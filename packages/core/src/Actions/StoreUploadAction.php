<?php

declare(strict_types=1);

namespace Shopper\Core\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Shopper\Core\Models\Upload;

final class StoreUploadAction
{
    public function execute(UploadedFile $file, ?int $userId = null): Upload
    {
        // Trust the detected content type over the client-provided name, so a
        // renamed script can never keep an executable extension on disk.
        $extension = mb_strtolower((string) $file->guessExtension());

        if (! in_array($extension, config('shopper.media.uploads.extensions', []), true)) {
            throw new InvalidArgumentException("Files of type [{$extension}] are not allowed.");
        }

        $disk = config('shopper.media.uploads.disk');
        $directory = mb_trim((string) config('shopper.media.uploads.directory'), '/').'/'.now()->format('Y/m');
        $originalName = $file->getClientOriginalName();
        $fileName = $this->uniqueFileName($disk, $directory, pathinfo($originalName, PATHINFO_FILENAME), $extension);

        $path = $file->storeAs($directory, $fileName, ['disk' => $disk]);

        return Upload::query()->create([
            'name' => $originalName,
            'file_name' => $fileName,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => (string) $file->getMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'user_id' => $userId,
        ]);
    }

    private function uniqueFileName(string $disk, string $directory, string $baseName, string $extension): string
    {
        $baseName = Str::slug($baseName) ?: Str::random(8);
        $fileName = "{$baseName}.{$extension}";
        $counter = 1;

        while (Storage::disk($disk)->exists("{$directory}/{$fileName}")) {
            $fileName = "{$baseName}-{$counter}.{$extension}";
            $counter++;
        }

        return $fileName;
    }
}
