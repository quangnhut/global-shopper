<x-shopper::container class="py-5">
    <x-shopper::heading :title="__('shopper::pages/uploads.menu')">
        <x-slot name="action">
            @can('add_uploads')
                {{ $this->uploadAction }}
            @endcan
        </x-slot>
    </x-shopper::heading>

    {{ shopper()->getRenderHook(\Shopper\View\CatalogRenderHook::UPLOADS_TABLE_BEFORE) }}

    <div class="mt-8">
        {{ $this->table }}
    </div>

    {{ shopper()->getRenderHook(\Shopper\View\CatalogRenderHook::UPLOADS_TABLE_AFTER) }}
</x-shopper::container>
