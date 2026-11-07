<?php

declare(strict_types=1);

namespace Shopper\Storefront\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Shopper\Core\Models\Product;
use Shopper\Seo\Support\SeoUrls;
use Shopper\Storefront\Support\ProductImage;
use Shopper\Storefront\Support\ProductPrice;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ProductCardData implements Arrayable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $url,
        public ?string $image,
        public int $amount,
        public ?int $compareAmount,
        public ?int $discountPercent,
        public string $currency,
        public bool $inStock,
    ) {}

    /**
     * Expects `ProductPrice::WITH`, `media` and (for stock) `real_stock` loaded on the product and its variants.
     */
    public static function fromModel(Model $product): ?self
    {
        $price = ProductPrice::for($product);

        if (! $product instanceof Product || $price === null) {
            return null;
        }

        return new self(
            id: (int) $product->id,
            name: (string) $product->name,
            url: SeoUrls::entity('product', (string) $product->slug),
            image: ProductImage::url($product),
            amount: $price['amount'],
            compareAmount: $price['compare_amount'],
            discountPercent: ProductPrice::discountPercent($price['amount'], $price['compare_amount']),
            currency: $price['currency'],
            inStock: self::inStock($product),
        );
    }

    public function formattedPrice(): string
    {
        return shopper_money_format($this->amount, $this->currency);
    }

    public function formattedComparePrice(): ?string
    {
        return $this->compareAmount === null ? null : shopper_money_format($this->compareAmount, $this->currency);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'image' => $this->image,
            'price' => ['amount' => $this->amount, 'formatted' => $this->formattedPrice()],
            'compare_price' => $this->compareAmount === null ? null : ['amount' => $this->compareAmount, 'formatted' => $this->formattedComparePrice()],
            'discount_percent' => $this->discountPercent,
            'currency' => $this->currency,
            'in_stock' => $this->inStock,
        ];
    }

    private static function inStock(Product $product): bool
    {
        if ($product->allow_backorder || $product->isVirtual() || $product->isExternal()) {
            return true;
        }

        if ($product->variants->isNotEmpty()) {
            return $product->variants->contains(fn (Model $variant): bool => (int) $variant->getAttribute('stock') > 0);
        }

        return (int) $product->getAttribute('stock') > 0;
    }
}
