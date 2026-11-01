<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Posts;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Cms\Actions\ForceDeletePostAction;
use Shopper\Cms\Actions\RestorePostAction;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Actions\TrashPostAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Models\Post;
use Shopper\Cms\PostTypes\PostType;
use Shopper\Cms\PostTypes\PostTypeRegistry;
use Shopper\Cms\Support\PostHierarchy;
use Shopper\Cms\Support\PostPermissions;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Seo\Filament\SeoBulkActions;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * WordPress's "All Posts" / "All Pages" screen, shared by every post type.
 *
 * @property-read array<string, string> $monthOptions
 * @property-read array<int, string> $authorOptions
 * @property-read array<int, int> $depths
 */
abstract class AbstractPostIndex extends AbstractPageComponent implements HasActions, HasSchemas, HasTable
{
    use HandlesAuthorizationExceptions;
    use HasTabs;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    abstract public function typeKey(): string;

    public function mount(): void
    {
        $this->authorize($this->postType()->permission('browse'));

        $this->loadDefaultActiveTab();
    }

    public function postType(): PostType
    {
        return resolve(PostTypeRegistry::class)->get($this->typeKey());
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $type = $this->typeKey();
        $query = fn (): Builder => Post::configuredClass()::query()->ofType($type);

        $counts = $query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $mine = $query()->where('author_id', shopper()->auth()->id())->count();
        $trashed = $query()->onlyTrashed()->count();

        $tabs = [
            'all' => Tab::make(__('shopper-cms::posts.tabs.all'))
                ->icon(Untitledui::LayersThree)
                ->badge($counts->sum()),
        ];

        if ($mine > 0) {
            $tabs['mine'] = Tab::make(__('shopper-cms::posts.tabs.mine'))
                ->badge($mine)
                ->query(fn (Builder $query): Builder => $query->where('author_id', shopper()->auth()->id()));
        }

        foreach (PostStatus::cases() as $status) {
            if (($counts[$status->value] ?? 0) === 0) {
                continue;
            }

            $tabs[$status->value] = Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->badge($counts[$status->value])
                ->badgeColor($status->getColor())
                ->query(fn (Builder $query): Builder => $query->where('status', $status));
        }

        if ($trashed > 0) {
            $tabs['trash'] = Tab::make(__('shopper-cms::posts.tabs.trash'))
                ->icon(Untitledui::Trash03)
                ->badge($trashed)
                ->badgeColor('danger')
                ->query(fn (Builder $query): Builder => $query->onlyTrashed()); // @phpstan-ignore method.notFound
        }

        return $tabs;
    }

