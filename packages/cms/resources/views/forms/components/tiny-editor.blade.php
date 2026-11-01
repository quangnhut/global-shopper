@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Facades\FilamentView;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        wire:key="{{ $getId() }}.tiny-editor"
        @if (FilamentView::hasSpaMode(url()->current()))
            x-load="visible || event (x-modal-opened)"
        @else
            x-load
        @endif
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('tiny-editor', package: 'shopper/cms') }}"
        x-data="tinyEditor({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')", isOptimisticallyLive: false) }},
            config: @js($getEditorConfig()),
            baseUrl: @js($getBaseUrl()),
            uploadUrl: @js($getUploadUrl()),
            mediaAction: @js($getMediaAction()),
            mediaLabel: @js(__('shopper-cms::posts.media.add_media')),
            statePath: @js($getStatePath()),
            disabled: @js($isDisabled()),
        })"
        x-on:cms-editor-insert.window="insertHtml($event.detail)"
        class="min-h-[200px] overflow-hidden rounded-lg"
    >
        <textarea x-ref="textarea" id="{{ $getId() }}" class="hidden"></textarea>
    </div>
</x-dynamic-component>
