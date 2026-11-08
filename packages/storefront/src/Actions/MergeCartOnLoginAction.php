<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Shopper\Cart\CartManager;
use Shopper\Cart\CartSessionManager;
use Shopper\Cart\Models\Cart;
use Shopper\Cart\Models\CartLine;
use Shopper\Core\Contracts\Priceable;
use Shopper\Storefront\Cart\StorefrontCart;
use Throwable;

/**
 * After sign-in: the guest cart becomes the customer's. When the customer already
 * had an open cart from an earlier visit, the guest lines are moved into it.
 */
final readonly class MergeCartOnLoginAction
{
    public function __construct(
        private CartManager $cartManager,
        private CartSessionManager $sessions,
        private StorefrontCart $carts,
    ) {}

    public function execute(Authenticatable $user): ?Cart
    {
        $customerId = $user->getAuthIdentifier();
        $guest = $this->carts->current();

        $saved = Cart::query()
            ->where('customer_id', $customerId)
            ->whereNull('completed_at')
            ->when($guest !== null, fn ($query) => $query->whereKeyNot($guest->id))
            ->latest('updated_at')
            ->first();

        if ($guest === null) {
            if ($saved instanceof Cart) {
                $this->sessions->use($saved);
            }

            return $saved;
        }

        if (! $saved instanceof Cart) {
            $guest->update(['customer_id' => $customerId]);

            return $guest;
        }

        foreach ($guest->lines()->with('purchasable')->get() as $line) {
            /** @var CartLine $line */
            $purchasable = $line->purchasable;

            if (! $purchasable instanceof Priceable || ! StorefrontCart::isSellable($purchasable)) {
                continue;
            }

            try {
                $inSaved = (int) $saved->lines()->where('purchasable_type', $line->purchasable_type)->where('purchasable_id', $line->purchasable_id)->value('quantity');
                $quantity = min($line->quantity, StorefrontCart::available($purchasable) - $inSaved);

                if ($quantity > 0) {
                    $this->cartManager->add($saved, $purchasable, $quantity);
                }
            } catch (Throwable) {
                // A line that cannot move (stock gone…) is dropped; the cart page says what is left.
            }
        }

        $guest->lines()->delete();
        $guest->delete();
        $this->sessions->use($saved);

        return $saved;
    }
}
