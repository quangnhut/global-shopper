<?php

declare(strict_types=1);

namespace Shopper\Cms\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Self-hosted TinyMCE as a Filament field, configured like WordPress's classic editor.
 * Defaults come from `shopper.cms.editor`; every option can be changed per field.
 */
class TinyEditor extends Field
{
    protected string $view = 'shopper-cms::forms.components.tiny-editor';

    protected string|Closure|null $toolbar = null;

    protected string|Closure|null $plugins = null;

    protected int|Closure|null $height = null;

    protected string|Closure|null $uploadUrl = null;

    protected string|Closure|null $mediaAction = null;

    public function toolbar(string|Closure|null $toolbar): static
    {
        $this->toolbar = $toolbar;

        return $this;
    }

    public function plugins(string|Closure|null $plugins): static
    {
        $this->plugins = $plugins;

        return $this;
    }

    public function height(int|Closure|null $height): static
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Endpoint for pasted, dropped and "Add media" image uploads. Null disables image uploads.
     */
    public function uploadUrl(string|Closure|null $url): static
    {
        $this->uploadUrl = $url;

        return $this;
    }

    /**
     * Name of a Livewire action (see `InteractsWithEditorMedia`) opened by the "Add media" button.
     * Null hides the button.
     */
    public function mediaAction(string|Closure|null $action): static
    {
        $this->mediaAction = $action;

        return $this;
    }

    public function getMediaAction(): ?string
    {
        return $this->evaluate($this->mediaAction);
    }

    public function getUploadUrl(): ?string
    {
        return $this->evaluate($this->uploadUrl);
    }

    public function getBaseUrl(): string
    {
        return mb_rtrim(asset((string) config('shopper.cms.editor.base_path')), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditorConfig(): array
    {
        $config = (array) config('shopper.cms.editor.options', []);

        return [
            ...$config,
            'license_key' => config('shopper.cms.editor.license_key'),
            'plugins' => $this->evaluate($this->plugins) ?? $config['plugins'] ?? '',
            'toolbar' => $this->resolveToolbar($config),
            'height' => $this->evaluate($this->height) ?? $config['height'] ?? 500,
            'language' => $this->editorLanguage(),
            'automatic_uploads' => $this->getUploadUrl() !== null,
            'paste_data_images' => $this->getUploadUrl() !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveToolbar(array $config): string
    {
        $toolbar = (string) ($this->evaluate($this->toolbar) ?? $config['toolbar'] ?? '');

        return $this->getMediaAction() === null
            ? (string) preg_replace('/\baddmedia\s*/', '', $toolbar)
            : $toolbar;
    }

    /**
     * TinyMCE ships English only; other languages need their language pack in `langs/`.
     */
    private function editorLanguage(): ?string
    {
        $locale = app()->getLocale();

        if ($locale === 'en' || ! file_exists(public_path(config('shopper.cms.editor.base_path')."/langs/{$locale}.js"))) {
            return null;
        }

        return $locale;
    }
}
