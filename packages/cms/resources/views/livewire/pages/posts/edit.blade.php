<x-shopper::container class="py-5">
    {{-- Warn before leaving with unsaved changes. Only user navigation is guarded (link clicks and
         closing/reloading the tab), so the redirect after saving a new post never prompts. --}}
    <div
        x-data="{
            dirty: false,
            changedSinceAutosave: false,
            timer: null,
            message: @js(__('shopper-cms::posts.form.unsaved_changes')),
            init() {
                const interval = @js($autosaveInterval)

                if (interval > 0) {
                    this.timer = setInterval(() => {
                        if (this.changedSinceAutosave) {
                            this.changedSinceAutosave = false
                            $wire.autosave()
                        }
                    }, interval * 1000)

                    // wire:navigate does not always run destroy(): stop the timer on navigation too.
                    document.addEventListener('livewire:navigating', () => this.destroy(), { once: true })
                }
            },
            destroy() {
                clearInterval(this.timer)
            },
        }"
        x-on:input="dirty = true; changedSinceAutosave = true"
        x-on:change="dirty = true; changedSinceAutosave = true"
        x-on:cms-post-saved.window="dirty = false; changedSinceAutosave = false"
        x-on:beforeunload.window="if (dirty) $event.preventDefault()"
        x-on:click.capture.document="
            if (dirty && $event.target.closest('a[href]') && ! window.confirm(message)) {
                $event.preventDefault()
                $event.stopImmediatePropagation()
            }
        "
    >
        <x-shopper::breadcrumb :back="$indexUrl">
            <x-untitledui-chevron-left class="size-4 shrink-0 text-gray-300 dark:text-gray-600" />
            <x-shopper::breadcrumb.link :link="$indexUrl" :title="$postType->getLabel()" />
        </x-shopper::breadcrumb>

        <x-shopper::heading
            class="mt-5"
            :title="__($this->post?->exists ? 'shopper-cms::posts.edit_' . $postType->key : 'shopper-cms::posts.add_new_' . $postType->key)"
        >
            <x-slot name="action">
                <div class="flex items-center gap-3">
                    @foreach (['trashAction', 'restoreAction', 'previewAction', 'saveDraftAction', 'publishAction'] as $action)
                        @if ($this->{$action}->isVisible())
                            {{ $this->{$action} }}
                        @endif
                    @endforeach
                </div>
            </x-slot>
        </x-shopper::heading>

        @if ($autosave)
            <div
                class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-800 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/20"
            >
                <span>{{ __('shopper-cms::revisions.autosave.notice') }}</span>
                <a
                    href="{{ route('shopper.cms.' . \Illuminate\Support\Str::plural($postType->key) . '.revisions', ['post' => $this->post, 'autosave' => 1]) }}"
                    wire:navigate
                    class="font-medium underline"
                >
                    {{ __('shopper-cms::revisions.autosave.view') }}
                </a>
            </div>
        @endif

        @if ($this->post?->trashed())
            <div
                class="mt-6 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-danger-200 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/20"
            >
                {{ __('shopper-cms::posts.form.trashed_notice') }}
            </div>
        @endif

        <form wire:submit="mountAction('publish')" class="mt-8">
            {{ $this->form }}
        </form>
    </div>

    <x-filament-actions::modals />
</x-shopper::container>
