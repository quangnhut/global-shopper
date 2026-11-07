<?php

declare(strict_types=1);

namespace Shopper\Storefront\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The image a product card shows: the thumbnail, else the first gallery image, else null.
 * Expects `media` to be eager loaded.
 */
final class ProductImage
{
    public static function url(Model $product): ?string
    {
        if (! $product instanceof SpatieHasMedia) {
            return null;
        }

        foreach ([config('shopper.media.storage.thumbnail_collection', 'thumbnail'), config('shopper.media.storage.collection_name', 'default')] as $collection) {
            $media = $product->getMedia((string) $collection)->first();

            if ($media instanceof Media) {
                return $media->getFullUrl();
            }
        }

        return null;
    }
}
