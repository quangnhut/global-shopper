<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Shopper\Components\Form\UploadPicker;
use Shopper\Core\Models\Upload;

/**
 * WordPress's "Add Media" for `TinyEditor` fields: the editor's button mounts this action,
 * and the chosen upload is sent back to that editor as HTML (`cms-editor-insert` event).
 */
trait InteractsWithEditorMedia
{
    public function insertMediaAction(): Action
    {
        return Action::make('insertMedia')
            ->label(__('shopper-cms::posts.media.add_media'))
            ->modalHeading(__('shopper-cms::posts.media.add_media'))
            ->modalSubmitActionLabel(__('shopper-cms::posts.media.insert'))
            ->schema([
                UploadPicker::make('upload_id', imagesOnly: false)
                    ->label(__('shopper-cms::posts.media.file'))
                    ->required(),
                TextInput::make('alt')
                    ->label(__('shopper-cms::posts.media.alt'))
                    ->helperText(__('shopper-cms::posts.media.alt_help'))
                    ->maxLength(255),
                Radio::make('align')
                    ->label(__('shopper-cms::posts.media.align'))
                    ->options([
                        'none' => __('shopper-cms::posts.media.align_none'),
                        'left' => __('shopper-cms::posts.media.align_left'),
                        'center' => __('shopper-cms::posts.media.align_center'),
                        'right' => __('shopper-cms::posts.media.align_right'),
                    ])
                    ->default('none')
                    ->inline(),
            ])
            ->action(function (array $data, array $arguments): void {
                $upload = Upload::query()->findOrFail($data['upload_id']);

                $this->dispatch(
                    'cms-editor-insert',
                    statePath: (string) ($arguments['statePath'] ?? ''),
                    html: $this->mediaHtml($upload, (string) ($data['alt'] ?? ''), (string) ($data['align'] ?? 'none')),
                );
            });
    }

    /**
     * Images become `<img>` (with WordPress's `align*` classes), other files a download link.
     */
    protected function mediaHtml(Upload $upload, string $alt, string $align): string
    {
        if (! $upload->isImage()) {
            return sprintf('<a href="%s">%s</a>', e($upload->url), e($upload->name));
        }

        $class = in_array($align, ['left', 'center', 'right'], true) ? " class=\"align{$align}\"" : '';

        return sprintf('<img src="%s" alt="%s"%s>', e($upload->url), e($alt), $class);
    }
}
