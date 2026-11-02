<x-shopper::container class="py-5">
    <x-shopper::heading :title="__('shopper-seo::settings.title')" />

    @if ($this->data['general']['discourage_search_engines'] ?? false)
        <div class="mt-6 rounded-lg bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700 ring-1 ring-danger-200 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/20">
            {{ __('shopper-seo::settings.general.discourage_banner') }}
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
