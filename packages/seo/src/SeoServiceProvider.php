<?php

declare(strict_types=1);

namespace Shopper\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\ServiceProvider;
use Shopper\Core\Models\Price;
use Shopper\Core\Models\Review;
use Shopper\Seo\Http\Middleware\AddXRobotsTag;
use Shopper\Seo\Models\SeoMeta;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\SeoCache;
use Shopper\ShopperPanel;

final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/seo.php', 'shopper.seo');

        $this->app->singleton(
            SeoableRegistry::class,
            fn (): SeoableRegistry => SeoableRegistry::fromConfig(config('shopper.seo.types', [])),
        );
        $this->app->singleton(SeoSettings::class);
        $this->app->singleton(SeoManager::class);

        // Attached as soon as the admin panel exists, whatever order the providers boot in.
        $this->callAfterResolving('shopper', fn (ShopperPanel $panel): ShopperPanel => $panel->addon(new SeoAddon));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'shopper-seo');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shopper-seo');

        $this->publishes([
            __DIR__.'/../config/seo.php' => config_path('shopper/seo.php'),
        ], 'shopper-config');

        $this->app['router']->aliasMiddleware('shopper-seo.robots', AddXRobotsTag::class);

        if (config('shopper.seo.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
        }

        if (config('shopper.seo.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        $this->app->booted(function (): void {
            $this->deleteMetaWithTheirEntries();
            $this->flushOutputOnChanges();
        });
    }

    /**
     * Cached head data and sitemaps are dropped when an entry, its SEO meta, a price or a review changes.
     */
    private function flushOutputOnChanges(): void
    {
        $models = [
            ...array_column(resolve(SeoableRegistry::class)->all(), 'model'),
            SeoMeta::class,
            Price::class,
            Review::class,
        ];

        foreach (array_unique($models) as $model) {
            foreach (['saved', 'deleted', 'restored', 'forceDeleted'] as $event) {
                $this->app['events']->listen("eloquent.{$event}: {$model}", fn () => SeoCache::flush());
            }
        }
    }

    /**
     * SEO rows go away with their entry (only on permanent deletion for models with a trash).
     */
    private function deleteMetaWithTheirEntries(): void
    {
        foreach (array_unique(array_column(resolve(SeoableRegistry::class)->all(), 'model')) as $model) {
            $event = in_array(SoftDeletes::class, class_uses_recursive($model), true) ? 'forceDeleted' : 'deleted';

            $this->app['events']->listen("eloquent.{$event}: {$model}", fn (Model $entry) => SeoMeta::query()
                ->where('seoable_type', $entry->getMorphClass())
                ->where('seoable_id', $entry->getKey())
                ->delete());
        }
    }
}
