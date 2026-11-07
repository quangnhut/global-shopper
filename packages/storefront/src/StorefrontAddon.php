<?php

declare(strict_types=1);

namespace Shopper\Storefront;

use Shopper\Addon\BaseAddon;
use Shopper\ShopperPanel;
use Shopper\Storefront\Livewire\Pages;
use Shopper\Storefront\Sidebar\StorefrontSidebar;

final class StorefrontAddon extends BaseAddon
{
    public function getId(): string
    {
        return 'storefront';
    }

    public function register(ShopperPanel $panel): void
    {
        $panel
            ->addonRoutes(function (): void {
                require __DIR__.'/../routes/admin.php';
            })
            ->addonSidebar(StorefrontSidebar::class)
            ->addonLivewireComponents([
                'storefront.sections-index' => Pages\Sections\Index::class,
                'storefront.sections-editor' => Pages\Sections\Editor::class,
                'storefront.settings' => Pages\HeaderFooterSettings::class,
            ]);
    }
}
