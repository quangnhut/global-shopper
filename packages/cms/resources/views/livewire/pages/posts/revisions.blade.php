@php
    $history = $this->history;
    $to = $this->toRevision;
    $from = $this->fromRevision;
    $toIndex = $to ? max(0, (int) $history->search(fn ($revision) => $revision->id === $to->id)) : 0;
@endphp

<x-shopper::container class="py-5">
    <x-shopper::breadcrumb :back="$this->editUrl()">
        <x-untitledui-chevron-left class="size-4 shrink-0 text-gray-300 dark:text-gray-600" />
        <x-shopper::breadcrumb.link :link="$this->editUrl()" :title="$post->title" />
    </x-shopper::breadcrumb>

    <x-shopper::heading class="mt-5" :title="__('shopper-cms::revisions.title', ['title' => $post->title])">
        <x-slot name="action">
            @if ($this->restoreAction->isVisible())
                {{ $this->restoreAction }}
            @endif
        </x-slot>
    </x-shopper::heading>

    @if ($history->isEmpty() && ! ($this->showAutosave && $this->autosave))
        <p class="mt-8 text-sm text-gray-500 dark:text-gray-400">{{ __('shopper-cms::revisions.none') }}</p>
    @else
        <div class="mt-8 space-y-6">
            <div class="flex flex-col gap-4 rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="compareTwo" class="rounded border-gray-300 dark:border-white/20" />
                    {{ __('shopper-cms::revisions.compare_two') }}
                </label>

                @if ($this->showAutosave && $this->autosave)
                    <x-filament::badge color="warning" class="w-fit">{{ __('shopper-cms::revisions.autosave.badge') }}</x-filament::badge>
                @elseif ($this->compareTwo)
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['fromId' => __('shopper-cms::revisions.from'), 'toId' => __('shopper-cms::revisions.to')] as $property => $label)
                            <label class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $label }}
                                <select wire:model.live="{{ $property }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                                    @foreach ($history as $revision)
                                        <option value="{{ $revision->id }}">
                                            {{ $revision->created_at->translatedFormat('j F Y, H:i:s') }} · {{ $revision->user?->full_name ?? '—' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div>
                        <input
                            type="range"
                            min="0"
                            max="{{ $history->count() - 1 }}"
                            value="{{ $toIndex }}"
                            aria-label="{{ __('shopper-cms::revisions.slider') }}"
                            x-on:change="$wire.set('toId', @js($history->pluck('id')->values())[$event.target.value])"
                            class="w-full accent-primary-600"
                        />
                        <div class="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ __('shopper-cms::revisions.oldest') }}</span>
                            <span>{{ trans_choice('shopper-cms::revisions.count', $history->count(), ['count' => $history->count()]) }}</span>
                            <span>{{ __('shopper-cms::revisions.newest') }}</span>
                        </div>
                    </div>
                @endif

                <div class="grid gap-4 text-sm sm:grid-cols-2">
                    @foreach (['from' => $from, 'to' => $to] as $side => $revision)
                        <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ __("shopper-cms::revisions.{$side}") }}
                            </p>
                            @if ($revision)
                                <p class="mt-1 font-medium text-gray-900 dark:text-white">
                                    {{ $revision->user?->full_name ?? '—' }}
                                    <span class="font-normal text-gray-500 dark:text-gray-400">
                                        · {{ $revision->created_at->diffForHumans() }} ({{ $revision->created_at->translatedFormat('j F Y, H:i') }})
                                    </span>
                                </p>
                                @if ($side === 'to' && $revision->id === $history->last()?->id)
                                    <x-filament::badge class="mt-2 w-fit" color="success">{{ __('shopper-cms::revisions.current') }}</x-filament::badge>
                                @endif
                            @else
                                <p class="mt-1 text-gray-500 dark:text-gray-400">{{ __('shopper-cms::revisions.first_version') }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Own styles instead of the diff library's stylesheet, which is unreadable in dark mode. --}}
            <style>
                .cms-revision-diff { width: 100%; table-layout: fixed; border-collapse: collapse; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.8rem; line-height: 1.6; }
                .cms-revision-diff td { padding: 0.25rem 0.75rem; white-space: pre-wrap; word-break: break-word; vertical-align: top; color: rgb(55 65 81); }
                .cms-revision-diff td.none { background: rgb(249 250 251); }
                .cms-revision-diff .change-rep td.old, .cms-revision-diff .change-del td.old { background: rgb(254 242 242); }
                .cms-revision-diff .change-rep td.new, .cms-revision-diff .change-ins td.new { background: rgb(240 253 244); }
                .cms-revision-diff del { background: rgb(254 202 202); color: rgb(153 27 27); text-decoration: line-through; }
                .cms-revision-diff ins { background: rgb(187 247 208); color: rgb(22 101 52); text-decoration: none; }
                .dark .cms-revision-diff td { color: rgb(209 213 219); }
                .dark .cms-revision-diff td.none { background: rgb(255 255 255 / 0.03); }
                .dark .cms-revision-diff .change-rep td.old, .dark .cms-revision-diff .change-del td.old { background: rgb(239 68 68 / 0.12); }
                .dark .cms-revision-diff .change-rep td.new, .dark .cms-revision-diff .change-ins td.new { background: rgb(34 197 94 / 0.12); }
                .dark .cms-revision-diff del { background: rgb(239 68 68 / 0.4); color: rgb(254 226 226); }
                .dark .cms-revision-diff ins { background: rgb(34 197 94 / 0.4); color: rgb(220 252 231); }
            </style>

            @forelse ($this->diffs as $field => $diff)
                <section class="rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{{ __("shopper-cms::revisions.fields.{$field}") }}</h3>
                    <div class="overflow-x-auto">{!! $diff !!}</div>
                </section>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('shopper-cms::revisions.identical') }}</p>
            @endforelse
        </div>
    @endif

    <x-filament-actions::modals />
</x-shopper::container>
