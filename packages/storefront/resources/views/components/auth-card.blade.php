@props(['title', 'subtitle' => null])

<div class="sf-container flex justify-center py-10">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif

        <div class="mt-6">{{ $slot }}</div>
    </div>
</div>
