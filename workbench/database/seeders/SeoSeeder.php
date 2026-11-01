<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Shopper\Core\Actions\StoreUploadAction;
use Shopper\Core\Models\Upload;
use Shopper\Seo\Actions\GenerateSiteIconsAction;
use Shopper\Seo\Settings\SeoSettings;

/**
 * Demo SEO settings: site identity, a default social image, a logo and the generated site icons,
 * so the head output and JSON-LD have images (Google wants one for products and articles).
 */
final class SeoSeeder extends Seeder
{
    public function run(StoreUploadAction $uploads, GenerateSiteIconsAction $icons, SeoSettings $settings): void
    {
        $social = $this->image($uploads, 'default-social-image.png', 1200, 630, [37, 99, 235]);
        $logo = $this->image($uploads, 'store-logo.png', 512, 512, [15, 23, 42]);

        $settings->save('general', [
            'site_name' => 'Shopper Demo Store',
            'tagline' => 'Electronics, fashion and home goods',
            'site_icon_upload_id' => $logo->id,
            'site_icons' => $icons->execute($logo, 'Shopper Demo Store'),
        ]);
        $settings->save('social', ['default_image_upload_id' => $social->id, 'twitter_handle' => '@shopperdemo']);
        $settings->save('schema', ['logo_upload_id' => $logo->id, 'search_url' => '/search?q={search_term_string}']);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $color
     */
    private function image(StoreUploadAction $uploads, string $name, int $width, int $height, array $color): Upload
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$color));
        imagefilledrectangle($image, (int) ($width * 0.1), (int) ($height * 0.4), (int) ($width * 0.9), (int) ($height * 0.6), (int) imagecolorallocate($image, 255, 255, 255));

        $path = tempnam(sys_get_temp_dir(), 'seo-demo').'.png';
        imagepng($image, $path);

        return $uploads->execute(new UploadedFile($path, $name, 'image/png', test: true));
    }
}
