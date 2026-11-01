<?php

declare(strict_types=1);

namespace Shopper\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Shopper\Addon\BaseAddon;
use Shopper\Seo\Livewire\Components\EntrySeoPanel;
use Shopper\Seo\Livewire\Pages;
use Shopper\Seo\Sidebar\SeoSidebar;
use Shopper\ShopperPanel;
use Shopper\View\ProductRenderHook;

final class SeoAddon extends BaseAddon
{
    public function getId(): string
    {
        return 'seo';
    }

    public function register(ShopperPanel $panel): void
    {
        $panel
            ->addonRoutes(function (): void {
                require __DIR__.'/../routes/admin.php';
            })
            ->addonSidebar(SeoSidebar::class)
            ->addonLivewireComponents([
                'seo.settings' => Pages\SeoSettingsPage::class,
                'seo.content' => Pages\ContentSeo::class,
                'seo.entry-panel' => EntrySeoPanel::class,
            ])
            // "Advanced SEO" tab on the product edit page; the product's own SEO tab keeps title/description.
            ->renderHook(ProductRenderHook::EDIT_TABS_END, fn (): string => $this->productId() === null ? '' : Blade::render(
                '<x-filament::tabs.item alpine-active="activeTab === \'advanced-seo\'" x-on:click="activeTab = \'advanced-seo\'" icon="untitledui-search-md">{{ $label }}</x-filament::tabs.item>',
                ['label' => __('shopper-seo::panel.advanced_tab')],
            ))
            ->renderHook(ProductRenderHook::EDIT_CONTENT_AFTER, fn (): string => $this->productId() === null ? '' : Blade::render(
                '<div x-cloak x-show="activeTab === \'advanced-seo\'"><livewire:shopper-seo.entry-panel type="product" :record-id="$id" :with-basics="false" /></div>',
                ['id' => $this->productId()],
            ));
    }

    /**
     * Render hooks receive no context: the product comes from the current route, and the tab is only
     * shown to users who manage SEO.
     */
    private function productId(): int|string|null
    {
        if (! shopper()->auth()->user()?->can('manage_seo')) {
            return null;
        }

        $product = request()->route('product');

        return $product instanceof Model ? $product->getKey() : (is_scalar($product) ? $product : null);
    }
}
