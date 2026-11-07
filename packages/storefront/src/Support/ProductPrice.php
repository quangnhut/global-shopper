<?php

declare(strict_types=1);

namespace Shopper\Storefront\Support;

use Illuminate\Database\Eloquent\Model;
use Shopper\Core\Models\Price;
use Shopper\Core\Models\Product;

/**
 * The price a product card shows, in the store currency: the product's own price, or the cheapest
 * variant price when the product has none. Expects `prices` and `variants.prices` to be eager loaded.
 */
final class ProductPrice
{
    /**
     * Relations to eager load before calling `for()`.
     */
    public const array WITH = ['prices', 'variants.prices'];

    /**
     * @return array{amount: int, compare_amount: ?int, currency: string}|null
     */
    public static function for(Model $product): ?array
    {
        if (! $product instanceof Product) {
            return null;
        }

        $currencyId = shopper_setting('default_currency_id');
        $inCurrency = fn (Price $price): bool => $price->amount !== null
            && ($currencyId === null || (int) $price->currency_id === (int) $currencyId);

        $price = $product->prices->first($inCurrency)
            ?? $product->variants->flatMap->prices->filter($inCurrency)->sortBy('amount')->first();

        if (! $price instanceof Price) {
            return null;
        }

        $compare = $price->compare_amount !== null && $price->compare_amount > $price->amount ? (int) $price->compare_amount : null;

        return [
            'amount' => (int) $price->amount,
            'compare_amount' => $compare,
            'currency' => shopper_currency(),
        ];
    }

    /**
     * Whole percent off the compare price, e.g. 23 for "-23%".
     */
    public static function discountPercent(int $amount, ?int $compareAmount): ?int
    {
        if ($compareAmount === null || $compareAmount <= $amount || $compareAmount === 0) {
            return null;
        }

        return (int) round(($compareAmount - $amount) * 100 / $compareAmount);
    }
}
