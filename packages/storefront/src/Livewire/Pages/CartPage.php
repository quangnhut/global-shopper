<?php

declare(strict_types=1);

namespace Shopper\Storefront\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Shopper\Storefront\Livewire\Concerns\ManagesCartLines;

/**
 * The /cart page body (the controller wraps it in the storefront layout).
 */
class CartPage extends Component
{
    use ManagesCartLines;

    public function mount(): void
    {
        $this->refreshCart();
    }

    public function render(): View
    {
        return view('shopper-storefront::livewire.cart-page');
    }
}
