<x-shopper::container class="py-5">
    <x-shopper::heading :title="__('shopper-cms::taxonomies.tags.menu')">
        <x-slot name="action">
            @can('add_post_tags')
                {{ $this->createAction }}
            @endcan
        </x-slot>
    </x-shopper::heading>

    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-shopper::container>
