<?php

declare(strict_types=1);

namespace Shopper\Cms;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Shopper\Cms\Console\EmptyTrashCommand;
use Shopper\Cms\Console\PublishScheduledPostsCommand;
use Shopper\Cms\Models\Contracts\Post as PostContract;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Models\Redirect;
use Shopper\Cms\PostTypes\PostTypeRegistry;
use Shopper\Cms\Seo\CmsPathResolver;
use Shopper\Cms\Seo\PostSource;
use Shopper\Cms\Seo\TermSource;
use Shopper\Cms\Support\CmsCache;
use Shopper\Cms\Support\HtmlSanitizer;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\ShopperPanel;

final class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cms.php', 'shopper.cms');

        $this->app->singleton(
            PostTypeRegistry::class,
            fn (): PostTypeRegistry => PostTypeRegistry::fromConfig(config('shopper.cms.post_types', [])),
        );

        $this->app->bind(PostContract::class, config('shopper.cms.models.post'));

        $this->app->singleton(HtmlSanitizer::class);

        // Posts, pages and their terms get SEO templates, the SEO panel and (plan 08) sitemaps.
        $this->callAfterResolving(SeoableRegistry::class, fn (SeoableRegistry $registry): SeoableRegistry => $registry
            ->register('post', Post::configuredClass(), 'shopper-seo::types.post', PostSource::class)
            ->register('page', Post::configuredClass(), 'shopper-seo::types.page', PostSource::class)
            ->register('post_category', PostCategory::class, 'shopper-seo::types.post_category', TermSource::class)
            ->register('post_tag', PostTag::class, 'shopper-seo::types.post_tag', TermSource::class)
            ->resolvePathsWith(CmsPathResolver::class));

        // The admin boots its addons from this panel, so the addon must be attached
        // as soon as the panel exists, whatever order the providers boot in.
        $this->callAfterResolving('shopper', fn (ShopperPanel $panel): ShopperPanel => $panel->addon(new CmsAddon));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'shopper-cms');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shopper-cms');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if (config('shopper.cms.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        // Optional public pages: a fallback route, so the application's own routes always win.
        if (config('shopper.cms.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views/public', 'shopper-cms-public');
        $this->publishes([
            __DIR__.'/../resources/views/public' => resource_path('views/vendor/shopper-cms-public'),
        ], 'shopper-cms-views');

        $this->flushCacheOnChanges();

        $this->publishes([
            __DIR__.'/../config/cms.php' => config_path('shopper/cms.php'),
        ], 'shopper-config');

        // TinyMCE loads its plugins and skins by URL, so the whole folder is published to public/.
        $this->publishes([
            __DIR__.'/../dist/tinymce' => public_path(config('shopper.cms.editor.base_path')),
        ], 'shopper-cms-assets');

        FilamentAsset::register([
            AlpineComponent::make('tiny-editor', __DIR__.'/../dist/tiny-editor.js'),
        ], 'shopper/cms');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EmptyTrashCommand::class,
                PublishScheduledPostsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PublishScheduledPostsCommand::class)->everyMinute()->withoutOverlapping();
            $schedule->command(EmptyTrashCommand::class)->daily();
        });
    }

    /**
     * Cached API responses are dropped whenever CMS content changes.
     */
    private function flushCacheOnChanges(): void
    {
        foreach ([Post::configuredClass(), PostCategory::class, PostTag::class, Redirect::class] as $model) {
            foreach (['saved', 'deleted', 'restored', 'forceDeleted'] as $event) {
                $this->app['events']->listen("eloquent.{$event}: {$model}", fn () => CmsCache::flush());
            }
        }
    }
}
