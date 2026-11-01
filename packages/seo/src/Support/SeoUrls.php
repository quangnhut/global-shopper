<?php

declare(strict_types=1);

namespace Shopper\Seo\Support;

use Illuminate\Support\Facades\Storage;
use Shopper\Core\Models\Upload;

/**
 * Public URLs as the storefront sees them.
 */
final class SeoUrls
{
    /**
     * Base URL of the storefront, without trailing slash: `shopper.seo.frontend_url`, then the CMS one, then APP_URL.
     */
    public static function base(): string
    {
        return mb_rtrim((string) (config('shopper.seo.frontend_url') ?: config('shopper.cms.frontend_url') ?: config('app.url')), '/');
    }

    public static function home(): string
    {
        return self::base().'/';
    }

    /**
     * Absolute URL of a path; absolute URLs are returned unchanged.
     */
    public static function absolute(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return self::base().'/'.mb_ltrim($path, '/');
    }

    /**
     * Path part of a URL or path, with a leading slash and without query string.
     */
    public static function path(string $pathOrUrl): string
    {
        $path = (string) (parse_url($pathOrUrl, PHP_URL_PATH) ?? '/');

        return '/'.mb_ltrim($path, '/');
    }

    /**
     * Storefront URL of a shop entity from `shopper.seo.paths`.
     */
    public static function entity(string $type, string $slug): string
    {
        $pattern = (string) config("shopper.seo.paths.{$type}", "/{$type}/{slug}");

        return self::absolute(str_replace('{slug}', rawurlencode($slug), $pattern));
    }

    /**
     * Paginated archives: page 1 is the plain URL, page ≥ 2 is canonical to itself (`?page=N`).
     */
    public static function paginated(string $url, int $page): string
    {
        return $page > 1 ? $url.(str_contains($url, '?') ? '&' : '?').'page='.$page : $url;
    }

    public static function upload(mixed $uploadId): ?string
    {
        if (blank($uploadId)) {
            return null;
        }

        $upload = Upload::query()->find($uploadId);

        return $upload?->isImage() ? $upload->url : null;
    }

    public static function disk(?string $path): ?string
    {
        return $path === null ? null : Storage::disk((string) config('shopper.media.uploads.disk', 'public'))->url($path);
    }
}
