<?php

declare(strict_types=1);

namespace Shopper\Storefront;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Shopper\Cms\Models\Post;
use Shopper\Core\Models\Carrier;
use Shopper\Core\Models\InventoryHistory;
use Shopper\Core\Models\Legal;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Core\Models\Price;
use Shopper\Core\Models\Setting;
use Shopper\Core\Models\Upload;
use Shopper\ShopperPanel;
use Shopper\Storefront\Http\Middleware\SetStorefrontLocale;
use Shopper\Storefront\Livewire\Components\HeaderSearch;
use Shopper\Storefront\Livewire\Components\MiniCart;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Models\HomeSectionItem;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\SectionItems;
use Shopper\Storefront\Support\StorefrontCache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'shopper.storefront');

        $this->app->singleton(StorefrontSettings::class);
        $this->app->singleton(SectionItems::class);

        // Attached as soon as the admin panel exists, whatever order the providers boot in.
        $this->callAfterResolving('shopper', fn (ShopperPanel $panel): ShopperPanel => $panel->addon(new StorefrontAddon));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'shopper-storefront');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shopper-storefront');

        $this->publishes([
            __DIR__.'/../config/storefront.php' => config_path('shopper/storefront.php'),
        ], 'shopper-config');

        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/shopper-storefront'),
        ], 'shopper-storefront-assets');

        $this->app['router']->aliasMiddleware('shopper-storefront.locale', SetStorefrontLocale::class);

        Livewire::component('shopper-storefront.header-search', HeaderSearch::class);
        Livewire::component('shopper-storefront.mini-cart', MiniCart::class);

        if (config('shopper.storefront.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        if (config('shopper.storefront.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->flushSettingsOnChanges();

        $this->app->booted(fn () => $this->flushCacheOnChanges());
    }

    /**
     * The cached home page is dropped when anything it shows changes.
     */
    private function flushCacheOnChanges(): void
    {
        $models = array_filter([
            HomeSection::configuredClass(),
            HomeSectionItem::configuredClass(),
            config('shopper.models.product'),
            config('shopper.models.variant'),
            config('shopper.models.category'),
            Price::class,
            InventoryHistory::class,
            Post::configuredClass(),
            Legal::class,
            Carrier::class,
            PaymentMethod::class,
            Upload::class,
            Media::class,
        ]);

        foreach (array_unique($models) as $model) {
            foreach (['saved', 'deleted', 'restored', 'forceDeleted'] as $event) {
                $this->app['events']->listen("eloquent.{$event}: {$model}", fn () => StorefrontCache::flush());
            }
        }
    }

    /**
     * Settings → General (name, address, logo…) feeds the header and footer too.
     */
    private function flushSettingsOnChanges(): void
    {
        foreach (['saved', 'deleted'] as $event) {
            $this->app['events']->listen("eloquent.{$event}: ".Setting::class, function (Setting $setting): void {
                if (str_starts_with($setting->key, StorefrontSettings::PREFIX) || in_array($setting->key, StorefrontSettings::STORE_KEYS, true)) {
                    resolve(StorefrontSettings::class)->flush();
                    StorefrontCache::flush();
                }

                if (in_array($setting->key, ['default_currency_id', 'currencies'], true)) {
                    StorefrontCache::flush();
                }
            });
        }
    }
}
