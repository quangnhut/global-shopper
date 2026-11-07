<x-shopper::container class="py-5">
    <div class="flex items-center gap-2 text-sm">
        <a
            href="{{ route('shopper.storefront.sections.index') }}"
            wire:navigate
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
        >
            {{ __('shopper-storefront::admin.sections.menu') }}
        </a>
        <span class="text-gray-400">/</span>
        <span class="font-medium text-gray-700 dark:text-gray-300">
            {{ $section?->title ?? __('shopper-storefront::admin.sections.create') }}
        </span>
    </div>

    <form wire:submit="save" class="mt-6">
        <x-shopper::heading
            :title="$section?->title ?? __('shopper-storefront::admin.sections.create')"
        >
            <x-slot name="action">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <x-shopper::loader wire:loading wire:target="save" class="text-white" />
                    {{ __('shopper::forms.actions.save') }}
                </x-filament::button>
            </x-slot>
        </x-shopper::heading>

        <div class="mt-8">
            {{ $this->form }}
        </div>
    </form>

    <x-filament-actions::modals />
</x-shopper::container>
