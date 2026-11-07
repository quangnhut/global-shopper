<x-shopper::container class="py-5">
    <x-shopper::heading :title="__('shopper-storefront::admin.settings.title')" />
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('shopper-storefront::admin.settings.description') }}</p>

    @php($missing = $this->missingLegalDetails())

    @if ($missing !== [])
        <div
            class="mt-6 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-800 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/20"
            role="status"
        >
            <p class="font-medium">{{ __('shopper-storefront::admin.settings.legal_warning') }}</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($missing as $detail)
                    <li>{{ __('shopper-storefront::admin.settings.legal_details.' . $detail) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="save" class="mt-8">
        {{ $this->form }}

        <div class="mt-8 flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <x-shopper::loader wire:loading wire:target="save" class="text-white" />
                {{ __('shopper::forms.actions.save') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</x-shopper::container>
