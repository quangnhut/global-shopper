<?php

declare(strict_types=1);

namespace Shopper\Storefront\Livewire\Components;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Shopper\Cart\Models\Cart;
use Shopper\Cart\Models\CartLine;

/**
 * Header cart icon with the item count and a short list of lines. Reads the session cart
 * without creating one, so visitors who never add a product get no cart row.
 *
 * @property-read ?Cart $cart
 */
class MiniCart extends Component
{
    #[On('cart-updated')]
    public function refresh(): void
    {
        unset($this->cart);
    }

    #[Computed]
    public function cart(): ?Cart
    {
        $id = session((string) config('shopper.cart.session.key', 'shopper_cart'));

        if (! $id) {
            return null;
        }

        $cart = Cart::query()->with('lines.purchasable')->find($id);

        return $cart instanceof Cart && ! $cart->isCompleted() ? $cart : null;
    }

    public function count(): int
    {
        return (int) $this->cart?->lines->sum('quantity');
    }

    public function subtotal(): int
    {
        return (int) $this->cart?->lines->sum(fn (CartLine $line): float|int => $line->unit_price_amount * $line->quantity);
    }

    public function render(): View
    {
        return view('shopper-storefront::livewire.mini-cart');
    }
}
