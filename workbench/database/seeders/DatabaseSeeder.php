<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Shopper\Core\Database\Seeders\ShopperSeeder;
use Shopper\Database\Seeders\AuthTableSeeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ShopperSeeder::class);
        $this->call(AuthTableSeeder::class);

        Model::unguard();

        $this->call(StoreSettingsSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(CatalogSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(OrderSeeder::class);
        $this->call(ReviewSeeder::class);
        $this->call(DiscountSeeder::class);
        $this->call(CmsSeeder::class);
        $this->call(SeoSeeder::class);
        $this->call(DemoImagesSeeder::class);
        $this->call(HomepageSeeder::class);

        Model::reguard();
    }
}
