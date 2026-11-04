<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Shopper\Core\Enum\CollectionType;
use Shopper\Core\Enum\ProductType;
use Shopper\Core\Models\Contracts\Brand as BrandContract;
use Shopper\Core\Models\Contracts\Category as CategoryContract;
use Shopper\Core\Models\Contracts\Channel as ChannelContract;
use Shopper\Core\Models\Contracts\Collection as CollectionContract;
use Shopper\Core\Models\Contracts\Product as ProductContract;
use Shopper\Core\Models\Country;
use Shopper\Core\Models\Currency;
use Shopper\Core\Models\Inventory;
use Shopper\Core\Models\ProductTag;
use Shopper\Core\Models\Supplier;

final class CatalogSeeder extends Seeder
{
    /**
     * The demo prices above are USD cents: 25,000 ₫ per USD is 250 ₫ per cent.
     */
    private const int USD_CENTS_TO_VND = 250;

    /**
     * @var array<string, list<string>>
     */
    private array $categories = [
        'Electronics' => ['Phones', 'Laptops', 'Audio', 'Accessories'],
        'Fashion' => ['Shoes', 'Apparel', 'Bags'],
        'Home & Living' => ['Kitchen', 'Furniture'],
    ];

    /**
     * name, brand, category, price (USD cents), compare price, stock, featured.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: int, 4: ?int, 5: int, 6: bool}>
     */
    private array $products = [
        ['iPhone 16 Pro', 'Apple', 'Phones', 99900, 109900, 40, true],
        ['iPhone 16', 'Apple', 'Phones', 79900, null, 55, false],
        ['Galaxy S25 Ultra', 'Samsung', 'Phones', 129900, 139900, 30, true],
        ['Galaxy A56', 'Samsung', 'Phones', 44900, null, 80, false],
        ['MacBook Air 13" M4', 'Apple', 'Laptops', 109900, null, 25, true],
        ['MacBook Pro 14" M4 Pro', 'Apple', 'Laptops', 199900, 214900, 12, false],
        ['Galaxy Book5 Pro', 'Samsung', 'Laptops', 144900, null, 15, false],
        ['WH-1000XM6 Headphones', 'Sony', 'Audio', 44900, 49900, 60, true],
        ['AirPods Pro 3', 'Apple', 'Audio', 24900, null, 120, true],
        ['SRS-XB100 Speaker', 'Sony', 'Audio', 5999, 6999, 90, false],
        ['MX Master 3S Mouse', 'Logitech', 'Accessories', 9999, null, 150, false],
        ['MX Keys S Keyboard', 'Logitech', 'Accessories', 10999, 11999, 100, false],
        ['Air Max 90', 'Nike', 'Shoes', 13000, null, 70, true],
        ['Pegasus 41', 'Nike', 'Shoes', 14000, 15500, 65, false],
        ['Ultraboost 5', 'Adidas', 'Shoes', 18000, null, 45, false],
        ['Samba OG', 'Adidas', 'Shoes', 10000, null, 85, true],
        ['Tech Fleece Hoodie', 'Nike', 'Apparel', 13000, 14500, 50, false],
        ['Essentials Track Jacket', 'Adidas', 'Apparel', 7000, null, 75, false],
        ['Classic Backpack', 'Adidas', 'Bags', 5500, null, 110, false],
        ['Cast Iron Skillet 12"', 'Lodge', 'Kitchen', 4499, 5499, 95, false],
        ['Chef Knife 8"', 'Lodge', 'Kitchen', 7999, null, 40, false],
        ['Lounge Armchair', 'Ikea', 'Furniture', 29900, 34900, 8, true],
        ['Oak Coffee Table', 'Ikea', 'Furniture', 19900, null, 3, false],
        ['Bookshelf Billy', 'Ikea', 'Furniture', 8900, null, 0, false],
    ];

