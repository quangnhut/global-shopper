<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Shopper\Cms\Models\Post;
use Shopper\Core\Models\Legal;
use Shopper\Core\Models\Product;
use Shopper\Storefront\Actions\SaveHomeSectionAction;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\SectionItems;

/**
 * Demo home page: sale, hot deal, new arrivals and news sections, plus header and footer settings.
 * The company details are placeholders: replace them before going live.
 */
final class HomepageSeeder extends Seeder
{
    public function run(SaveHomeSectionAction $saveSection, StorefrontSettings $settings): void
    {
        if (HomeSection::query()->exists()) {
            return;
        }

        $products = SectionItems::constrainProducts(Product::query())->with('prices')->get();
        $onSale = $products->filter(fn (Product $product): bool => $product->prices->contains(fn ($price): bool => $price->compare_amount > $price->amount));
        $posts = SectionItems::constrainPosts(Post::query())->latest('published_at')->limit(4)->pluck('id')->all();

        $sections = [
            ['title' => 'Deal hot', 'subtitle' => 'Giá sốc, số lượng có hạn', 'style' => 'hot_deal', 'layout' => 'carousel', 'columns' => 5, 'ends_at' => now()->addDays(3)->endOfDay(), 'items' => $products->shuffle()->take(8)->pluck('id')->all()],
            ['title' => 'Khuyến mãi', 'subtitle' => 'Ưu đãi tốt nhất tuần này', 'style' => 'sale', 'layout' => 'grid', 'columns' => 5, 'items' => ($onSale->isNotEmpty() ? $onSale : $products)->take(10)->pluck('id')->all()],
            ['title' => 'Sản phẩm mới', 'style' => 'new', 'layout' => 'carousel', 'columns' => 5, 'items' => $products->sortByDesc('published_at')->take(10)->pluck('id')->all()],
            ['type' => 'posts', 'title' => 'Tin tức & mẹo hay', 'style' => 'default', 'layout' => 'grid', 'columns' => 4, 'items' => $posts],
        ];

        foreach ($sections as $section) {
            $items = $section['items'];
            unset($section['items']);

            $saveSection->execute(null, ['type' => 'products', 'is_enabled' => true, ...$section], $items);
        }

        $page = fn (string $title): ?int => Post::query()->ofType('page')->where('title', $title)->value('id');
        $legal = fn (string $slug): ?int => Legal::query()->where('slug', $slug)->value('id');
        $link = fn (string $type, ?int $id): ?array => $id === null ? null : ['type' => $type, 'id' => $id, 'url' => null, 'label' => null];

        $settings->save([
            'hotline' => '1900 1234',
            'working_hours' => '8:00 – 21:00, Thứ 2 – Chủ nhật',
            'business_registration' => '0123456789 (demo)',
            'registration_issued' => 'Cấp ngày 01/01/2024 tại Sở KH&ĐT TP. Hồ Chí Minh (demo)',
            'footer_columns' => [
                [
                    'title' => 'Về chúng tôi',
                    'links' => array_values(array_filter([
                        $link('page', $page('About Us')),
                        $link('page', $page('Contact')),
                        ['type' => 'url', 'id' => null, 'url' => '/search', 'label' => 'Tìm sản phẩm'],
                    ])),
                ],
                [
                    'title' => 'Chính sách',
                    'links' => array_values(array_filter([
                        $link('legal', $legal('refund')),
                        $link('legal', $legal('shipping')),
                        $link('legal', $legal('privacy')),
                        $link('legal', $legal('terms')),
                    ])),
                ],
            ],
            'bct_url' => 'http://online.gov.vn/',
            'dmca_url' => 'https://www.dmca.com/',
            'extra_partners' => [
                ['name' => 'GHN', 'group' => 'shipping', 'url' => 'https://ghn.vn', 'logo' => null],
                ['name' => 'GHTK', 'group' => 'shipping', 'url' => 'https://giaohangtietkiem.vn', 'logo' => null],
                ['name' => 'VNPay', 'group' => 'payment', 'url' => 'https://vnpay.vn', 'logo' => null],
                ['name' => 'MoMo', 'group' => 'payment', 'url' => 'https://momo.vn', 'logo' => null],
            ],
        ]);
    }
}
