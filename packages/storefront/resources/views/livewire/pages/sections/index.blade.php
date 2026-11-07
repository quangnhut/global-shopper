<x-shopper::container class="py-5">
    <x-shopper::heading
        :title="__('shopper-storefront::admin.sections.menu')"
    >
        <x-slot name="action">
            <div class="flex items-center gap-3">
                <x-filament::button
                    color="gray"
                    tag="a"
                    :href="$this->storefrontUrl()"
                    target="_blank"
                    icon="untitledui-link-external-01"
                >
                    {{ __('shopper-storefront::admin.view_home') }}
                </x-filament::button>
                <x-filament::button tag="a" :href="route('shopper.storefront.sections.create')" wire:navigate>
                    {{ __('shopper-storefront::admin.sections.create') }}
                </x-filament::button>
            </div>
        </x-slot>
    </x-shopper::heading>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('shopper-storefront::admin.sections.description') }}</p>

    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-shopper::container>
