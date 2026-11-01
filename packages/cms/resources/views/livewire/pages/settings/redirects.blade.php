<x-shopper::container>
    <x-shopper::breadcrumb :back="route('shopper.settings.index')" :current="__('shopper-cms::permalinks.redirects.title')">
        <x-untitledui-chevron-left class="size-4 shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true" />
        <x-shopper::breadcrumb.link
            :link="route('shopper.settings.index')"
            :title="__('shopper::pages/settings/global.menu')"
        />
    </x-shopper::breadcrumb>

    <x-shopper::heading class="mt-6" :title="__('shopper-cms::permalinks.redirects.title')">
        <x-slot name="action">
            {{ $this->createAction }}
        </x-slot>
    </x-shopper::heading>

    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-shopper::container>
