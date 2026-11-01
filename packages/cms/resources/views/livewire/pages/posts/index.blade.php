<x-shopper::container class="py-5">
    <x-shopper::heading :title="$postType->getLabel()">
        <x-slot name="action">
            <div class="flex items-center gap-3">
                @if ($this->emptyTrashAction->isVisible())
                    {{ $this->emptyTrashAction }}
                @endif

                @if ($this->createAction->isVisible())
                    {{ $this->createAction }}
                @endif
            </div>
        </x-slot>
    </x-shopper::heading>

    <div class="mt-8 space-y-4">
        <x-shopper::tabs-navigation
            wire:model="activeTab"
            :tabs="collect($this->getCachedTabs())->map(fn ($tab, $key): array => [
                'key' => $key,
                'label' => $tab->getLabel(),
                'icon' => $tab->getIcon(),
                'badge' => $tab->getBadge(),
                'badgeColor' => $tab->getBadgeColor(),
            ])->values()->all()"
            :active="$this->activeTab"
        />

        {{ $this->table }}
    </div>
</x-shopper::container>
