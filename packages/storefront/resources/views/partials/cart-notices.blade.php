@if ($notices !== [])
    <div class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200" role="status">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($notices as $notice)
                <li>{{ $notice }}</li>
            @endforeach
        </ul>
    </div>
@endif
@error('cart')
    <p class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" role="alert">{{ $message }}</p>
@enderror
