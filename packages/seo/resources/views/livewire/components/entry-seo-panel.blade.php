<x-shopper::container>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <x-shopper::loader wire:loading wire:target="save" class="text-white" />
                {{ __('shopper::forms.actions.save') }}
            </x-filament::button>
        </div>
    </form>
</x-shopper::container>
