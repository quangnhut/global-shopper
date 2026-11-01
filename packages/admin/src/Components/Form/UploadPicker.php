<?php

declare(strict_types=1);

namespace Shopper\Components\Form;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Http\UploadedFile;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Core\Actions\StoreUploadAction;
use Shopper\Core\Models\Upload;

/**
 * Pick a file from the Uploads library (images by default), or upload a new image into it, and store its id.
 * Used by the CMS (featured images, "Add media") and SEO (logo, social images).
 */
final class UploadPicker
{
    /**
     * @param  bool  $imagesOnly  false also lists ZIP and text files (the editor's "Add media")
     */
    public static function make(string $name, bool $imagesOnly = true): Select
    {
        return Select::make($name)
            ->searchable()
            ->allowHtml()
            ->getSearchResultsUsing(fn (string $search): array => self::options(
                Upload::query()->where('name', 'like', "%{$search}%"),
                $imagesOnly,
            ))
            ->options(fn (): array => self::options(Upload::query(), $imagesOnly))
            ->getOptionLabelUsing(fn ($value): ?string => ($upload = Upload::query()->find($value)) instanceof Upload
                ? self::label($upload)
                : null)
            ->suffixAction(
                Action::make('upload')
                    ->label(__('shopper::pages/uploads.upload'))
                    ->icon(Untitledui::UploadCloud02)
                    ->visible(fn (): bool => (bool) shopper()->auth()->user()?->can('add_uploads'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('shopper::pages/uploads.files'))
                            ->image()
                            ->required()
                            ->storeFiles(false)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])
                            ->maxSize((int) config('shopper.media.uploads.max_size')),
                    ])
                    ->action(function (array $data, Set $set) use ($name): void {
                        /** @var UploadedFile $file */
                        $file = $data['file'];

                        $upload = resolve(StoreUploadAction::class)->execute($file, shopper()->auth()->id());

                        $set($name, $upload->id);
                    }),
            );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Upload>  $query
     * @return array<int, string>
     */
    private static function options($query, bool $imagesOnly): array
    {
        return $query
            ->when($imagesOnly, fn ($query) => $query->where('mime_type', 'like', 'image/%'))
            ->latest()
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Upload $upload): array => [$upload->id => self::label($upload)])
            ->all();
    }

    private static function label(Upload $upload): string
    {
        if (! $upload->isImage()) {
            return sprintf('<span>%s <span class="text-gray-500">(%s)</span></span>', e($upload->name), e(mb_strtoupper($upload->extension)));
        }

        return sprintf(
            '<span class="flex items-center gap-2"><img src="%s" alt="" class="size-8 rounded object-cover"><span>%s</span></span>',
            e($upload->url),
            e($upload->name),
        );
    }
}
