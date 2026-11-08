<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Shopper\Cart\CartManager;
use Shopper\Cart\Models\CartLine;
use Shopper\Core\Models\Product;
use Shopper\Core\Models\ProductVariant;
use Shopper\Storefront\Cart\CartException;
use Shopper\Storefront\Cart\ChooseVariantException;
use Shopper\Storefront\Cart\StorefrontCart;

/**
 * Puts a product (or one of its variants) in the session cart. The quantity is
 * capped at what is in stock, and the price always comes from the database.
 */
final readonly class AddToCartAction
{
    public function __construct(
        private CartManager $cartManager,
        private StorefrontCart $carts,
    ) {}

    /**
     * @return array{line: CartLine, added: int, capped: bool}
     */
    public function execute(int $productId, ?int $variantId = null, int $quantity = 1): array
    {
        /** @var Product|null $product */
        $product = resolve(config('shopper.models.product', Product::class))::query()
            ->with(['prices', 'variants.prices'])
            ->find($productId);

        if (! $product instanceof Product || ! $product->isPublished()) {
            throw CartException::unavailable();
        }

        $purchasable = $this->purchasable($product, $variantId);
        $currency = shopper_currency();

        if ($purchasable->getPrice($currency)?->amount === null) {
            throw CartException::unavailable();
        }

        $cart = $this->carts->currentOrCreate();
        $inCart = (int) $cart->lines()
            ->where('purchasable_type', $purchasable->getMorphClass())
            ->where('purchasable_id', $purchasable->getKey())
            ->value('quantity');

        $room = StorefrontCart::available($purchasable) - $inCart;

        if ($room <= 0) {
            throw CartException::outOfStock(StorefrontCart::displayName($purchasable));
        }

        $added = min(max(1, $quantity), $room);
        $line = $this->cartManager->add($cart, $purchasable, $added);

        return ['line' => $line, 'added' => $added, 'capped' => $added < $quantity];
    }

    private function purchasable(Product $product, ?int $variantId): Product|ProductVariant
    {
        if ($variantId !== null) {
            $variant = $product->variants->firstWhere('id', $variantId);

            if (! $variant instanceof ProductVariant) {
                throw CartException::unavailable();
            }

            return $variant;
        }

        if ($product->variants->isEmpty()) {
            return $product;
        }

        $currency = shopper_currency();
        $choices = $product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->getPrice($currency)?->amount !== null)
            ->values();

        if ($choices->count() === 1) {
            return $choices->first();
        }

        if ($choices->isEmpty()) {
            throw CartException::unavailable();
        }

        throw new ChooseVariantException($product->name, $choices->map(fn (ProductVariant $variant): array => [
            'id' => $variant->id,
            'name' => (string) $variant->name,
            'price' => shopper_money_format((int) $variant->getPrice($currency)?->amount, $currency),
            'in_stock' => StorefrontCart::available($variant) > 0,
        ])->all());
    }
}
