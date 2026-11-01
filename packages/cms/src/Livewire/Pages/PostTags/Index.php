<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\PostTags;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteBulkAction;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Cms\Models\PostTag;
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
        $this->authorize('browse_post_tags');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PostTag::query()->withCount('posts'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('shopper::forms.label.name'))
                    ->description(fn (PostTag $record): ?string => Str::limit((string) $record->description, 80) ?: null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('shopper::forms.label.slug'))
                    ->searchable(),
                TextColumn::make('posts_count')
                    ->label(__('shopper-cms::taxonomies.tags.count'))
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('shopper::forms.actions.edit'))
                    ->icon(Untitledui::Edit03)
                    ->iconButton()
                    ->fillForm(fn (PostTag $record): array => [...$record->only(['name', 'slug', 'description']), 'seo' => SeoFields::state($record)])
                    ->schema($this->tagForm(...))
                    ->modalWidth(Width::Medium)
                    ->action(function (PostTag $record, array $data): void {
                        $record->update($this->attributes($data));
                        $this->saveSeo($record, $data);

                        Notification::make()
                            ->title(__('shopper::notifications.update', ['item' => __('shopper-cms::taxonomies.tags.single')]))
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel(__('shopper::forms.actions.update'))
                    ->authorize('edit_post_tags')
                    ->visible(shopper()->auth()->user()->can('edit_post_tags')),
                Action::make('delete')
                    ->label(__('shopper::forms.actions.delete'))
                    ->icon(Untitledui::Trash03)
                    ->iconButton()
                    ->modalIcon(Untitledui::Trash03)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PostTag $record): void {
                        $record->delete();

                        Notification::make()
                            ->title(__('shopper::notifications.delete', ['item' => __('shopper-cms::taxonomies.tags.single')]))
                            ->success()
                            ->send();
                    })
                    ->authorize('delete_post_tags')
                    ->visible(shopper()->auth()->user()->can('delete_post_tags')),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make()
                    ->authorize('delete_post_tags')
                    ->visible(shopper()->auth()->user()->can('delete_post_tags'))
                    ->label(__('shopper::forms.actions.delete'))
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $records->each->delete();

                        Notification::make()
                            ->title(__('shopper::notifications.delete', ['item' => __('shopper-cms::taxonomies.tags.single')]))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public function tagForm(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('shopper::forms.label.name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?Model $record, ?string $state, Set $set, Get $get): void {
                    if ($record?->exists && filled($get('slug'))) {
                        return;
                    }

                    $set('slug', Str::slug($state ?? ''));
                }),
            TextInput::make('slug')
                ->label(__('shopper::forms.label.slug'))
                ->maxLength(255)
                ->unique(PostTag::class, 'slug', ignoreRecord: true),
            Textarea::make('description')
                ->label(__('shopper::forms.label.description'))
                ->rows(3),
            SeoPanel::make(),
        ]);
    }

    public function createAction(): Action
    {
        return Action::make('create')
            ->authorize('add_post_tags')
            ->label(__('shopper-cms::taxonomies.tags.create'))
            ->schema($this->tagForm(...))
            ->modalWidth(Width::Medium)
            ->modalHeading(__('shopper-cms::taxonomies.tags.create'))
            ->action(function (array $data): void {
                $this->saveSeo(PostTag::query()->create($this->attributes($data)), $data);

                Notification::make()
                    ->title(__('shopper::notifications.create', ['item' => __('shopper-cms::taxonomies.tags.single')]))
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('shopper-cms::livewire.pages.post-tags.index')
            ->title(__('shopper-cms::taxonomies.tags.menu'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveSeo(PostTag $tag, array $data): void
    {
        if (array_key_exists('seo', $data)) {
            SeoFields::save($tag, (array) $data['seo'], (bool) shopper()->auth()->user()?->can('manage_seo_scripts'));
        }
    }

    /**
     * An empty slug is dropped so the model generates one from the name.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['name', 'slug', 'description']);

        return filled($attributes['slug'] ?? null) ? $attributes : Arr::except($attributes, 'slug');
    }
}
