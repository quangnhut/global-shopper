<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Shopper\Core\Models\Upload;

/**
 * Random demo images taken from the admin uploads folder (`./uploads` on the host, served from the public disk).
 * Large photos are resized to web copies in `uploads/demo/`; small images (logos, flags) are centred on a white
 * square so product cards show them sharp. Without any image in the folder, nothing is attached.
 */
final class DemoImages
{
    private const string DISK = 'public';

    private const string DIRECTORY = 'uploads/demo';

    /** Images wider than this are treated as photos and resized. */
    private const int PHOTO_MIN_WIDTH = 1000;

    /** @var list<string>|null */
    private static ?array $deck = null;

    /**
     * Paths (on the public disk) of every image in the uploads folder, except the generated copies.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_filter(
            Storage::disk(self::DISK)->allFiles('uploads'),
            fn (string $path): bool => ! str_starts_with($path, self::DIRECTORY.'/')
                && in_array(mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['webp', 'png', 'jpg', 'jpeg'], true),
        ));
    }

    /**
     * The next image of a shuffled deck: every image is used before any repeats.
     */
    public static function next(): ?string
    {
        if (self::$deck === null || self::$deck === []) {
            self::$deck = self::all();
            shuffle(self::$deck);
        }

        return array_pop(self::$deck);
    }

    /**
     * Absolute path of a square, web-sized version for product cards.
     */
    public static function card(string $path, int $size = 800): ?string
    {
        $target = self::DIRECTORY.'/'.pathinfo($path, PATHINFO_FILENAME)."-card-{$size}.webp";

        return self::make($target, function () use ($path, $size): ?GdImage {
            $image = self::open($path);

            if ($image === null) {
                return null;
            }

            $square = imagecreatetruecolor($size, $size);
            imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
            $width = imagesx($image);
            $height = imagesy($image);

            if ($width >= self::PHOTO_MIN_WIDTH) {
                // Photo: centred square crop.
                $side = min($width, $height);
                imagecopyresampled($square, $image, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $size, $size, $side, $side);
            } else {
                // Logo or flag: at most 2.5x its size and half the card, centred.
                $scale = min(2.5, ($size * 0.5) / max($width, $height));
                $newWidth = (int) round($width * $scale);
                $newHeight = (int) round($height * $scale);
                imagecopyresampled($square, $image, intdiv($size - $newWidth, 2), intdiv($size - $newHeight, 2), 0, 0, $newWidth, $newHeight, $width, $height);
            }

            return $square;
        });
    }

    /**
     * Absolute path of a copy of an uploaded logo with its transparent border cut off, or null.
     */
    public static function trimmedLogo(string $file): ?string
    {
        $original = collect(self::all())->first(fn (string $path): bool => basename($path) === $file);

        if ($original === null) {
            return null;
        }

        $target = self::DIRECTORY.'/'.pathinfo($file, PATHINFO_FILENAME).'-logo.png';

        if (! Storage::disk(self::DISK)->exists($target)) {
            $image = self::open($original);

            if ($image === null) {
                return null;
            }

            imagesavealpha($image, true);
            $trimmed = self::trim($image);

            Storage::disk(self::DISK)->makeDirectory(self::DIRECTORY);
            imagepng($trimmed, Storage::disk(self::DISK)->path($target), 9);
        }

        return Storage::disk(self::DISK)->path($target);
    }

    /**
     * An Uploads library entry usable as a featured image: a 1200 px copy of a photo, or the image itself.
     */
    public static function upload(string $path, ?int $userId = null): ?Upload
    {
        $image = self::open($path);

        if ($image === null) {
            return null;
        }

        if (imagesx($image) < self::PHOTO_MIN_WIDTH) {
            return self::register(Storage::disk(self::DISK)->path($path), $userId);
        }

        $target = self::DIRECTORY.'/'.pathinfo($path, PATHINFO_FILENAME).'-1200.webp';
        $absolute = self::make($target, fn (): GdImage|false => imagescale($image, 1200));

        return self::register($absolute, $userId);
    }

    /**
     * The Uploads entry of a file on the public disk (reuses the existing entry for admin uploads).
     */
    public static function register(?string $absolute, ?int $userId = null): ?Upload
    {
        if ($absolute === null || ! is_file($absolute)) {
            return null;
        }

        $path = str_replace(Storage::disk(self::DISK)->path(''), '', $absolute);

        return Upload::query()->firstOrCreate(['disk' => self::DISK, 'path' => $path], [
            'name' => basename($path),
            'file_name' => basename($path),
            'mime_type' => (string) mime_content_type($absolute),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'size' => (int) filesize($absolute),
            'user_id' => $userId,
        ]);
    }

    /**
     * @param  callable(): (GdImage|false|null)  $build
     */
    private static function make(string $target, callable $build): ?string
    {
        if (! Storage::disk(self::DISK)->exists($target)) {
            $image = $build();

            if (! $image instanceof GdImage) {
                return null;
            }

            Storage::disk(self::DISK)->makeDirectory(self::DIRECTORY);
            imagewebp($image, Storage::disk(self::DISK)->path($target), 80);
        }

        return Storage::disk(self::DISK)->path($target);
    }

    /**
     * Cuts off the border made of (nearly) transparent or white pixels, keeping a 1 px margin.
     */
    private static function trim(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        [$left, $top, $right, $bottom] = [$width, $height, -1, -1];

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($image, $x, $y);

                if ((($color >> 24) & 127) < 100 && ($color & 0xFFFFFF) !== 0xFFFFFF) {
                    [$left, $top, $right, $bottom] = [min($left, $x), min($top, $y), max($right, $x), max($bottom, $y)];
                }
            }
        }

        if ($right < 0) {
            return $image;
        }

        $left = max(0, $left - 1);
        $top = max(0, $top - 1);
        $crop = imagecrop($image, ['x' => $left, 'y' => $top, 'width' => min($width, $right + 2) - $left, 'height' => min($height, $bottom + 2) - $top]);

        if (! $crop instanceof GdImage) {
            return $image;
        }

        imagesavealpha($crop, true);

        return $crop;
    }

    private static function open(string $path): ?GdImage
    {
        $absolute = Storage::disk(self::DISK)->path($path);

        // Some PNGs carry a broken sRGB profile; GD only warns about it.
        $image = @match (mb_strtolower(pathinfo($absolute, PATHINFO_EXTENSION))) {
            'webp' => imagecreatefromwebp($absolute),
            'png' => imagecreatefrompng($absolute),
            'jpg', 'jpeg' => imagecreatefromjpeg($absolute),
            default => false,
        };

        if (! $image instanceof GdImage) {
            return null;
        }

        imagepalettetotruecolor($image);

        return $image;
    }
}
