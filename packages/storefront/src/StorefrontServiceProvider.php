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
use Shopper\OnePay\Events\OnePayPaymentFailed;
use Shopper\OnePay\Events\OnePayPaymentSucceeded;
use Shopper\OnePay\OnePay;
use Shopper\ShopperPanel;
use Shopper\Storefront\Cart\StorefrontCart;
use Shopper\Storefront\Checkout\OnePayCheckout;
use Shopper\Storefront\Console\TelegramTestCommand;
use Shopper\Storefront\Http\Middleware\SetStorefrontLocale;
use Shopper\Storefront\Jobs\SendTelegramOrderMessage;
use Shopper\Storefront\Livewire\Components\HeaderSearch;
use Shopper\Storefront\Livewire\Components\MiniCart;
use Shopper\Storefront\Livewire\Pages\CartPage;
use Shopper\Storefront\Livewire\Pages\CheckoutPage;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Models\HomeSectionItem;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\SectionItems;
use Shopper\Storefront\Support\StorefrontCache;
use Shopper\Storefront\Telegram\OrderTelegramMessage;
use Shopper\Storefront\Telegram\TelegramConfig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'shopper.storefront');

        $this->app->singleton(StorefrontSettings::class);

        // Customers come back from OnePay to the storefront: same language as the shop.
        config()->set('shopper.payment.drivers.onepay.routes.return_middleware', ['web', SetStorefrontLocale::class]);
        $this->app->singleton(SectionItems::class);
        // Scoped: a long-running queue worker re-reads the file for each job.
        $this->app->scoped(TelegramConfig::class);
        // Scoped: the JSON API switches it to the cart of the `X-Cart-Token` header for one request.
        $this->app->scoped(StorefrontCart::class);

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

        if ($this->app->runningInConsole()) {
            $this->commands([TelegramTestCommand::class]);
        }

        $this->app['router']->aliasMiddleware('shopper-storefront.locale', SetStorefrontLocale::class);

        // Livewire updates (quantity buttons, search suggestions) keep the storefront language.
        Livewire::addPersistentMiddleware([SetStorefrontLocale::class]);

        Livewire::component('shopper-storefront.header-search', HeaderSearch::class);
        Livewire::component('shopper-storefront.mini-cart', MiniCart::class);
        Livewire::component('shopper-storefront.cart-page', CartPage::class);
        Livewire::component('shopper-storefront.checkout-page', CheckoutPage::class);

        if (config('shopper.storefront.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        if (config('shopper.storefront.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->flushSettingsOnChanges();
        $this->connectOnePay();

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
     * With `shopper/onepay` installed: back from OnePay to the order page, and Telegram alerts on the result.
     */
    private function connectOnePay(): void
    {
        if (! OnePayCheckout::installed()) {
            return;
        }

        OnePay::redirectAfterReturnUsing(OnePayCheckout::afterReturn(...));

        $this->app['events']->listen(OnePayPaymentSucceeded::class, fn (OnePayPaymentSucceeded $event) => SendTelegramOrderMessage::dispatch($event->order->id, OrderTelegramMessage::PAID));
        $this->app['events']->listen(OnePayPaymentFailed::class, fn (OnePayPaymentFailed $event) => SendTelegramOrderMessage::dispatch($event->order->id, OrderTelegramMessage::PAYMENT_FAILED, $event->message));
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
