<?php

declare(strict_types=1);

namespace Shopper\Seo\Actions;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Shopper\Core\Models\Upload;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

final class GenerateSiteIconsAction
{
    /**
     * Favicon, Apple touch icon and web app manifest sizes.
     */
    public const array SIZES = [16, 32, 180, 192, 512];

    public const string DIRECTORY = 'seo/icons';

    /**
     * Square PNG icons from the "website avatar" (site icon), plus `site.webmanifest`, on the public disk.
     *
     * @return array<int, string> size => public path on the disk
     */
    public function execute(Upload $upload, string $siteName): array
    {
        if (! $upload->isImage()) {
            throw new InvalidArgumentException('The site icon must be an image.');
        }

        $disk = Storage::disk((string) config('shopper.media.uploads.disk', 'public'));
        $source = tempnam(sys_get_temp_dir(), 'site-icon');
        file_put_contents($source, Storage::disk($upload->disk)->get($upload->path));

        $icons = [];

        foreach (self::SIZES as $size) {
            $target = tempnam(sys_get_temp_dir(), "icon-{$size}").'.png';

            Image::load($source)->fit(Fit::Crop, $size, $size)->format('png')->save($target);

            $path = self::DIRECTORY."/icon-{$size}.png";
            $disk->put($path, (string) file_get_contents($target));
            @unlink($target);

            $icons[$size] = $path;
        }

        @unlink($source);

        $disk->put(self::DIRECTORY.'/site.webmanifest', (string) json_encode([
            'name' => $siteName,
            'short_name' => $siteName,
            'icons' => [
                ['src' => $disk->url($icons[192]), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $disk->url($icons[512]), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
            'display' => 'browser',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $icons;
    }
}
