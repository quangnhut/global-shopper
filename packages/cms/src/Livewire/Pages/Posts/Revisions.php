<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Posts;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Cms\Actions\RestoreRevisionAction;
use Shopper\Cms\Enums\RevisionKind;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostRevision;
use Shopper\Cms\Support\PostPermissions;
use Shopper\Cms\Support\RevisionDiff;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * WordPress's revisions screen: browse the history of a post or page, compare two versions, restore one.
 *
 * @property-read Collection<int, PostRevision> $history
 * @property-read ?PostRevision $toRevision
 * @property-read ?PostRevision $fromRevision
 * @property-read ?PostRevision $autosave
 * @property-read array<string, string> $diffs
 */
class Revisions extends AbstractPageComponent implements HasActions, HasSchemas
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Post $post;

    #[Url(as: 'to')]
    public ?int $toId = null;

    #[Url(as: 'from')]
    public ?int $fromId = null;

    #[Url(as: 'compare')]
    public bool $compareTwo = false;

    /**
     * Compare the current version with the user's autosave ("View the autosave" in the editor).
     */
    #[Url(as: 'autosave')]
    public bool $showAutosave = false;

    public function mount(Post $post): void
    {
        abort_unless(PostPermissions::canEdit(shopper()->auth()->user(), $post), 403);

        $this->post = $post;
        $this->toId ??= $this->history->last()?->id;
    }

    /**
     * Oldest first, like the WordPress slider.
     *
     * @return Collection<int, PostRevision>
     */
    #[Computed]
    public function history(): Collection
    {
        return $this->post->revisions()->with('user')->reorder('id')->get();
    }

    #[Computed]
    public function toRevision(): ?PostRevision
    {
        if ($this->showAutosave && $this->autosave !== null) {
            return $this->autosave;
        }

        return $this->history->firstWhere('id', $this->toId) ?? $this->history->last();
    }

    #[Computed]
    public function autosave(): ?PostRevision
    {
        return $this->post->autosaves()->with('user')->where('user_id', shopper()->auth()->id())->first();
    }

    /**
     * In single mode, the revision just before `to`; in "compare any two" mode, the one chosen.
     */
    #[Computed]
    public function fromRevision(): ?PostRevision
    {
        if ($this->showAutosave && $this->autosave !== null) {
            return $this->history->last() ?? $this->savedVersion();
        }

        if ($this->compareTwo && $this->fromId !== null) {
            return $this->history->firstWhere('id', $this->fromId);
        }

        $position = $this->history->search(fn (PostRevision $revision): bool => $revision->id === $this->toRevision?->id);

        return $position > 0 ? $this->history->get($position - 1) : null;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function diffs(): array
    {
        return $this->toRevision === null ? [] : resolve(RevisionDiff::class)->compare($this->fromRevision, $this->toRevision);
    }

    public function updatedCompareTwo(bool $compareTwo): void
    {
        if ($compareTwo && $this->fromId === null) {
            $this->fromId = $this->fromRevision->id ?? $this->history->first()?->id;
        }
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('shopper-cms::revisions.restore'))
            ->icon(Untitledui::ReverseLeft)
            ->requiresConfirmation()
            ->modalDescription(__('shopper-cms::revisions.restore_confirm'))
            ->action(function (): void {
                abort_unless(PostPermissions::canEdit(shopper()->auth()->user(), $this->post), 403);

                $revision = $this->toRevision;

                abort_if($revision === null, 404);

                resolve(RestoreRevisionAction::class)->execute($revision);

                if ($revision->kind === RevisionKind::Autosave) {
                    $revision->delete();
                }

                Notification::make()
                    ->title(__('shopper-cms::revisions.restored', ['date' => $revision->created_at->translatedFormat('j F Y, H:i')]))
                    ->success()
                    ->send();

                $this->redirect($this->editUrl(), navigate: true);
            })
            ->visible(fn (): bool => $this->toRevision !== null
                && ($this->toRevision->kind === RevisionKind::Autosave || $this->toRevision->id !== $this->history->last()?->id));
    }

    public function editUrl(): string
    {
        return route('shopper.cms.'.Str::plural($this->post->type).'.edit', $this->post);
    }

    public function render(): View
    {
        return view('shopper-cms::livewire.pages.posts.revisions')
            ->title(__('shopper-cms::revisions.title', ['title' => $this->post->title]));
    }

    /**
     * The post as saved, for content that has no revisions yet (saved before revisions existed).
     */
    protected function savedVersion(): PostRevision
    {
        $version = new PostRevision([
            'title' => $this->post->title,
            'content' => $this->post->content,
            'excerpt' => $this->post->excerpt,
        ]);

        $version->setAttribute('created_at', $this->post->updated_at);
        $version->setRelation('user', $this->post->author);

        return $version;
    }
}
