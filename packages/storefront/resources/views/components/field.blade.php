@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'required' => true])

<div>
    <label for="sf-{{ $name }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    <input
        id="sf-{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        @error($name) aria-invalid="true" aria-describedby="sf-{{ $name }}-error" @enderror
        {{ $attributes->merge(['class' => 'sf-input mt-1']) }}
    />
    @error($name)
        <p id="sf-{{ $name }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