    public function run(): void
    {
        $country = Country::query()->where('cca2', 'US')->firstOrFail();
        $currency = Currency::query()->where('code', 'VND')->firstOrFail();
        $channel = resolve(ChannelContract::class)::query()->where('is_default', true)->firstOrFail();

        $inventory = Inventory::query()->create([
            'name' => 'Main Warehouse',
            'code' => 'main-warehouse',
            'email' => 'warehouse@laravelshopper.dev',
            'street_address' => '1200 Industrial Way',
            'postal_code' => '94080',
            'city' => 'South San Francisco',
            'phone_number' => '+1 650 555 0110',
            'is_default' => true,
            'country_id' => $country->id,
        ]);

        $brands = $this->createBrands();
        $categories = $this->createCategories();
        $suppliers = $this->createSuppliers();

        $tags = collect(['New', 'Bestseller', 'Eco-friendly', 'Limited'])
            ->map(fn (string $name): ProductTag => ProductTag::query()->create(['name' => $name]));

        $newArrivals = resolve(CollectionContract::class)::query()->create([
            'name' => 'New Arrivals',
            'slug' => 'New Arrivals',
            'type' => CollectionType::Manual,
            'description' => 'The latest products in our store.',
            'published_at' => now(),
        ]);

        $featured = resolve(CollectionContract::class)::query()->create([
            'name' => 'Staff Picks',
            'slug' => 'Staff Picks',
            'type' => CollectionType::Manual,
            'description' => 'Products our team loves.',
            'published_at' => now(),
        ]);

        foreach ($this->products as $index => [$name, $brand, $category, $price, $compare, $stock, $isFeatured]) {
            $product = resolve(ProductContract::class)::query()->create([
                'name' => $name,
                'slug' => $name,
                'sku' => sprintf('SKU-%05d', $index + 1),
                'barcode' => fake()->unique()->ean13(),
                'description' => fake()->paragraphs(2, true),
                'type' => ProductType::Standard,
                'is_visible' => true,
                'featured' => $isFeatured,
                'security_stock' => 5,
                'brand_id' => $brands[$brand]->id,
                'supplier_id' => $suppliers->random()->id,
                'published_at' => now()->subDays(fake()->numberBetween(1, 180)),
                'weight_value' => fake()->randomFloat(2, 0.1, 15),
                'weight_unit' => 'kg',
            ]);

            $product->categories()->attach($categories[$category]->id);
            $product->channels()->attach($channel->id);
            $product->tags()->attach($tags->random(fake()->numberBetween(0, 2))->pluck('id'));

            if ($index >= count($this->products) - 8) {
                $product->collections()->attach($newArrivals->id);
            }

            if ($isFeatured) {
                $product->collections()->attach($featured->id);
            }

            $product->prices()->create([
                'amount' => $price * self::USD_CENTS_TO_VND,
                'compare_amount' => $compare ? $compare * self::USD_CENTS_TO_VND : null,
                'cost_amount' => (int) round($price * self::USD_CENTS_TO_VND * 0.6),
                'currency_id' => $currency->id,
            ]);

            if ($stock > 0) {
                $product->mutateStock(
                    inventoryId: $inventory->id,
                    quantity: $stock,
                    event: 'Initial stock',
                );
            }
        }
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Model>
     */
    private function createBrands(): Collection
    {
        return collect([
            'Apple' => 'https://www.apple.com',
            'Samsung' => 'https://www.samsung.com',
            'Sony' => 'https://www.sony.com',
            'Logitech' => 'https://www.logitech.com',
            'Nike' => 'https://www.nike.com',
            'Adidas' => 'https://www.adidas.com',
            'Lodge' => 'https://www.lodgecastiron.com',
            'Ikea' => 'https://www.ikea.com',
        ])->map(fn (string $website, string $name) => resolve(BrandContract::class)::query()->create([
            'name' => $name,
            'slug' => $name,
            'website' => $website,
            'description' => fake()->sentence(12),
            'is_enabled' => true,
        ]));
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Model>
     */
    private function createCategories(): Collection
    {
        $categories = collect();
        $position = 0;

        foreach ($this->categories as $parentName => $children) {
            $parent = resolve(CategoryContract::class)::query()->create([
                'name' => $parentName,
                'slug' => $parentName,
                'description' => fake()->sentence(10),
                'position' => $position++,
                'is_enabled' => true,
            ]);

            $categories->put($parentName, $parent);

            foreach ($children as $childPosition => $childName) {
                $categories->put($childName, resolve(CategoryContract::class)::query()->create([
                    'name' => $childName,
                    'slug' => $childName,
                    'description' => fake()->sentence(10),
                    'position' => $childPosition,
                    'is_enabled' => true,
                    'parent_id' => $parent->id,
                ]));
            }
        }

        return $categories;
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function createSuppliers(): Collection
    {
        return collect(['Global Distribution Co.', 'Pacific Wholesale', 'EuroTrade Partners'])
            ->map(fn (string $name): Supplier => Supplier::query()->create([
                'name' => $name,
                'email' => fake()->unique()->companyEmail(),
                'phone' => fake()->phoneNumber(),
                'contact_name' => fake()->name(),
                'website' => 'https://www.'.fake()->domainName(),
                'is_enabled' => true,
            ]));
    }
}
