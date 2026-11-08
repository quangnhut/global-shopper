<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Shopper\Cart\Models\Cart;
use Shopper\Cart\Models\CartLine;
use Shopper\Core\Contracts\Priceable;
use Shopper\Core\Models\ProductVariant;
use Shopper\Storefront\Cart\StorefrontCart;

/**
 * Brings the cart in line with the catalogue before it is shown or ordered:
 * unpublished or deleted products are removed, prices follow the database,
 * quantities are capped at the stock left.
 *
 * Returns what changed, as messages for the customer.
 */
final class RefreshCartAction
{
    /**
     * @return array<int, string>
     */
    public function execute(Cart $cart): array
    {
        return DB::transaction(function () use ($cart): array {
            $notices = [];
            $currency = $cart->currency_code;

            $cart->load('lines.purchasable');
            $cart->lines->loadMorph('purchasable', [ProductVariant::class => ['product', 'prices'], config('shopper.models.product') => ['prices']]);

            foreach ($cart->lines as $line) {
                /** @var CartLine $line */
                /** @var Model|null $purchasable null when the product was deleted */
                $purchasable = $line->purchasable;
                $price = $purchasable instanceof Priceable ? $purchasable->getPrice($currency)?->amount : null;

                if ($purchasable === null || ! StorefrontCart::isSellable($purchasable) || $price === null) {
                    $notices[] = __('shopper-storefront::cart.notices.removed', ['name' => $purchasable !== null ? StorefrontCart::displayName($purchasable) : __('shopper-storefront::cart.a_product')]);
                    $line->delete();

                    continue;
                }

                $name = StorefrontCart::displayName($purchasable);
                $available = StorefrontCart::available($purchasable);

                if ($available === 0) {
                    $notices[] = __('shopper-storefront::cart.notices.sold_out', ['name' => $name]);
                    $line->delete();

                    continue;
                }

                $changes = [];

                if ($line->quantity > $available) {
                    $changes['quantity'] = $available;
                    $notices[] = __('shopper-storefront::cart.notices.capped', ['name' => $name, 'quantity' => $available]);
                }

                if ((int) $line->unit_price_amount !== (int) $price) {
                    $changes['unit_price_amount'] = (int) $price;
                    $notices[] = __('shopper-storefront::cart.notices.price_changed', ['name' => $name, 'price' => shopper_money_format((int) $price, $currency)]);
                }

                if ($changes !== []) {
                    $line->update($changes);
                }
            }

            $cart->unsetRelation('lines');

            return $notices;
        });
    }
}
