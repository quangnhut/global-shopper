<?php

declare(strict_types=1);

namespace Shopper\Seo\Livewire\Pages;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Shopper\Components\Form\UploadPicker;
use Shopper\Core\Models\Setting;
use Shopper\Core\Models\Upload;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Seo\Actions\GenerateSiteIconsAction;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\TemplateRenderer;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * SEO → Settings: General, Titles & meta, Social, Schema, Indexing, Tracking & verification, robots.txt.
 *
 * @property-read Schema $form
 */
class SeoSettingsPage extends AbstractPageComponent implements HasActions, HasSchemas
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public const array SEPARATORS = ['-', '–', '|', '·', '•', '»', '/'];

    public const array SCHEMA_TYPES = [
        'WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'CollectionPage', 'Article', 'BlogPosting', 'NewsArticle', 'Product',
    ];

    public const array LOCAL_BUSINESS_TYPES = [
        'Store', 'ClothingStore', 'ElectronicsStore', 'FurnitureStore', 'HomeGoodsStore', 'ShoeStore',
        'SportingGoodsStore', 'JewelryStore', 'BookStore', 'GroceryStore', 'OnlineStore',
    ];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(SeoSettings $settings): void
    {
        $this->authorize('manage_seo');

        $this->form->fill($settings->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('seo')
                    ->persistTabInQueryString()
                    ->tabs([
                        $this->generalTab(),
                        $this->titlesTab(),
                        $this->socialTab(),
                        $this->schemaTab(),
                        $this->indexingTab(),
                        $this->trackingTab(),
                        $this->robotsTab(),
                    ]),
            ]);
    }

    public function save(SeoSettings $settings, GenerateSiteIconsAction $generateIcons): void
    {
        $this->authorize('manage_seo');

        $data = $this->form->getState();

        // Raw code can run scripts on the storefront: only users trusted with it may change it.
        if (! shopper()->auth()->user()?->can('manage_seo_scripts')) {
            foreach (['custom_head', 'custom_body_open', 'custom_body_close'] as $field) {
                $data['tracking'][$field] = $settings->get("tracking.{$field}");
            }
        }

        $previousIcon = $settings->get('general.site_icon_upload_id');

        foreach (SeoSettings::GROUPS as $group) {
            if (array_key_exists($group, $data)) {
                $settings->save($group, (array) $data[$group]);
            }
        }

        $iconId = $data['general']['site_icon_upload_id'] ?? null;

        if ($iconId !== null && ((int) $iconId !== (int) $previousIcon || $settings->get('general.site_icons') === null)) {
            $upload = Upload::query()->find($iconId);

            if ($upload instanceof Upload && $upload->isImage()) {
                $settings->save('general', ['site_icons' => $generateIcons->execute($upload, $this->siteName())]);
            }
        }

        Notification::make()->title(__('shopper-seo::settings.saved'))->success()->send();
    }

    public function render(): View
    {
        return view('shopper-seo::livewire.pages.settings')->title(__('shopper-seo::settings.title'));
    }

    protected function generalTab(): Tab
    {
        return Tab::make(__('shopper-seo::settings.tabs.general'))
            ->id('general')
            ->schema([
                Section::make(__('shopper-seo::settings.general.identity'))
                    ->columns()
                    ->schema([
                        TextInput::make('general.site_name')
                            ->label(__('shopper-seo::settings.general.site_name'))
                            ->placeholder(fn (): string => (string) $this->storeName())
                            ->maxLength(120),
                        TextInput::make('general.tagline')
                            ->label(__('shopper-seo::settings.general.tagline'))
                            ->maxLength(160),
                        ToggleButtons::make('general.separator')
                            ->label(__('shopper-seo::settings.general.separator'))
                            ->options(array_combine(self::SEPARATORS, self::SEPARATORS))
                            ->inline()
                            ->columnSpanFull(),
                        UploadPicker::make('general.site_icon_upload_id')
                            ->label(__('shopper-seo::settings.general.site_icon'))
                            ->helperText(__('shopper-seo::settings.general.site_icon_help'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('shopper-seo::settings.general.home'))
                    ->schema([
                        TextInput::make('general.home_title')
                            ->label(__('shopper-seo::settings.fields.title_template'))
                            ->helperText($this->variablesHelp()),
                        Textarea::make('general.home_description')
                            ->label(__('shopper-seo::settings.fields.meta_description'))
                            ->rows(2)
                            ->maxLength(320),
                    ]),
                Section::make(__('shopper-seo::settings.general.visibility'))
                    ->schema([
                        Toggle::make('general.discourage_search_engines')
                            ->label(__('shopper-seo::settings.general.discourage'))
                            ->helperText(__('shopper-seo::settings.general.discourage_help'))
                            ->onColor('danger'),
                    ]),
            ]);
    }

    protected function titlesTab(): Tab
    {
        $registry = resolve(SeoableRegistry::class);
        $sections = [];

        foreach (array_keys($registry->all()) as $type) {
            $sections[] = Section::make($registry->label($type))
                ->collapsible()
                ->collapsed($type !== 'post' && $type !== 'product')
                ->columns()
                ->schema([
                    TextInput::make("titles.{$type}.title")
                        ->label(__('shopper-seo::settings.fields.title_template'))
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                    Textarea::make("titles.{$type}.description")
                        ->label(__('shopper-seo::settings.fields.description_template'))
                        ->rows(2)
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                    Placeholder::make("titles.{$type}.preview")
                        ->label(__('shopper-seo::settings.fields.preview'))
                        ->content(fn (Get $get): HtmlString => $this->snippetPreview($get("titles.{$type}.title"), $get("titles.{$type}.description"), $get('general.separator')))
                        ->columnSpanFull(),
                    Toggle::make("titles.{$type}.noindex")
                        ->label(__('shopper-seo::settings.fields.noindex'))
                        ->helperText(__('shopper-seo::settings.fields.noindex_help')),
                    Toggle::make("titles.{$type}.sitemap")
                        ->label(__('shopper-seo::settings.fields.sitemap')),
                    Select::make("titles.{$type}.schema_type")
                        ->label(__('shopper-seo::settings.fields.schema_type'))
                        ->options(array_combine(self::SCHEMA_TYPES, self::SCHEMA_TYPES))
                        ->selectablePlaceholder(false),
                    UploadPicker::make("titles.{$type}.og_image_upload_id")
                        ->label(__('shopper-seo::settings.fields.default_image')),
                ]);
        }

        foreach (['search', 'not_found'] as $special) {
            $sections[] = Section::make(__("shopper-seo::types.{$special}"))
                ->collapsible()
                ->collapsed()
                ->columns()
                ->schema([
                    TextInput::make("titles.{$special}.title")
                        ->label(__('shopper-seo::settings.fields.title_template')),
                    Toggle::make("titles.{$special}.noindex")
                        ->label(__('shopper-seo::settings.fields.noindex')),
                ]);
        }

        return Tab::make(__('shopper-seo::settings.tabs.titles'))
            ->id('titles')
            ->schema([
                Placeholder::make('titles_help')->hiddenLabel()->content($this->variablesHelp()),
                ...$sections,
            ]);
    }

    protected function socialTab(): Tab
    {
        return Tab::make(__('shopper-seo::settings.tabs.social'))
            ->id('social')
            ->schema([
                Section::make()
                    ->columns()
                    ->schema([
                        UploadPicker::make('social.default_image_upload_id')
                            ->label(__('shopper-seo::settings.social.default_image'))
                            ->helperText(__('shopper-seo::settings.social.default_image_help'))
                            ->columnSpanFull(),
                        TextInput::make('social.facebook_url')
                            ->label(__('shopper-seo::settings.social.facebook_url'))
                            ->url(),
                        TextInput::make('social.facebook_app_id')
                            ->label(__('shopper-seo::settings.social.facebook_app_id'))
                            ->regex('/^\d+$/'),
                        TextInput::make('social.twitter_handle')
                            ->label(__('shopper-seo::settings.social.twitter_handle'))
                            ->prefix('@')
                            ->regex('/^@?\w{1,15}$/')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_ltrim($state, '@') : null),
                        Select::make('social.twitter_card')
                            ->label(__('shopper-seo::settings.social.twitter_card'))
                            ->options(['summary' => 'summary', 'summary_large_image' => 'summary_large_image'])
                            ->selectablePlaceholder(false),
                        TagsInput::make('social.profiles')
                            ->label(__('shopper-seo::settings.social.profiles'))
                            ->helperText(__('shopper-seo::settings.social.profiles_help'))
                            ->placeholder('https://instagram.com/…')
                            ->nestedRecursiveRules(['url'])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function schemaTab(): Tab
    {
        return Tab::make(__('shopper-seo::settings.tabs.schema'))
            ->id('schema')
            ->schema([
                Section::make(__('shopper-seo::settings.schema.publisher'))
                    ->columns()
                    ->schema([
                        Radio::make('schema.entity')
                            ->label(__('shopper-seo::settings.schema.entity'))
                            ->options([
                                'organization' => __('shopper-seo::settings.schema.organization'),
                                'person' => __('shopper-seo::settings.schema.person'),
                            ])
                            ->inline()
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('schema.name')
                            ->label(__('shopper-seo::settings.schema.name'))
                            ->placeholder(fn (): string => (string) $this->storeName()),
                        UploadPicker::make('schema.logo_upload_id')
                            ->label(__('shopper-seo::settings.schema.logo')),
                        TextInput::make('schema.email')
                            ->label(__('shopper::forms.label.email'))
                            ->email(),
                        TextInput::make('schema.phone')
                            ->label(__('shopper::forms.label.phone_number'))
                            ->tel(),
                        Select::make('schema.local_business_type')
                            ->label(__('shopper-seo::settings.schema.local_business'))
                            ->helperText(__('shopper-seo::settings.schema.local_business_help'))
                            ->options(array_combine(self::LOCAL_BUSINESS_TYPES, self::LOCAL_BUSINESS_TYPES))
                            ->visible(fn (Get $get): bool => $get('schema.entity') === 'organization'),
                        TagsInput::make('schema.opening_hours')
                            ->label(__('shopper-seo::settings.schema.opening_hours'))
                            ->placeholder('Mo-Fr 09:00-18:00')
                            ->nestedRecursiveRules(['regex:/^(Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))?(,(Mo|Tu|We|Th|Fr|Sa|Su))* \d{2}:\d{2}-\d{2}:\d{2}$/'])
                            ->visible(fn (Get $get): bool => filled($get('schema.local_business_type'))),
                    ]),
                Section::make(__('shopper-seo::settings.schema.website'))
                    ->columns()
                    ->schema([
                        TextInput::make('schema.search_url')
                            ->label(__('shopper-seo::settings.schema.search_url'))
                            ->helperText(__('shopper-seo::settings.schema.search_url_help'))
                            ->placeholder('/search?q={search_term_string}')
                            ->rule('nullable')
                            ->regex('/\{search_term_string\}/'),
                        Select::make('schema.article_type')
                            ->label(__('shopper-seo::settings.schema.article_type'))
                            ->options(['Article' => 'Article', 'BlogPosting' => 'BlogPosting', 'NewsArticle' => 'NewsArticle'])
                            ->selectablePlaceholder(false),
                    ]),
            ]);
    }

    protected function indexingTab(): Tab
    {
        return Tab::make(__('shopper-seo::settings.tabs.indexing'))
            ->id('indexing')
            ->schema([
                Section::make(__('shopper-seo::settings.indexing.archives'))
                    ->columns()
                    ->schema([
                        Toggle::make('indexing.noindex_date_archives')->label(__('shopper-seo::settings.indexing.date_archives')),
                        Toggle::make('indexing.noindex_author_archives')->label(__('shopper-seo::settings.indexing.author_archives')),
                        Toggle::make('indexing.noindex_search')->label(__('shopper-seo::settings.indexing.search')),
                        Toggle::make('indexing.noindex_paginated')->label(__('shopper-seo::settings.indexing.paginated')),
                        Toggle::make('indexing.noindex_empty_terms')->label(__('shopper-seo::settings.indexing.empty_terms')),
                    ]),
                Section::make(__('shopper-seo::settings.indexing.paths'))
                    ->description(__('shopper-seo::settings.indexing.paths_help'))
                    ->schema([
                        Repeater::make('indexing.noindex_paths')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('pattern')
                                    ->label(__('shopper-seo::settings.indexing.pattern'))
                                    ->placeholder('/cart*')
                                    ->required()
                                    ->regex('#^/[^\s]*$#'),
                                Toggle::make('nofollow')
                                    ->label(__('shopper-seo::settings.indexing.nofollow'))
                                    ->inline(false),
                            ])
                            ->columns()
                            ->defaultItems(0)
                            ->reorderable(false),
                    ]),
            ]);
    }

    protected function trackingTab(): Tab
    {
        $canScripts = (bool) shopper()->auth()->user()?->can('manage_seo_scripts');
        $verification = fn (string $name, string $label): TextInput => TextInput::make("tracking.verification_{$name}")
            ->label($label)
            ->helperText(__('shopper-seo::settings.tracking.verification_help'))
            ->regex('/^[A-Za-z0-9_\-=.]+$/')
            ->maxLength(255);

        return Tab::make(__('shopper-seo::settings.tabs.tracking'))
            ->id('tracking')
            ->schema([
                Section::make(__('shopper-seo::settings.tracking.analytics'))
                    ->columns()
                    ->schema([
                        TextInput::make('tracking.gtm_id')
                            ->label(__('shopper-seo::settings.tracking.gtm'))
                            ->placeholder('GTM-XXXXXXX')
                            ->helperText(__('shopper-seo::settings.tracking.gtm_help'))
                            ->regex('/^GTM-[A-Z0-9]+$/'),
                        TextInput::make('tracking.ga4_id')
                            ->label(__('shopper-seo::settings.tracking.ga4'))
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText(__('shopper-seo::settings.tracking.ga4_help'))
                            ->regex('/^G-[A-Z0-9]+$/'),
                    ]),
                Section::make(__('shopper-seo::settings.tracking.verification'))
                    ->columns()
                    ->schema([
                        $verification('google', 'Google Search Console'),
                        $verification('bing', 'Bing Webmaster Tools'),
                        $verification('yandex', 'Yandex'),
                        $verification('pinterest', 'Pinterest'),
                        $verification('facebook', 'Facebook (domain verification)'),
                    ]),
                Section::make(__('shopper-seo::settings.tracking.custom_code'))
                    ->description(__($canScripts ? 'shopper-seo::settings.tracking.custom_code_warning' : 'shopper-seo::settings.tracking.custom_code_locked'))
                    ->schema([
                        Textarea::make('tracking.custom_head')->label(__('shopper-seo::settings.tracking.custom_head'))->rows(4)->disabled(! $canScripts),
                        Textarea::make('tracking.custom_body_open')->label(__('shopper-seo::settings.tracking.custom_body_open'))->rows(3)->disabled(! $canScripts),
                        Textarea::make('tracking.custom_body_close')->label(__('shopper-seo::settings.tracking.custom_body_close'))->rows(3)->disabled(! $canScripts),
                    ]),
            ]);
    }

    protected function robotsTab(): Tab
    {
        return Tab::make('robots.txt')
            ->id('robots')
            ->schema([
                Section::make()
                    ->schema([
                        Textarea::make('robots.content')
                            ->label('robots.txt')
                            ->helperText(__('shopper-seo::settings.robots.help'))
                            ->rows(12)
                            ->extraInputAttributes(['class' => 'font-mono text-sm']),
                    ]),
            ]);
    }

    protected function variablesHelp(): string
    {
        return __('shopper-seo::settings.fields.variables', ['variables' => implode(' ', TemplateRenderer::VARIABLES)]);
    }

    /**
     * Google-like result preview with sample values.
     */
    protected function snippetPreview(?string $title, ?string $description, ?string $separator): HtmlString
    {
        $renderer = resolve(TemplateRenderer::class);
        $values = [
            'title' => __('shopper-seo::settings.preview.sample_title'),
            'sitename' => $this->siteName(),
            'tagline' => (string) ($this->data['general']['tagline'] ?? ''),
            'excerpt' => __('shopper-seo::settings.preview.sample_excerpt'),
            'description' => __('shopper-seo::settings.preview.sample_excerpt'),
            'category' => __('shopper-seo::settings.preview.sample_category'),
            'currentyear' => now()->format('Y'),
        ];

        $renderedTitle = $renderer->render($title, $values, $separator ?? '-');
        $renderedDescription = TemplateRenderer::excerpt($renderer->render($description, $values, $separator ?? '-'));

        return new HtmlString(sprintf(
            '<div class="max-w-xl rounded-lg bg-white p-3 text-left ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">'
            .'<p class="text-xs text-gray-500">%s</p><p class="truncate text-lg text-blue-700 dark:text-blue-400">%s</p>'
            .'<p class="text-sm text-gray-600 dark:text-gray-300">%s</p></div>',
            e(config('app.url')),
            e($renderedTitle),
            e($renderedDescription),
        ));
    }

    protected function siteName(): string
    {
        return (string) ($this->data['general']['site_name'] ?? null ?: $this->storeName() ?: config('app.name'));
    }

    protected function storeName(): ?string
    {
        $name = Setting::query()->where('key', 'name')->value('value');

        return is_string($name) ? $name : null;
    }
}
