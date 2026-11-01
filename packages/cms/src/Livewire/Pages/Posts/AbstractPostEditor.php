<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Posts;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Cms\Actions\AutosavePostAction;
use Shopper\Cms\Actions\GeneratePreviewUrlAction;
use Shopper\Cms\Actions\RestorePostAction;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Actions\TrashPostAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Enums\PostVisibility;
use Shopper\Cms\Forms\Components\TinyEditor;
use Shopper\Cms\Livewire\Concerns\InteractsWithEditorMedia;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostRevision;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Permalinks\PermalinkGenerator;
use Shopper\Cms\PostTypes\PostType;
use Shopper\Cms\PostTypes\PostTypeRegistry;
use Shopper\Cms\Support\PostHierarchy;
use Shopper\Cms\Support\PostPermissions;
use Shopper\Components\Form\UploadPicker;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Seo\Filament\SeoPanel;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\SeoFields;
use Shopper\Seo\Support\TemplateRenderer;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * WordPress's "Add New" / "Edit" screen, shared by every post type.
 *
 * @property-read Schema $form
 */
abstract class AbstractPostEditor extends AbstractPageComponent implements HasActions, HasSchemas
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithEditorMedia;
    use InteractsWithSchemas;

    public ?Post $post = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    abstract public function typeKey(): string;

    public function mount(?Post $post = null): void
    {
        $type = $this->postType();

        if ($post?->exists) {
            abort_unless($post->type === $type->key, 404);
            abort_unless(PostPermissions::canEdit(shopper()->auth()->user(), $post), 403);

            $this->post = $post->load(['categories', 'tags']);
        } else {
            abort_unless(PostPermissions::canCreate(shopper()->auth()->user(), $type), 403);
        }

        $this->form->fill($this->formState());
    }

    /**
     * Called by the editor's timer while there are unsaved changes. Only existing content is autosaved.
     */
    public function autosave(): void
    {
        if ($this->post === null || $this->post->trashed() || ! config('shopper.cms.revisions.enabled', true)) {
            return;
        }

        abort_unless(PostPermissions::canEdit(shopper()->auth()->user(), $this->post), 403);

        resolve(AutosavePostAction::class)->execute(
            $this->post,
            [
                'title' => $this->data['title'] ?? null,
                'content' => $this->data['content'] ?? null,
                'excerpt' => $this->data['excerpt'] ?? null,
            ],
            shopper()->auth()->id(),
        );
    }

    /**
     * The current user's autosave when it is newer than the saved post and differs from it
     * (WordPress: "There is an autosave of this post that is more recent than the version below").
     */
    public function newerAutosave(): ?PostRevision
    {
        if ($this->post === null) {
            return null;
        }

        /** @var ?PostRevision $autosave */
        $autosave = $this->post->autosaves()->where('user_id', shopper()->auth()->id())->first();

        if ($autosave === null || $autosave->updated_at->lte($this->post->updated_at)) {
            return null;
        }

        $differs = $autosave->title !== $this->post->title
            || $autosave->content !== $this->post->content
            || $autosave->excerpt !== $this->post->excerpt;

        return $differs ? $autosave : null;
    }

    public function postType(): PostType
    {
        return resolve(PostTypeRegistry::class)->get($this->typeKey());
    }

    public function form(Schema $schema): Schema
    {
        $type = $this->postType();

        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => (bool) $this->post?->trashed())
            ->components([
                Grid::make(['lg' => 3])->schema([
                    Group::make()
                        ->columnSpan(['lg' => 2])
                        ->schema([
                            Section::make()->schema([
                                TextInput::make('title')
                                    ->label(__('shopper-cms::posts.form.title'))
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                        if ($this->post?->exists || $get('slug_edited')) {
                                            return;
                                        }

                                        $set('slug', Str::slug((string) $state));
                                    }),
                                TextInput::make('slug')
                                    ->label(__('shopper-cms::posts.form.slug'))
                                    ->helperText(__('shopper-cms::posts.form.slug_help'))
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set) => $set('slug_edited', true)),
                                TinyEditor::make('content')
                                    ->label(__('shopper-cms::posts.form.content'))
                                    ->mediaAction('insertMedia')
                                    ->uploadUrl(fn (): ?string => shopper()->auth()->user()?->can('add_uploads')
                                        ? route('shopper.cms.editor.upload')
                                        : null),
                                Textarea::make('excerpt')
                                    ->label(__('shopper-cms::posts.form.excerpt'))
                                    ->helperText(__('shopper-cms::posts.form.excerpt_help'))
                                    ->rows(3)
                                    ->visible($type->supports('excerpt')),
                            ]),
                            SeoPanel::make(preview: fn (): array => $this->seoPreview()),
                        ]),
                    Group::make()
                        ->columnSpan(['lg' => 1])
                        ->schema([
                            $this->publishSection(),
                            $this->taxonomySection(),
                            $this->pageAttributesSection(),
                            Section::make(__('shopper-cms::posts.form.featured_image'))
                                ->schema([
                                    UploadPicker::make('featured_upload_id')
                                        ->label(__('shopper-cms::posts.form.featured_image'))
                                        ->hiddenLabel()
                                        ->helperText(__('shopper-cms::posts.form.featured_image_help')),
                                ])
                                ->visible($type->supports('featured_image')),
                        ]),
                ]),
            ]);
    }

    public function saveDraftAction(): Action
    {
        return Action::make('saveDraft')
            ->label(fn (): string => ($this->data['status'] ?? null) === PostStatus::Pending->value
                ? __('shopper-cms::posts.actions.save_as_pending')
                : __('shopper-cms::posts.actions.save_draft'))
            ->color('gray')
            ->action(fn () => $this->persist(
                ($this->data['status'] ?? null) === PostStatus::Pending->value ? PostStatus::Pending : PostStatus::Draft
            ))
            ->visible(fn (): bool => ! $this->isLive() && ! $this->post?->trashed());
    }

    public function publishAction(): Action
    {
        return Action::make('publish')
            ->label(fn (): string => $this->publishLabel())
            ->action(function (): void {
                if (! $this->canPublish()) {
                    $this->persist(PostStatus::Pending);

                    return;
                }

                $this->persist($this->isLive()
                    ? PostStatus::from($this->data['status'] ?? PostStatus::Published->value)
                    : PostStatus::Published);
            })
            ->visible(fn (): bool => ! $this->post?->trashed());
    }

    /**
     * Opens the saved version (save first to preview the latest changes; plan 05 adds autosave).
     */
    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('shopper-cms::posts.actions.preview'))
            ->color('gray')
            ->icon(Untitledui::LinkExternal01)
            ->url(fn (): ?string => $this->post?->exists ? resolve(GeneratePreviewUrlAction::class)->execute($this->post) : null)
            ->openUrlInNewTab()
            ->visible(fn (): bool => $this->post?->exists && ! $this->post->trashed());
    }

    public function trashAction(): Action
    {
        return Action::make('trash')
            ->label(__('shopper-cms::posts.actions.trash'))
            ->color('danger')
            ->link()
            ->action(function (): void {
                abort_unless($this->post !== null && PostPermissions::canDelete(shopper()->auth()->user(), $this->post), 403);

                resolve(TrashPostAction::class)->execute($this->post);

                Notification::make()
                    ->title(trans_choice('shopper-cms::posts.notifications.trashed', 1, ['count' => 1]))
                    ->success()
                    ->send();

                $this->redirect($this->indexUrl(), navigate: true);
            })
            ->visible(fn (): bool => $this->post?->exists
                && ! $this->post->trashed()
                && PostPermissions::canDelete(shopper()->auth()->user(), $this->post));
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('shopper-cms::posts.actions.restore'))
            ->icon(Untitledui::ReverseLeft)
            ->action(function (): void {
                abort_unless($this->post !== null && PostPermissions::canDelete(shopper()->auth()->user(), $this->post), 403);

                $this->post = resolve(RestorePostAction::class)->execute($this->post);
                $this->form->fill($this->formState());

                Notification::make()
                    ->title(trans_choice('shopper-cms::posts.notifications.restored', 1, ['count' => 1]))
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => (bool) $this->post?->trashed());
    }

    public function render(): View
    {
        $type = $this->postType();

        return view('shopper-cms::livewire.pages.posts.edit', [
            'postType' => $type,
            'indexUrl' => $this->indexUrl(),
            'autosave' => $this->newerAutosave(),
            'autosaveInterval' => $this->post?->exists && $type->supports('revisions')
                ? (int) config('shopper.cms.revisions.autosave_interval', 60)
                : 0,
        ])->title(__($this->post?->exists
            ? "shopper-cms::posts.edit_{$type->key}"
            : "shopper-cms::posts.add_new_{$type->key}"));
    }

    protected function publishSection(): Section
    {
        return Section::make(__('shopper-cms::posts.form.publish'))
            ->schema([
                Select::make('status')
                    ->label(__('shopper-cms::posts.form.status'))
                    ->options(fn (): array => $this->statusOptions())
                    ->selectablePlaceholder(false)
                    ->live(),
                Radio::make('visibility')
                    ->label(__('shopper-cms::posts.form.visibility'))
                    ->options(fn (): array => collect(PostVisibility::cases())
                        ->reject(fn (PostVisibility $visibility): bool => $visibility === PostVisibility::Private && ! $this->canPublish())
                        ->mapWithKeys(fn (PostVisibility $visibility): array => [$visibility->value => $visibility->getLabel()])
                        ->all())
                    ->live(),
                TextInput::make('password')
                    ->label(__('shopper-cms::posts.form.password'))
                    ->password()
                    ->revealable()
                    ->helperText(fn (): ?string => $this->post?->isPasswordProtected() ? __('shopper-cms::posts.form.password_help') : null)
                    ->required(fn (): bool => ! $this->post?->isPasswordProtected())
                    ->visible(fn (Get $get): bool => $get('visibility') === PostVisibility::Password->value),
                DateTimePicker::make('published_at')
                    ->label(__('shopper-cms::posts.form.published_at'))
                    ->helperText(__('shopper-cms::posts.form.published_at_help'))
                    ->native(false)
                    ->live(),
                Placeholder::make('revisions')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(sprintf(
                        '%s · <a href="%s" wire:navigate class="font-medium text-primary-600 hover:underline dark:text-primary-400">%s</a>',
                        e(__('shopper-cms::revisions.label', ['count' => $this->post?->revisions()->count() ?? 0])),
                        e(route('shopper.cms.'.Str::plural($this->typeKey()).'.revisions', $this->post)),
                        e(__('shopper-cms::revisions.browse')),
                    )))
                    ->visible(fn (): bool => $this->post?->exists && $this->postType()->supports('revisions') && $this->post->revisions()->count() > 1),
                Placeholder::make('last_edited')
                    ->hiddenLabel()
                    ->content(fn (): string => __('shopper-cms::posts.form.last_edited', [
                        'name' => $this->post?->author?->getAttribute('full_name') ?? '—',
                        'date' => $this->post?->updated_at?->translatedFormat('j F Y, H:i'),
                    ]))
                    ->visible(fn (): bool => (bool) $this->post?->exists),
            ]);
    }

    protected function taxonomySection(): Section
    {
        $type = $this->postType();

        return Section::make(__('shopper-cms::posts.form.categories'))
            ->schema([
                CheckboxList::make('categories')
                    ->hiddenLabel()
                    ->options(fn (): array => PostHierarchy::categoryOptions())
                    ->live()
                    ->visible($type->supports('categories')),
                Select::make('primary_category_id')
                    ->label(__('shopper-cms::posts.form.primary_category'))
                    ->helperText(__('shopper-cms::posts.form.primary_category_help'))
                    ->options(fn (Get $get): array => PostHierarchy::categoryNames($get('categories') ?? []))
                    ->visible(fn (Get $get): bool => $type->supports('categories') && count($get('categories') ?? []) > 1),
                TagsInput::make('tags')
                    ->label(__('shopper-cms::posts.form.tags'))
                    ->helperText(__('shopper-cms::posts.form.tags_help'))
                    ->suggestions(fn (): array => PostTag::query()->orderBy('name')->pluck('name')->all())
                    ->visible($type->supports('tags')),
            ])
            ->visible($type->supports('categories') || $type->supports('tags'));
    }

    protected function pageAttributesSection(): Section
    {
        $type = $this->postType();

        return Section::make(__('shopper-cms::posts.form.page_attributes'))
            ->schema([
                Select::make('parent_id')
                    ->label(__('shopper-cms::posts.form.parent'))
                    ->placeholder(__('shopper-cms::posts.form.no_parent'))
                    ->options(fn (): array => PostHierarchy::parentOptions($type->key, $this->post?->id))
                    ->searchable()
                    ->visible($type->hierarchical),
                Select::make('template')
                    ->label(__('shopper-cms::posts.form.template'))
                    ->options(fn (): array => collect(config('shopper.cms.templates', []))->map(fn (string $label): string => __($label))->all())
                    ->selectablePlaceholder(false),
                TextInput::make('menu_order')
                    ->label(__('shopper-cms::posts.form.menu_order'))
                    ->integer()
                    ->visible($type->hierarchical),
            ])
            ->visible($type->supports('page_attributes'));
    }

    /**
     * Like WordPress's status dropdown: Draft and Pending, plus Published once the content is live.
     * Private is chosen through the visibility, and a future date schedules.
     *
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        $statuses = $this->isLive()
            ? [PostStatus::Published, PostStatus::Pending, PostStatus::Draft]
            : [PostStatus::Draft, PostStatus::Pending];

        return collect($statuses)->mapWithKeys(fn (PostStatus $status): array => [$status->value => $status->getLabel()])->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formState(): array
    {
        $post = $this->post;

        if ($post === null) {
            return [
                'seo' => ['robots_index' => 'default', 'robots_follow' => 'default'],
                'status' => PostStatus::Draft->value,
                'visibility' => PostVisibility::Public->value,
                'template' => array_key_first(config('shopper.cms.templates', [])),
                'menu_order' => 0,
                'categories' => [],
                'tags' => [],
            ];
        }

        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
            'status' => ($this->isLive() ? PostStatus::Published : $post->status)->value,
            'visibility' => $post->visibility()->value,
            'published_at' => $post->published_at,
            'parent_id' => $post->parent_id,
            'template' => $post->template ?? array_key_first(config('shopper.cms.templates', [])),
            'menu_order' => $post->menu_order,
            'featured_upload_id' => $post->featured_upload_id,
            'categories' => $post->categories->pluck('id')->all(),
            'primary_category_id' => $post->primary_category_id,
            'tags' => $post->tags->pluck('name')->all(),
            'seo' => SeoFields::state($post),
        ];
    }

    /**
     * What search engines would show when the SEO fields are left empty (the type's templates).
     *
     * @return array{title: string, description: string, url: string}
     */
    protected function seoPreview(): array
    {
        $settings = resolve(SeoSettings::class);
        $renderer = resolve(TemplateRenderer::class);
        $type = $this->typeKey();
        $excerpt = filled($this->data['excerpt'] ?? null) ? (string) $this->data['excerpt'] : (string) ($this->data['content'] ?? '');
        $values = [
            'title' => (string) ($this->data['title'] ?? ''),
            'excerpt' => TemplateRenderer::excerpt($excerpt),
            'sitename' => (string) ($settings->get('general.site_name') ?: config('app.name')),
            'tagline' => (string) $settings->get('general.tagline'),
            'currentyear' => now()->format('Y'),
        ];
        $separator = (string) $settings->get('general.separator', '-');

        return [
            'title' => $renderer->render($settings->get("titles.{$type}.title"), $values, $separator),
            'description' => TemplateRenderer::excerpt($renderer->render($settings->get("titles.{$type}.description"), $values, $separator)),
            'url' => $this->post?->exists ? resolve(PermalinkGenerator::class)->url($this->post) : (string) config('app.url'),
        ];
    }

    protected function persist(PostStatus $status): void
    {
        $data = $this->form->getState();
        $type = $this->postType();
        $visibility = PostVisibility::from($data['visibility'] ?? PostVisibility::Public->value);

        $goesLive = $status === PostStatus::Published || $visibility === PostVisibility::Private;
        abort_if($goesLive && ! $this->canPublish(), 403);

        $isNew = $this->post === null;

        try {
            $post = resolve(SavePostAction::class)->execute([
                'type' => $type->key,
                'title' => (string) ($data['title'] ?? ''),
                'slug' => $data['slug'] ?? null,
                'content' => $data['content'] ?? null,
                'excerpt' => $data['excerpt'] ?? null,
                'status' => $status,
                'visibility' => $visibility,
                'published_at' => $data['published_at'] ?? null,
                'featured_upload_id' => $data['featured_upload_id'] ?? null,
                ...Arr::only($data, ['template']),
                ...array_filter(['password' => $data['password'] ?? null]),
                ...$type->hierarchical ? [
                    'parent_id' => filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                    'menu_order' => (int) ($data['menu_order'] ?? 0),
                ] : [],
                ...$isNew ? ['author_id' => shopper()->auth()->id()] : [],
            ], $this->post);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["data.{$key}" => $messages])->all()
            );
        }

        if ($type->supports('categories') || $type->supports('tags')) {
            resolve(SyncPostTermsAction::class)->execute(
                $post,
                $data['categories'] ?? [],
                $data['tags'] ?? [],
                filled($data['primary_category_id'] ?? null) ? (int) $data['primary_category_id'] : null,
            );
        }

        Notification::make()
            ->title($this->savedMessage($post))
            ->success()
            ->send();

        if (array_key_exists('seo', $data)) {
            SeoFields::save($post, (array) $data['seo'], (bool) shopper()->auth()->user()?->can('manage_seo_scripts'));
        }

        // A real save makes the user's autosave obsolete.
        $post->autosaves()->where('user_id', shopper()->auth()->id())->delete();

        $this->dispatch('cms-post-saved');

        if ($isNew) {
            $this->redirect($this->editUrl($post), navigate: true);

            return;
        }

        $this->post = $post->refresh()->load(['categories', 'tags']);
        $this->form->fill($this->formState());
    }

    protected function savedMessage(Post $post): string
    {
        return match ($post->status) {
            PostStatus::Draft => __('shopper-cms::posts.notifications.saved_draft'),
            PostStatus::Pending => __('shopper-cms::posts.notifications.submitted'),
            PostStatus::Scheduled => __('shopper-cms::posts.notifications.scheduled', [
                'date' => $post->published_at?->translatedFormat('j F Y, H:i'),
            ]),
            default => $this->post?->exists && $this->isLive()
                ? __('shopper-cms::posts.notifications.updated_single')
                : __('shopper-cms::posts.notifications.published'),
        };
    }

    protected function publishLabel(): string
    {
        if (! $this->canPublish()) {
            return __('shopper-cms::posts.actions.submit_for_review');
        }

        if ($this->isLive()) {
            return __('shopper-cms::posts.actions.update');
        }

        $publishedAt = $this->data['published_at'] ?? null;

        return filled($publishedAt) && Carbon::parse($publishedAt)->isFuture()
            ? __('shopper-cms::posts.actions.schedule')
            : __('shopper-cms::posts.actions.publish');
    }

    protected function isLive(): bool
    {
        return $this->post !== null
            && in_array($this->post->status, [PostStatus::Published, PostStatus::Scheduled, PostStatus::Private], true);
    }

    protected function canPublish(): bool
    {
        return PostPermissions::canPublish(shopper()->auth()->user(), $this->postType());
    }

    protected function editUrl(Post $post): string
    {
        return route('shopper.cms.'.Str::plural($post->type).'.edit', $post);
    }

    protected function indexUrl(): string
    {
        return route('shopper.cms.'.Str::plural($this->typeKey()).'.index');
    }
}
