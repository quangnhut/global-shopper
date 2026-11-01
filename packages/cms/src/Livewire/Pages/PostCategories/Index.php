<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\PostCategories;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Cms\Actions\DeletePostCategoryAction;
use Shopper\Cms\Actions\ResolveDefaultCategoryAction;
use Shopper\Cms\Actions\SavePostCategoryAction;
use Shopper\Cms\Models\PostCategory;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Seo\Filament\SeoPanel;
use Shopper\Seo\Support\SeoFields;
use Shopper\Traits\HandlesAuthorizationExceptions;

class Index extends AbstractPageComponent implements HasActions, HasSchemas, HasTable
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        $this->authorize('browse_post_categories');
    }

    public function table(Table $table): Table
    {
        $defaultId = resolve(ResolveDefaultCategoryAction::class)->execute()->id;

        return $table
            ->query(PostCategory::query()->with('parent')->withCount('posts'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('shopper::forms.label.name'))
                    ->description(fn (PostCategory $record): ?string => Str::limit((string) $record->description, 80) ?: null)
                    ->badge(fn (PostCategory $record): bool => $record->id === $defaultId)
                    ->color(fn (PostCategory $record): ?string => $record->id === $defaultId ? 'primary' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('shopper::forms.label.slug'))
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->label(__('shopper-cms::taxonomies.categories.parent'))
                    ->placeholder('—'),
                TextColumn::make('posts_count')
                    ->label(__('shopper-cms::taxonomies.categories.count'))
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('shopper::forms.actions.edit'))
                    ->icon(Untitledui::Edit03)
                    ->iconButton()
                    ->fillForm(fn (PostCategory $record): array => [...$record->only(['name', 'slug', 'description', 'parent_id']), 'seo' => SeoFields::state($record)])
                    ->schema(fn (Schema $schema, PostCategory $record): Schema => $this->categoryForm($schema, $record))
                    ->modalWidth(Width::Large)
                    ->action(function (PostCategory $record, array $data): void {
                        $category = resolve(SavePostCategoryAction::class)->execute($data, $record);
                        $this->saveSeo($category, $data);

                        Notification::make()
                            ->title(__('shopper::notifications.update', ['item' => __('shopper-cms::taxonomies.categories.single')]))
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel(__('shopper::forms.actions.update'))
                    ->authorize('edit_post_categories')
                    ->visible(shopper()->auth()->user()->can('edit_post_categories')),
                Action::make('delete')
                    ->label(__('shopper::forms.actions.delete'))
                    ->icon(Untitledui::Trash03)
                    ->iconButton()
                    ->modalIcon(Untitledui::Trash03)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PostCategory $record): void {
                        resolve(DeletePostCategoryAction::class)->execute($record);

                        Notification::make()
                            ->title(__('shopper::notifications.delete', ['item' => __('shopper-cms::taxonomies.categories.single')]))
                            ->success()
                            ->send();
                    })
                    ->authorize('delete_post_categories')
                    ->visible(fn (PostCategory $record): bool => $record->id !== $defaultId
                        && shopper()->auth()->user()->can('delete_post_categories')),
            ])
            ->groupedBulkActions([
                BulkAction::make('delete')
                    ->label(__('shopper::forms.actions.delete'))
                    ->icon(Untitledui::Trash03)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize('delete_post_categories')
                    ->visible(shopper()->auth()->user()->can('delete_post_categories'))
                    ->action(function (Collection $records) use ($defaultId): void {
                        /** @var Collection<int, PostCategory> $records */
                        $records->reject(fn (PostCategory $record): bool => $record->id === $defaultId)
                            ->each(fn (PostCategory $record) => resolve(DeletePostCategoryAction::class)->execute($record));

                        Notification::make()
                            ->title(__('shopper::notifications.delete', ['item' => __('shopper-cms::taxonomies.categories.single')]))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public function categoryForm(Schema $schema, ?PostCategory $record = null): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('shopper::forms.label.name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($record): void {
                    if ($record?->exists && filled($get('slug'))) {
                        return;
                    }

                    $set('slug', Str::slug($state ?? ''));
                }),
            TextInput::make('slug')
                ->label(__('shopper::forms.label.slug'))
                ->maxLength(255)
                ->unique(PostCategory::class, 'slug', ignoreRecord: true),
            Select::make('parent_id')
                ->label(__('shopper-cms::taxonomies.categories.parent'))
                ->placeholder(__('shopper-cms::taxonomies.categories.none'))
                ->options(fn (): array => $this->parentOptions($record))
                ->searchable(),
            Textarea::make('description')
                ->label(__('shopper::forms.label.description'))
                ->rows(3),
            SeoPanel::make(),
        ]);
    }

    public function createAction(): Action
    {
        return Action::make('create')
            ->authorize('add_post_categories')
            ->label(__('shopper-cms::taxonomies.categories.create'))
            ->schema(fn (Schema $schema): Schema => $this->categoryForm($schema))
            ->modalWidth(Width::Large)
            ->modalHeading(__('shopper-cms::taxonomies.categories.create'))
            ->action(function (array $data): void {
                $this->saveSeo(resolve(SavePostCategoryAction::class)->execute($data), $data);

                Notification::make()
                    ->title(__('shopper::notifications.create', ['item' => __('shopper-cms::taxonomies.categories.single')]))
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('shopper-cms::livewire.pages.post-categories.index')
            ->title(__('shopper-cms::taxonomies.categories.menu'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveSeo(PostCategory $category, array $data): void
    {
        if (array_key_exists('seo', $data)) {
            SeoFields::save($category, (array) $data['seo'], (bool) shopper()->auth()->user()?->can('manage_seo_scripts'));
        }
    }

    /**
     * Indented tree of possible parents. A category cannot move under itself or its descendants.
     *
     * @return array<int, string>
     */
    private function parentOptions(?PostCategory $record): array
    {
        $excluded = $record?->exists ? $record->descendantsAndSelf()->pluck('id')->all() : [];

        return PostCategory::tree()
            ->depthFirst()
            ->get()
            ->reject(fn (PostCategory $category): bool => in_array($category->id, $excluded, true))
            ->mapWithKeys(fn (PostCategory $category): array => [
                $category->id => str_repeat('— ', (int) $category->getAttribute('depth')).$category->name,
            ])
            ->all();
    }
}
