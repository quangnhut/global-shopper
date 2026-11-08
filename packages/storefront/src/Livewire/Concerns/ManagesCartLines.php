<?php

declare(strict_types=1);

namespace Shopper\Storefront\Livewire\Concerns;

use Livewire\Attributes\Computed;
use Shopper\Cart\Exceptions\CartCompletedException;
use Shopper\Cart\Models\Cart;
use Shopper\Cart\Models\CartLine;
use Shopper\Storefront\Actions\RefreshCartAction;
use Shopper\Storefront\Actions\UpdateCartLineAction;
use Shopper\Storefront\Cart\CartException;
use Shopper\Storefront\Cart\StorefrontCart;
use Shopper\Storefront\Data\CartLineData;
use Shopper\Storefront\Support\CartPresenter;

/**
 * Quantity stepper, remove and totals, shared by the cart and checkout pages.
 * The browser only sends line ids and quantities; prices come from the database.
 *
 * @property-read ?Cart $cart
 * @property-read array<int, CartLineData> $lines
 * @property-read array{subtotal: int, discount: int, tax: int, total: int, count: int, currency: string} $summary
 */
trait ManagesCartLines
{
    /** @var array<int, string> */
    public array $notices = [];

    public function refreshCart(): void
    {
        $cart = resolve(StorefrontCart::class)->current();

        if ($cart instanceof Cart) {
            $this->notices = resolve(RefreshCartAction::class)->execute($cart);
        }

        $this->forgetCart();
    }

    public function setQuantity(int $lineId, mixed $quantity): void
    {
        $this->changeLine($lineId, max(0, min(StorefrontCart::MAX_QUANTITY, (int) $quantity)));
    }

    public function increment(int $lineId): void
    {
        $line = $this->findLine($lineId);

        if ($line !== null) {
            $this->changeLine($lineId, min(StorefrontCart::MAX_QUANTITY, $line->quantity + 1));
        }
    }

    public function decrement(int $lineId): void
    {
        $line = $this->findLine($lineId);

        if ($line !== null && $line->quantity > 1) {
            $this->changeLine($lineId, $line->quantity - 1);
        }
    }

    public function remove(int $lineId): void
    {
        $this->changeLine($lineId, 0);
    }

    #[Computed]
    public function cart(): ?Cart
    {
        $cart = resolve(StorefrontCart::class)->current();

        return $cart !== null ? resolve(CartPresenter::class)->load($cart) : null;
    }

    /**
     * @return array<int, CartLineData>
     */
    #[Computed]
    public function lines(): array
    {
        return $this->cart !== null ? resolve(CartPresenter::class)->lines($this->cart) : [];
    }

    /**
     * @return array{subtotal: int, discount: int, tax: int, total: int, count: int, currency: string}
     */
    #[Computed]
    public function summary(): array
    {
        return resolve(CartPresenter::class)->summary($this->cart);
    }

    private function changeLine(int $lineId, int $quantity): void
    {
        $cart = resolve(StorefrontCart::class)->current();

        if (! $cart instanceof Cart) {
            $this->forgetCart();

            return;
        }

        $this->resetErrorBag('lines.'.$lineId);

        try {
            $kept = resolve(UpdateCartLineAction::class)->execute($cart, $lineId, $quantity);

            if ($kept > 0 && $kept < $quantity) {
                $this->addError('lines.'.$lineId, __('shopper-storefront::cart.errors.only_left', ['quantity' => $kept]));
            }
        } catch (CartException $exception) {
            $this->addError('lines.'.$lineId, $exception->getMessage());
        } catch (CartCompletedException) {
            $this->addError('cart', __('shopper-storefront::cart.errors.completed'));
        }

        $this->forgetCart();
        $this->dispatch('cart-updated');
    }

    private function findLine(int $lineId): ?CartLine
    {
        return $this->cart?->lines->firstWhere('id', $lineId);
    }

    private function forgetCart(): void
    {
        unset($this->cart, $this->lines, $this->summary);
    }
}
