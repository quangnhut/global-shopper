<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Database\Eloquent\Model;
use Shopper\Cart\CartManager;
use Shopper\Cart\Models\Cart;
use Shopper\Storefront\Cart\CartException;
use Shopper\Storefront\Cart\StorefrontCart;

/**
 * Changes the quantity of a line of the session cart (0 removes it).
 * Returns the quantity kept, which is capped at the stock left.
 */
final readonly class UpdateCartLineAction
{
    public function __construct(
        private CartManager $cartManager,
        private StorefrontCart $carts,
    ) {}

    public function execute(Cart $cart, int $lineId, int $quantity): int
    {
        $line = $this->carts->line($cart, $lineId);

        if ($quantity <= 0) {
            $this->cartManager->remove($cart, $line->id);

            return 0;
        }

        /** @var Model|null $purchasable null when the product was deleted */
        $purchasable = $line->purchasable;

        if ($purchasable === null || ! StorefrontCart::isSellable($purchasable)) {
            $this->cartManager->remove($cart, $line->id);

            throw CartException::unavailable();
        }

        $available = StorefrontCart::available($purchasable);

        if ($available === 0) {
            throw CartException::outOfStock(StorefrontCart::displayName($purchasable));
        }

        $kept = min($quantity, $available);
        $this->cartManager->update($cart, $line->id, ['quantity' => $kept]);

        return $kept;
    }
}
