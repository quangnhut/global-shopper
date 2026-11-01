<x-shopper::container class="py-5">
    <x-shopper::heading :title="__('shopper-cms::taxonomies.categories.menu')">
        <x-slot name="action">
            @can('add_post_categories')
                {{ $this->createAction }}
            @endcan
        </x-slot>
    </x-shopper::heading>

    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-shopper::container>
