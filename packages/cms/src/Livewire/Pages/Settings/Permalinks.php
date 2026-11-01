<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Settings;

use Closure;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Shopper\Cms\Permalinks\PermalinkSettings;
use Shopper\Cms\Permalinks\PermalinkStructure;
use Shopper\Cms\Permalinks\ReservedPaths;
use Shopper\Cms\Support\PostHierarchy;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * WordPress's Settings → Permalinks, plus "Your homepage displays" from Settings → Reading.
 *
 * @property-read Schema $form
 */
#[Layout('shopper::components.layouts.setting')]
class Permalinks extends AbstractPageComponent implements HasSchemas
{
    use HandlesAuthorizationExceptions;
    use InteractsWithSchemas;

    /**
     * WordPress's "Common settings".
     */
    public const array PRESETS = [
        'plain' => '',
        'day_and_name' => '/%year%/%monthnum%/%day%/%postname%/',
        'month_and_name' => '/%year%/%monthnum%/%postname%/',
        'numeric' => '/archives/%post_id%',
        'post_name' => '/%postname%/',
    ];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(PermalinkSettings $settings): void
    {
        $this->authorize('manage_permalinks');

        $structure = $settings->structure()->value;
        $preset = array_search($structure, self::PRESETS, true);

        $this->form->fill([
            'preset' => $preset === false ? 'custom' : $preset,
            'custom_structure' => $preset === false ? $structure : null,
            'category_base' => $settings->categoryBase() === 'category' ? null : $settings->categoryBase(),
            'tag_base' => $settings->tagBase() === 'tag' ? null : $settings->tagBase(),
            'front_page_id' => $settings->frontPageId(),
            'posts_page_id' => $settings->postsPageId(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('shopper-cms::permalinks.common'))
                    ->schema([
                        Radio::make('preset')
                            ->hiddenLabel()
                            ->options(collect([...array_keys(self::PRESETS), 'custom'])
                                ->mapWithKeys(fn (string $preset): array => [$preset => __("shopper-cms::permalinks.structures.{$preset}")])
                                ->all())
                            ->descriptions(collect(self::PRESETS)
                                ->map(fn (string $structure): string => $this->example($structure))
                                ->all())
                            ->required()
                            ->live(),
                        TextInput::make('custom_structure')
                            ->label(__('shopper-cms::permalinks.structures.custom'))
                            ->placeholder('/%category%/%postname%/')
                            ->helperText(fn (Get $get): string => __('shopper-cms::permalinks.custom_help', [
                                'tags' => implode(' ', array_keys(PermalinkStructure::TAGS)),
                            ]).(filled($get('custom_structure')) ? ' · '.$this->example((string) $get('custom_structure')) : ''))
                            ->required(fn (Get $get): bool => $get('preset') === 'custom')
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                foreach ((new PermalinkStructure((string) $value))->errors(ReservedPaths::all()) as $error) {
                                    $fail(__($error));
                                }
                            })
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => $get('preset') === 'custom'),
                    ]),
                Section::make(__('shopper-cms::permalinks.optional'))
                    ->description(__('shopper-cms::permalinks.optional_help'))
                    ->columns()
                    ->schema([
                        TextInput::make('category_base')
                            ->label(__('shopper-cms::permalinks.category_base'))
                            ->placeholder('category')
                            ->rule($this->baseRule(...)),
                        TextInput::make('tag_base')
                            ->label(__('shopper-cms::permalinks.tag_base'))
                            ->placeholder('tag')
                            ->rule($this->baseRule(...))
                            ->different('category_base'),
                    ]),
                Section::make(__('shopper-cms::permalinks.reading'))
                    ->columns()
                    ->schema([
                        Select::make('front_page_id')
                            ->label(__('shopper-cms::permalinks.front_page'))
                            ->placeholder(__('shopper-cms::permalinks.latest_posts'))
                            ->options(fn (): array => PostHierarchy::parentOptions('page'))
                            ->searchable()
                            ->live(),
                        Select::make('posts_page_id')
                            ->label(__('shopper-cms::permalinks.posts_page'))
                            ->options(fn (): array => PostHierarchy::parentOptions('page'))
                            ->searchable()
                            ->different('front_page_id')
                            ->visible(fn (Get $get): bool => filled($get('front_page_id'))),
                    ]),
            ]);
    }

    public function save(PermalinkSettings $settings): void
    {
        $this->authorize('manage_permalinks');

        $data = $this->form->getState();

        $settings->save([
            'structure' => $data['preset'] === 'custom'
                ? (new PermalinkStructure((string) $data['custom_structure']))->value
                : self::PRESETS[$data['preset']],
            'category_base' => Str::slug((string) ($data['category_base'] ?? '')) ?: null,
            'tag_base' => Str::slug((string) ($data['tag_base'] ?? '')) ?: null,
            'front_page_id' => $data['front_page_id'] ?? null,
            'posts_page_id' => filled($data['front_page_id'] ?? null) ? ($data['posts_page_id'] ?? null) : null,
        ]);

        Notification::make()->title(__('shopper-cms::permalinks.saved'))->success()->send();
    }

    public function render(): View
    {
        return view('shopper-cms::livewire.pages.settings.permalinks')
            ->title(__('shopper-cms::permalinks.title'));
    }

    protected function baseRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (filled($value) && ReservedPaths::contains(Str::slug((string) $value))) {
                $fail(__('shopper-cms::permalinks.errors.base_reserved'));
            }
        };
    }

    /**
     * Example URL like WordPress shows next to each option.
     */
    protected function example(string $structure): string
    {
        $base = mb_rtrim((string) (config('shopper.cms.frontend_url') ?: config('app.url')), '/');
        $structure = new PermalinkStructure($structure);

        if ($structure->isPlain()) {
            return $base.'/?p=123';
        }

        $date = now();

        return $base.$structure->build([
            'year' => $date->format('Y'), 'monthnum' => $date->format('m'), 'day' => $date->format('d'),
            'hour' => $date->format('H'), 'minute' => $date->format('i'), 'second' => $date->format('s'),
            'post_id' => 123, 'postname' => 'sample-post', 'category' => 'uncategorized', 'author' => 'admin',
        ]);
    }
}