    public function table(Table $table): Table
    {
        $type = $this->postType();

        return $table
            ->query(
                Post::configuredClass()::query()
                    ->ofType($type->key)
                    ->with(['author', 'categories', 'tags'])
            )
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->defaultSort($type->hierarchical
                ? fn (Builder $query): Builder => $this->orderHierarchically($query)
                : 'published_at', $type->hierarchical ? 'asc' : 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('shopper-cms::posts.columns.title'))
                    ->formatStateUsing(fn (Post $record): string => $this->displayTitle($record))
                    ->description(fn (Post $record): string => '/'.($record->metadata[TrashPostAction::DESIRED_SLUG_KEY] ?? $record->slug))
                    ->url(fn (Post $record): ?string => ! $record->trashed() && $this->canEdit($record)
                        ? $this->editUrl($record)
                        : null)
                    ->weight('medium')
                    ->wrap()
                    ->searchable(['title', 'content'])
                    ->sortable(),
                TextColumn::make('author.full_name')
                    ->label(__('shopper-cms::posts.columns.author'))
                    ->placeholder('—'),
                TextColumn::make('categories.name')
                    ->label(__('shopper-cms::posts.columns.categories'))
                    ->badge()
                    ->visible($type->supports('categories')),
                TextColumn::make('tags.name')
                    ->label(__('shopper-cms::posts.columns.tags'))
                    ->badge()
                    ->color('gray')
                    ->limitList(3)
                    ->wrap()
                    ->visible($type->supports('tags'))
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label(__('shopper-cms::posts.columns.date'))
                    ->state(fn (Post $record): CarbonInterface => $record->published_at ?? $record->updated_at)
                    ->dateTime()
                    ->description(fn (Post $record): string => $this->dateLabel($record), position: 'above')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label(__('shopper-cms::posts.filters.month'))
                    ->placeholder(__('shopper-cms::posts.filters.all_dates'))
                    ->options(fn (): array => $this->monthOptions)
                    ->query(fn (Builder $query, array $data): Builder => $this->filterByMonth($query, $data['value'] ?? null)),
                SelectFilter::make('category')
                    ->label(__('shopper-cms::posts.filters.category'))
                    ->relationship('categories', 'name')
                    ->visible($type->supports('categories')),
                SelectFilter::make('author_id')
                    ->label(__('shopper-cms::posts.filters.author'))
                    ->options(fn (): array => $this->authorOptions),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('shopper::forms.actions.edit'))
                    ->icon(Untitledui::Edit03)
                    ->iconButton()
                    ->url(fn (Post $record): string => $this->editUrl($record))
                    ->visible(fn (Post $record): bool => ! $record->trashed() && $this->canEdit($record)),
                Action::make('quickEdit')
                    ->label(__('shopper-cms::posts.actions.quick_edit'))
                    ->icon(Untitledui::Zap)
                    ->iconButton()
                    ->modalHeading(__('shopper-cms::posts.actions.quick_edit'))
                    ->modalWidth(Width::TwoExtraLarge)
                    ->fillForm(fn (Post $record): array => $this->quickEditState($record))
                    ->schema(fn (Schema $schema, Post $record): Schema => $this->quickEditForm($schema, $record))
                    ->action(function (Post $record, array $data): void {
                        $this->saveQuickEdit($record, $data);

                        Notification::make()
                            ->title(__('shopper-cms::posts.notifications.updated_single'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Post $record): bool => ! $record->trashed() && $this->canEdit($record)),
                Action::make('trash')
                    ->label(__('shopper-cms::posts.actions.trash'))
                    ->icon(Untitledui::Trash03)
                    ->iconButton()
                    ->color('danger')
                    ->action(fn (Post $record) => $this->runOn(collect([$record]), 'trash'))
                    ->visible(fn (Post $record): bool => ! $record->trashed() && $this->canDelete($record)),
                Action::make('restore')
                    ->label(__('shopper-cms::posts.actions.restore'))
                    ->icon(Untitledui::ReverseLeft)
                    ->iconButton()
                    ->action(fn (Post $record) => $this->runOn(collect([$record]), 'restore'))
                    ->visible(fn (Post $record): bool => $record->trashed() && $this->canDelete($record)),
                Action::make('forceDelete')
                    ->label(__('shopper-cms::posts.actions.delete_permanently'))
                    ->icon(Untitledui::Trash03)
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalIcon(Untitledui::Trash03)
                    ->action(fn (Post $record) => $this->runOn(collect([$record]), 'forceDelete'))
                    ->visible(fn (Post $record): bool => $record->trashed() && $this->canDelete($record)),
            ])
            ->groupedBulkActions([
                BulkAction::make('bulkEdit')
                    ->label(__('shopper-cms::posts.actions.bulk_edit'))
                    ->icon(Untitledui::Edit03)
                    ->modalWidth(Width::Large)
                    ->schema(fn (Schema $schema): Schema => $this->bulkEditForm($schema))
                    ->action(fn (Collection $records, array $data) => $this->bulkEdit($records, $data))
                    ->visible(fn (): bool => $this->activeTab !== 'trash')
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('trash')
                    ->label(__('shopper-cms::posts.actions.trash'))
                    ->icon(Untitledui::Trash03)
                    ->color('danger')
                    ->action(fn (Collection $records) => $this->runOn($records, 'trash'))
                    ->visible(fn (): bool => $this->activeTab !== 'trash')
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('restore')
                    ->label(__('shopper-cms::posts.actions.restore'))
                    ->icon(Untitledui::ReverseLeft)
                    ->action(fn (Collection $records) => $this->runOn($records, 'restore'))
                    ->visible(fn (): bool => $this->activeTab === 'trash')
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('forceDelete')
                    ->label(__('shopper-cms::posts.actions.delete_permanently'))
                    ->icon(Untitledui::Trash03)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->runOn($records, 'forceDelete'))
                    ->visible(fn (): bool => $this->activeTab === 'trash')
                    ->deselectRecordsAfterCompletion(),
                ...SeoBulkActions::make(),
            ]);
    }

    public function createAction(): Action
    {
        $type = $this->postType();

        return Action::make('create')
            ->label(__('shopper-cms::posts.add_new'))
            ->url(route('shopper.cms.'.Str::plural($type->key).'.create'))
            ->visible(PostPermissions::canCreate(shopper()->auth()->user(), $type));
    }

    public function emptyTrashAction(): Action
    {
        return Action::make('emptyTrash')
            ->label(__('shopper-cms::posts.actions.empty_trash'))
            ->color('danger')
            ->icon(Untitledui::Trash03)
            ->requiresConfirmation()
            ->modalDescription(__('shopper-cms::posts.actions.empty_trash_confirm'))
            ->action(fn () => $this->runOn(
                Post::configuredClass()::query()->onlyTrashed()->ofType($this->typeKey())->get(),
                'forceDelete',
            ))
            ->visible(fn (): bool => $this->activeTab === 'trash');
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function monthOptions(): array
    {
        return Post::configuredClass()::query()
            ->withTrashed()
            ->ofType($this->typeKey())
            ->get(['published_at', 'created_at'])
            ->map(fn (Post $post): CarbonInterface => $post->published_at ?? $post->created_at)
            ->sortDesc()
            ->mapWithKeys(fn (CarbonInterface $date): array => [$date->format('Y-m') => $date->translatedFormat('F Y')])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function authorOptions(): array
    {
        return Post::configuredClass()::query()
            ->withTrashed()
            ->ofType($this->typeKey())
            ->whereNotNull('author_id')
            ->with('author')
            ->get()
            ->pluck('author')
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn ($author): array => [$author->id => $author->full_name ?? $author->email])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function depths(): array
    {
        return PostHierarchy::depths($this->typeKey());
    }

    public function render(): View
    {
        return view('shopper-cms::livewire.pages.posts.index', [
            'postType' => $this->postType(),
        ])->title($this->postType()->getLabel());
    }

    /**
     * Children right under their parent, like WordPress's page list.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    protected function orderHierarchically(Builder $query): Builder
    {
        $ids = PostHierarchy::orderedIds($this->typeKey());

        if ($ids === []) {
            return $query;
        }

        $cases = collect($ids)->map(fn (int $id, int $position): string => "WHEN {$id} THEN {$position}")->implode(' ');

        return $query->orderByRaw('CASE '.shopper_table('cms_posts').".id {$cases} ELSE ".count($ids).' END');
    }

    protected function canEdit(Post $post): bool
    {
        return PostPermissions::canEdit(shopper()->auth()->user(), $post);
    }

    protected function canDelete(Post $post): bool
    {
        return PostPermissions::canDelete(shopper()->auth()->user(), $post);
    }

    protected function editUrl(Post $post): string
    {
        return route('shopper.cms.'.Str::plural($post->type).'.edit', $post);
    }

    protected function displayTitle(Post $post): string
    {
        $depth = $this->postType()->hierarchical ? ($this->depths[$post->id] ?? 0) : 0;
        $title = str_repeat('— ', $depth).($post->title !== '' ? $post->title : __('shopper-cms::posts.no_title'));

        $states = [];

        if ($post->status !== PostStatus::Published) {
            $states[] = $post->status->getLabel();
        }

        if ($post->isPasswordProtected()) {
            $states[] = __('shopper-cms::enum/post.visibility.password');
        }

        return $states === [] ? $title : $title.' — '.implode(', ', $states);
    }

    protected function dateLabel(Post $post): string
    {
        return match (true) {
            $post->status === PostStatus::Scheduled => __('shopper-cms::posts.date_labels.scheduled'),
            $post->published_at !== null && in_array($post->status, [PostStatus::Published, PostStatus::Private], true) => __('shopper-cms::posts.date_labels.published'),
            default => __('shopper-cms::posts.date_labels.modified'),
        };
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    protected function filterByMonth(Builder $query, ?string $month): Builder
    {
        if (blank($month)) {
            return $query;
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $query->where(fn (Builder $query): Builder => $query
            ->whereBetween('published_at', [$start, $end])
            ->orWhere(fn (Builder $query): Builder => $query->whereNull('published_at')->whereBetween('created_at', [$start, $end])));
    }

    /**
     * Status choices like WordPress's Quick Edit: users who cannot publish only get Draft and Pending.
     * "Scheduled" is not offered: publishing with a future date schedules.
     *
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        $statuses = PostPermissions::canPublish(shopper()->auth()->user(), $this->postType())
            ? [PostStatus::Published, PostStatus::Pending, PostStatus::Draft, PostStatus::Private]
            : [PostStatus::Pending, PostStatus::Draft];

        return collect($statuses)->mapWithKeys(fn (PostStatus $status): array => [$status->value => $status->getLabel()])->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function quickEditState(Post $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'published_at' => $post->published_at,
            'status' => ($post->status === PostStatus::Scheduled ? PostStatus::Published : $post->status)->value,
            'categories' => $post->categories->pluck('id')->all(),
            'tags' => $post->tags->pluck('name')->all(),
            'parent_id' => $post->parent_id,
            'menu_order' => $post->menu_order,
        ];
    }

    protected function quickEditForm(Schema $schema, Post $post): Schema
    {
        $type = $this->postType();

        return $schema->components([
            Grid::make()->schema([
                TextInput::make('title')
                    ->label(__('shopper-cms::posts.form.title'))
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label(__('shopper-cms::posts.form.slug'))
                    ->maxLength(255),
                DateTimePicker::make('published_at')
                    ->label(__('shopper-cms::posts.form.published_at'))
                    ->native(false),
                Select::make('status')
                    ->label(__('shopper-cms::posts.form.status'))
                    ->options($this->statusOptions())
                    ->required(),
                CheckboxList::make('categories')
                    ->label(__('shopper-cms::posts.form.categories'))
                    ->options(fn (): array => PostHierarchy::categoryOptions())
                    ->visible($type->supports('categories')),
                TagsInput::make('tags')
                    ->label(__('shopper-cms::posts.form.tags'))
                    ->visible($type->supports('tags')),
                Select::make('parent_id')
                    ->label(__('shopper-cms::posts.form.parent'))
                    ->placeholder(__('shopper-cms::posts.form.no_parent'))
                    ->options(fn (): array => PostHierarchy::parentOptions($type->key, $post->id))
                    ->visible($type->hierarchical),
                TextInput::make('menu_order')
                    ->label(__('shopper-cms::posts.form.menu_order'))
                    ->integer()
                    ->visible($type->hierarchical),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveQuickEdit(Post $post, array $data): void
    {
        abort_unless($this->canEdit($post), 403);

        $status = PostStatus::from($data['status']);

        abort_unless(array_key_exists($status->value, $this->statusOptions()), 403);

        $post = resolve(SavePostAction::class)->execute([
            'title' => $data['title'] ?? $post->title,
            'slug' => $data['slug'] ?? null,
            'status' => $status,
            'published_at' => $data['published_at'] ?? null,
            ...$this->postType()->hierarchical ? [
                'parent_id' => filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                'menu_order' => (int) ($data['menu_order'] ?? 0),
            ] : [],
        ], $post);

        if ($this->postType()->supports('categories') || $this->postType()->supports('tags')) {
            resolve(SyncPostTermsAction::class)->execute($post, $data['categories'] ?? [], $data['tags'] ?? []);
        }
    }

    protected function bulkEditForm(Schema $schema): Schema
    {
        $type = $this->postType();

        return $schema->components([
            Select::make('status')
                ->label(__('shopper-cms::posts.form.status'))
                ->placeholder(__('shopper-cms::posts.actions.no_change'))
                ->options($this->statusOptions()),
            Select::make('author_id')
                ->label(__('shopper-cms::posts.form.author'))
                ->placeholder(__('shopper-cms::posts.actions.no_change'))
                ->options(fn (): array => $this->authorOptions)
                ->visible(shopper()->auth()->user()?->can($type->permission('edit', others: true))),
            CheckboxList::make('categories')
                ->label(__('shopper-cms::posts.form.categories'))
                ->options(fn (): array => PostHierarchy::categoryOptions())
                ->visible($type->supports('categories')),
        ]);
    }

    /**
     * WordPress's bulk edit only adds categories; it never removes existing ones.
     *
     * @param  Collection<int, Post>  $records
     * @param  array<string, mixed>  $data
     */
    protected function bulkEdit(Collection $records, array $data): void
    {
        $updated = 0;
        $skipped = 0;
        $status = filled($data['status'] ?? null) ? PostStatus::from($data['status']) : null;

        abort_if($status !== null && ! array_key_exists($status->value, $this->statusOptions()), 403);

        foreach ($records as $post) {
            if (! $this->canEdit($post)) {
                $skipped++;

                continue;
            }

            try {
                $post = resolve(SavePostAction::class)->execute(array_filter([
                    'status' => $status,
                    'author_id' => filled($data['author_id'] ?? null) ? (int) $data['author_id'] : null,
                ]), $post);
            } catch (ValidationException) {
                $skipped++;

                continue;
            }

            if (filled($data['categories'] ?? null) && $this->postType()->supports('categories')) {
                resolve(SyncPostTermsAction::class)->execute(
                    $post,
                    $post->categories()->pluck('id')->merge($data['categories'])->all(),
                    $post->tags()->pluck('id')->all(),
                );
            }

            $updated++;
        }

        $this->notifyResult('updated', $updated, $skipped);
    }

    /**
     * @param  iterable<\Illuminate\Database\Eloquent\Model>  $records
     */
    protected function runOn(iterable $records, string $operation): void
    {
        $done = 0;
        $skipped = 0;

        foreach ($records as $post) {
            if (! $post instanceof Post || ! $this->canDelete($post)) {
                $skipped++;

                continue;
            }

            match ($operation) {
                'trash' => resolve(TrashPostAction::class)->execute($post),
                'restore' => resolve(RestorePostAction::class)->execute($post),
                'forceDelete' => resolve(ForceDeletePostAction::class)->execute($post),
                default => throw new InvalidArgumentException("Unknown operation [{$operation}]."),
            };

            $done++;
        }

        $this->notifyResult(match ($operation) {
            'trash' => 'trashed',
            'restore' => 'restored',
            default => 'deleted',
        }, $done, $skipped);
    }

    protected function notifyResult(string $key, int $done, int $skipped): void
    {
        Notification::make()
            ->title(trans_choice("shopper-cms::posts.notifications.{$key}", $done, ['count' => $done]))
            ->body($skipped > 0 ? trans_choice('shopper-cms::posts.notifications.skipped', $skipped, ['count' => $skipped]) : null)
            ->status($skipped > 0 ? 'warning' : 'success')
            ->send();
    }
}
