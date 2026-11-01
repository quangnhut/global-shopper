<div>
    @if (count($this->glance))
        <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
            <x-filament::section :heading="__('shopper-cms::posts.dashboard.at_a_glance')">
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->glance as $row)
                        <li class="flex items-center justify-between py-3 text-sm">
                            <a href="{{ $row['url'] }}" wire:navigate class="font-medium text-gray-900 hover:underline dark:text-white">
                                {{ trans_choice('shopper-cms::posts.dashboard.published_count', $row['published'], ['count' => $row['published'], 'type' => $row['type']->getLabel()]) }}
                            </a>
                            <span class="flex gap-2">
                                @if ($row['drafts'] > 0)
                                    <x-filament::badge color="gray">
                                        {{ trans_choice('shopper-cms::posts.dashboard.drafts_count', $row['drafts'], ['count' => $row['drafts']]) }}
                                    </x-filament::badge>
                                @endif

                                @if ($row['pending'] > 0)
                                    <x-filament::badge color="warning">
                                        {{ trans_choice('shopper-cms::posts.dashboard.pending_count', $row['pending'], ['count' => $row['pending']]) }}
                                    </x-filament::badge>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>

            <x-filament::section :heading="__('shopper-cms::posts.dashboard.recent_drafts')">
                @forelse ($this->recentDrafts as $draft)
                    <div class="py-2 text-sm">
                        <a href="{{ $this->editUrl($draft) }}" wire:navigate class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                            {{ $draft->title !== '' ? $draft->title : __('shopper-cms::posts.no_title') }}
                        </a>
                        <span class="text-gray-500 dark:text-gray-400">{{ $draft->updated_at->translatedFormat('j F Y') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('shopper-cms::posts.dashboard.no_drafts') }}</p>
                @endforelse
            </x-filament::section>
        </div>
    @endif
</div>
