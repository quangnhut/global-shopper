<?php

declare(strict_types=1);

namespace Shopper\Storefront;

use Shopper\Addon\BaseAddon;
use Shopper\Core\Models\Contracts\Order as OrderContract;
use Shopper\Core\Models\Order;
use Shopper\ShopperPanel;
use Shopper\Storefront\Livewire\Pages;
use Shopper\Storefront\Sidebar\StorefrontSidebar;
use Shopper\View\OrderRenderHook;

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
            ])
            // Checkout contact (name, phone, email), pickup and note on the order page.
            ->renderHook(OrderRenderHook::DETAIL_SIDEBAR_BEFORE, fn (): string => $this->orderContact());
    }

    /**
     * Render hooks receive no context: the order comes from the current route.
     */
    private function orderContact(): string
    {
        $order = request()->route('order');

        if (! $order instanceof Order) {
            $order = is_scalar($order) ? resolve(OrderContract::class)::query()->find($order) : null;
        }

        if (! $order instanceof Order || ($order->metadata['source'] ?? null) !== 'storefront') {
            return '';
        }

        return view('shopper-storefront::admin.order-contact', ['order' => $order->loadMissing('paymentMethod')])->render();
    }
}
