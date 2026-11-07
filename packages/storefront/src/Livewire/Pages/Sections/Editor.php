<?php

declare(strict_types=1);

namespace Shopper\Storefront\Livewire\Pages\Sections;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Storefront\Actions\SaveHomeSectionAction;
use Shopper\Storefront\Enums\HomeSectionLayout;
use Shopper\Storefront\Enums\HomeSectionStyle;
use Shopper\Storefront\Enums\HomeSectionType;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Models\HomeSectionItem;
use Shopper\Storefront\Support\ItemPicker;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * Create or edit a home page section: its settings and the products or posts it shows, in order.
 *
 * @property-read Schema $form
 */
class Editor extends AbstractPageComponent implements HasActions, HasSchemas
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?HomeSection $section = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(?HomeSection $section = null): void
    {
        $this->authorize('manage_homepage');

        $this->section = $section?->exists ? $section : null;

        if (! $this->section instanceof HomeSection) {
            $this->form->fill([
                'type' => HomeSectionType::Products->value,
                'style' => HomeSectionStyle::Default->value,
                'layout' => HomeSectionLayout::Grid->value,
                'columns' => 5,
                'is_enabled' => true,
                'items' => [],
            ]);

            return;
        }

        $this->form->fill([
            ...$this->section->only(['title', 'subtitle', 'columns', 'limit', 'view_all_url', 'is_enabled', 'starts_at', 'ends_at']),
            'type' => $this->section->type->value,
            'style' => $this->section->style->value,
            'layout' => $this->section->layout->value,
            'items' => $this->section->items->map(fn (HomeSectionItem $item): int => (int) $item->itemable_id)->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['lg' => 3])->schema([
                    Section::make(__('shopper-storefront::admin.sections.items'))
                        ->description(fn (): string => $this->type() === HomeSectionType::Products
                            ? __('shopper-storefront::admin.sections.items_products_help')
                            : __('shopper-storefront::admin.sections.items_posts_help'))
                        ->columnSpan(['lg' => 2])
                        ->schema([
                            Repeater::make('items')
                                ->hiddenLabel()
                                ->simple(
                                    Select::make('id')
                                        ->label(fn (): string => $this->type()->getLabel())
                                        ->hiddenLabel()
                                        ->required()
                                        ->searchable()
                                        ->allowHtml()
                                        ->distinct()
                                        ->getSearchResultsUsing(fn (?string $search): array => resolve(ItemPicker::class)->search($this->type(), $search))
                                        ->options(fn (): array => resolve(ItemPicker::class)->search($this->type(), null))
                                        ->getOptionLabelUsing(fn (mixed $value): ?string => resolve(ItemPicker::class)->labels($this->type(), [(int) $value])[(int) $value] ?? null),
                                )
                                ->reorderableWithDragAndDrop()
                                ->addActionLabel(fn (): string => $this->type() === HomeSectionType::Products
                                    ? __('shopper-storefront::admin.sections.add_product')
                                    : __('shopper-storefront::admin.sections.add_post'))
                                ->maxItems(60)
                                ->defaultItems(0),
                        ]),
                    Grid::make(1)->columnSpan(1)->schema([
                        Section::make(__('shopper-storefront::admin.sections.details'))
                            ->schema([
                                ToggleButtons::make('type')
                                    ->label(__('shopper-storefront::admin.sections.type'))
                                    ->options(HomeSectionType::class)
                                    ->inline()
                                    ->required()
                                    ->live()
                                    ->disabled(fn (): bool => $this->section instanceof HomeSection)
                                    ->helperText(fn (): ?string => $this->section instanceof HomeSection ? __('shopper-storefront::admin.sections.type_locked') : null)
                                    ->afterStateUpdated(fn (Set $set): mixed => $set('items', [])),
                                TextInput::make('title')
                                    ->label(__('shopper-storefront::admin.sections.title_field'))
                                    ->placeholder(__('shopper-storefront::admin.sections.title_placeholder'))
                                    ->required()
                                    ->maxLength(120),
                                TextInput::make('subtitle')
                                    ->label(__('shopper-storefront::admin.sections.subtitle'))
                                    ->maxLength(255),
                                Select::make('style')
                                    ->label(__('shopper-storefront::admin.sections.style'))
                                    ->helperText(__('shopper-storefront::admin.sections.style_help'))
                                    ->options(HomeSectionStyle::class)
                                    ->native(false)
                                    ->required(),
                                Toggle::make('is_enabled')
                                    ->label(__('shopper-storefront::admin.sections.enabled')),
                            ]),
                        Section::make(__('shopper-storefront::admin.sections.display'))
                            ->schema([
                                ToggleButtons::make('layout')
                                    ->label(__('shopper-storefront::admin.sections.layout'))
                                    ->options(HomeSectionLayout::class)
                                    ->inline()
                                    ->required(),
                                TextInput::make('columns')
                                    ->label(__('shopper-storefront::admin.sections.columns'))
                                    ->helperText(__('shopper-storefront::admin.sections.columns_help'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(2)
                                    ->maxValue(6)
                                    ->required(),
                                TextInput::make('limit')
                                    ->label(__('shopper-storefront::admin.sections.limit'))
                                    ->helperText(__('shopper-storefront::admin.sections.limit_help'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(60),
                                TextInput::make('view_all_url')
                                    ->label(__('shopper-storefront::admin.sections.view_all_url'))
                                    ->placeholder('/categories/khuyen-mai')
                                    ->maxLength(255)
                                    ->rule('regex:/^(\/(?!\/)|https?:\/\/)\S*$/'),
                            ]),
                        Section::make(__('shopper-storefront::admin.sections.schedule'))
                            ->description(__('shopper-storefront::admin.sections.schedule_help'))
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label(__('shopper-storefront::admin.sections.starts_at'))
                                    ->seconds(false),
                                DateTimePicker::make('ends_at')
                                    ->label(__('shopper-storefront::admin.sections.ends_at'))
                                    ->seconds(false)
                                    ->after('starts_at'),
                            ]),
                    ]),
                ]),
            ]);
    }

    public function save(SaveHomeSectionAction $saveSection): void
    {
        $this->authorize('manage_homepage');

        $data = $this->form->getState();
        $items = array_values(Arr::pull($data, 'items') ?? []);

        if ($this->section instanceof HomeSection) {
            $data['type'] = $this->section->type->value;
        }

        $data['limit'] = filled($data['limit'] ?? null) ? (int) $data['limit'] : null;
        $data['view_all_url'] = filled($data['view_all_url'] ?? null) ? $data['view_all_url'] : null;

        $created = ! $this->section instanceof HomeSection;
        $this->section = $saveSection->execute($this->section, $data, $items);

        Notification::make()
            ->title(__($created ? 'shopper::notifications.create' : 'shopper::notifications.update', ['item' => __('shopper-storefront::admin.sections.single')]))
            ->success()
            ->send();

        if ($created) {
            $this->redirectRoute('shopper.storefront.sections.edit', $this->section, navigate: true);
        }
    }

    public function render(): View
    {
        return view('shopper-storefront::livewire.pages.sections.editor')
            ->title($this->section instanceof HomeSection ? $this->section->title : __('shopper-storefront::admin.sections.create'));
    }

    /**
     * Read from the form state: the item fields live inside repeater rows, where `type` is out of reach of `$get`.
     */
    private function type(): HomeSectionType
    {
        if ($this->section instanceof HomeSection) {
            return $this->section->type;
        }

        $type = $this->data['type'] ?? null;

        return $type instanceof HomeSectionType ? $type : (HomeSectionType::tryFrom((string) $type) ?? HomeSectionType::Products);
    }
}
