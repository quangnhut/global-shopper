<?php

declare(strict_types=1);

namespace Shopper\Cms;

use Illuminate\Support\Facades\Blade;
use Shopper\Addon\BaseAddon;
use Shopper\Cms\Livewire\Components\DashboardOverview;
use Shopper\Cms\Livewire\Pages;
use Shopper\Cms\Settings\PermalinkSetting;
use Shopper\Cms\Settings\RedirectSetting;
use Shopper\Cms\Sidebar\ContentSidebar;
use Shopper\ShopperPanel;
use Shopper\View\LayoutRenderHook;

final class CmsAddon extends BaseAddon
{
    public function getId(): string
    {
        return 'cms';
    }

    public function register(ShopperPanel $panel): void
    {
        $panel
            ->addonRoutes(function (): void {
                require __DIR__.'/../routes/admin.php';
            })
            ->addonSidebar(ContentSidebar::class)
            ->addonLivewireComponents([
                'cms.posts-index' => Pages\Posts\Index::class,
                'cms.posts-editor' => Pages\Posts\Editor::class,
                'cms.pages-index' => Pages\Pages\Index::class,
                'cms.pages-editor' => Pages\Pages\Editor::class,
                'cms.post-revisions' => Pages\Posts\Revisions::class,
                'cms.post-categories-index' => Pages\PostCategories\Index::class,
                'cms.post-tags-index' => Pages\PostTags\Index::class,
                'cms.dashboard-overview' => DashboardOverview::class,
                'cms.settings-permalinks' => Pages\Settings\Permalinks::class,
                'cms.settings-redirects' => Pages\Settings\Redirects::class,
            ])
            ->addonSettingItems([PermalinkSetting::class => true, RedirectSetting::class => true])
            ->renderHook(
                LayoutRenderHook::DASHBOARD_END,
                fn (): string => Blade::render('<livewire:shopper-cms.dashboard-overview />'),
            );
    }
}
