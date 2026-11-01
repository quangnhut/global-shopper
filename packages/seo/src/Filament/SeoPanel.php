<?php

declare(strict_types=1);

namespace Shopper\Seo\Filament;

use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Shopper\Components\Form\UploadPicker;
use Shopper\Seo\Livewire\Pages\SeoSettingsPage;
use Shopper\Seo\Support\SeoFields;

/**
 * The per-entry SEO panel (Yoast's meta box), stored under the `seo` state path.
 * Read/write it with `SeoFields::state()` / `SeoFields::save()`.
 */
final class SeoPanel
{
    /**
     * @param  bool  $withBasics  false hides SEO title and description (e.g. products, whose own SEO tab edits them)
     * @param  (Closure(): array{title?: ?string, description?: ?string, url?: ?string})|null  $preview  fallback values for the preview
     */
    public static function make(bool $withBasics = true, ?Closure $preview = null, bool $collapsed = true): Section
    {
        return Section::make(__('shopper-seo::panel.title'))
            ->icon('phosphor-magnifying-glass')
            ->collapsible()
            ->collapsed($collapsed)
            ->statePath('seo')
            ->schema([
                Tabs::make('seo-panel')->tabs(array_values(array_filter([
                    $withBasics ? self::generalTab($preview) : null,
                    self::advancedTab(),
                    self::socialTab(),
                    self::schemaTab(),
                ]))),
            ]);
    }

    /**
     * @param  (Closure(): array{title?: ?string, description?: ?string, url?: ?string})|null  $preview
     */
    private static function generalTab(?Closure $preview): Tab
    {
        $counter = fn (int $recommended): Closure => fn (?string $state): string => __('shopper-seo::panel.counter', [
            'count' => mb_strlen((string) $state),
            'max' => $recommended,
        ]);

        return Tab::make(__('shopper-seo::panel.tabs.general'))->schema([
            Placeholder::make('preview')
                ->label(__('shopper-seo::settings.fields.preview'))
                ->content(function (Get $get) use ($preview): HtmlString {
                    $fallback = $preview !== null ? $preview() : [];

                    return new HtmlString(sprintf(
                        '<div class="max-w-xl rounded-lg bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">'
                        .'<p class="truncate text-xs text-gray-500">%s</p><p class="truncate text-lg text-blue-700 dark:text-blue-400">%s</p>'
                        .'<p class="line-clamp-2 text-sm text-gray-600 dark:text-gray-300">%s</p></div>',
                        e($fallback['url'] ?? config('app.url')),
                        e(filled($get('title')) ? $get('title') : ($fallback['title'] ?? __('shopper-seo::panel.uses_template'))),
                        e(filled($get('description')) ? $get('description') : ($fallback['description'] ?? __('shopper-seo::panel.uses_template'))),
                    ));
                }),
            TextInput::make('title')
                ->label(__('shopper-seo::settings.fields.title_template'))
                ->placeholder(__('shopper-seo::panel.uses_template'))
                ->hint($counter(60))
                ->maxLength(255)
                ->live(onBlur: true),
            Textarea::make('description')
                ->label(__('shopper-seo::settings.fields.meta_description'))
                ->placeholder(__('shopper-seo::panel.uses_template'))
                ->hint($counter(160))
                ->rows(3)
                ->maxLength(320)
                ->live(onBlur: true),
            TextInput::make('focus_keyword')
                ->label(__('shopper-seo::panel.focus_keyword'))
                ->helperText(__('shopper-seo::panel.focus_keyword_help'))
                ->maxLength(100),
        ]);
    }

    private static function advancedTab(): Tab
    {
        $tristate = fn (string $yes, string $no): array => [
            'default' => __('shopper-seo::panel.robots.default'),
            'yes' => $yes,
            'no' => $no,
        ];

        return Tab::make(__('shopper-seo::panel.tabs.advanced'))->columns()->schema([
            Select::make('robots_index')
                ->label(__('shopper-seo::panel.robots.index'))
                ->options($tristate('index', 'noindex'))
                ->default('default')
                ->selectablePlaceholder(false),
            Select::make('robots_follow')
                ->label(__('shopper-seo::panel.robots.follow'))
                ->options($tristate('follow', 'nofollow'))
                ->default('default')
                ->selectablePlaceholder(false),
            CheckboxList::make('robots_advanced')
                ->label(__('shopper-seo::panel.robots.advanced'))
                ->options(['noarchive' => 'noarchive', 'nosnippet' => 'nosnippet', 'noimageindex' => 'noimageindex'])
                ->columns(3),
            Select::make('max_image_preview')
                ->label('max-image-preview')
                ->options(['large' => 'large', 'standard' => 'standard', 'none' => 'none'])
                ->placeholder(__('shopper-seo::panel.robots.default')),
            TextInput::make('canonical_url')
                ->label(__('shopper-seo::panel.canonical'))
                ->helperText(__('shopper-seo::panel.canonical_help'))
                ->maxLength(2048)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (! SeoFields::isSafeUrl($value)) {
                        $fail(__('shopper-seo::panel.canonical_invalid'));
                    }
                })
                ->columnSpanFull(),
            TextInput::make('breadcrumb_title')
                ->label(__('shopper-seo::panel.breadcrumb_title'))
                ->maxLength(255),
            Toggle::make('exclude_from_sitemap')
                ->label(__('shopper-seo::panel.exclude_from_sitemap'))
                ->inline(false),
        ]);
    }

    private static function socialTab(): Tab
    {
        return Tab::make(__('shopper-seo::panel.tabs.social'))->columns()->schema([
            TextInput::make('og_title')->label(__('shopper-seo::panel.og_title'))->maxLength(255),
            UploadPicker::make('og_image_upload_id')->label(__('shopper-seo::panel.og_image')),
            Textarea::make('og_description')->label(__('shopper-seo::panel.og_description'))->rows(2)->maxLength(320)->columnSpanFull(),
            TextInput::make('twitter_title')->label(__('shopper-seo::panel.twitter_title'))->maxLength(255),
            UploadPicker::make('twitter_image_upload_id')->label(__('shopper-seo::panel.twitter_image')),
            Textarea::make('twitter_description')->label(__('shopper-seo::panel.twitter_description'))->rows(2)->maxLength(320)->columnSpanFull(),
        ]);
    }

    private static function schemaTab(): Tab
    {
        $canScripts = (bool) shopper()->auth()->user()?->can('manage_seo_scripts');

        return Tab::make(__('shopper-seo::panel.tabs.schema'))->schema([
            Select::make('schema_type')
                ->label(__('shopper-seo::settings.fields.schema_type'))
                ->options(array_combine(SeoSettingsPage::SCHEMA_TYPES, SeoSettingsPage::SCHEMA_TYPES))
                ->placeholder(__('shopper-seo::panel.robots.default')),
            Textarea::make('schema_custom')
                ->label(__('shopper-seo::panel.schema_custom'))
                ->helperText(__($canScripts ? 'shopper-seo::panel.schema_custom_help' : 'shopper-seo::settings.tracking.custom_code_locked'))
                ->rows(6)
                ->disabled(! $canScripts)
                ->extraInputAttributes(['class' => 'font-mono text-sm'])
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! is_array(json_decode((string) $value, true))) {
                        $fail(__('shopper-seo::panel.schema_custom_invalid'));
                    }
                }),
        ]);
    }
}
