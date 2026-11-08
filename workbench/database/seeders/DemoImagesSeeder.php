<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Support\DemoImages;
use Illuminate\Database\Seeder;
use Shopper\Cms\Models\Post;
use Shopper\Core\Models\Contracts\Product as ProductContract;
use Shopper\Storefront\Settings\StorefrontSettings;
use Spatie\MediaLibrary\HasMedia;

/**
 * Random images from the uploads folder for every product without an image and every post
 * without a featured image. The images do not match the products: they only make the demo look full.
 */
final class DemoImagesSeeder extends Seeder
{
    public function run(): void
    {
        if (DemoImages::all() === []) {
            return;
        }

        $collection = (string) config('shopper.media.storage.thumbnail_collection', 'thumbnail');

        foreach (resolve(ProductContract::class)::query()->with('media')->get() as $product) {
            if (! $product instanceof HasMedia || $product->getMedia($collection)->isNotEmpty()) {
                continue;
            }

            $image = DemoImages::next();
            $card = $image !== null ? DemoImages::card($image) : null;

            if ($card !== null) {
                $product->addMedia($card)->preservingOriginal()->toMediaCollection($collection);
            }
        }

        // Header logo (uploads/2026/11/8h.png), unless one is already chosen.
        $settings = resolve(StorefrontSettings::class);

        if ($settings->get('header_logo') === null) {
            $logo = DemoImages::register(DemoImages::trimmedLogo('8h.png'));

            if ($logo !== null) {
                $settings->save(['header_logo' => $logo->id]);
            }
        }

        foreach (Post::query()->withTrashed()->whereNull('featured_upload_id')->get() as $post) {
            $image = DemoImages::next();
            $upload = $image !== null ? DemoImages::upload($image, $post->author_id) : null;

            if ($upload !== null) {
                $post->forceFill(['featured_upload_id' => $upload->id])->saveQuietly();
            }
        }
    }
}
