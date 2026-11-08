<?php

declare(strict_types=1);

namespace Shopper\Storefront\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Shopper\Cart\Models\CartLine;
use Shopper\Core\Models\ProductVariant;
use Shopper\Seo\Support\SeoUrls;
use Shopper\Storefront\Cart\StorefrontCart;
use Shopper\Storefront\Support\ProductImage;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class CartLineData implements Arrayable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $variant,
        public ?string $image,
        public ?string $url,
        public int $unitPrice,
        public int $quantity,
        public int $maxQuantity,
        public int $total,
        public string $currency,
    ) {}

    public static function fromLine(CartLine $line, string $currency): self
    {
        /** @var Model|null $purchasable null when the product was deleted */
        $purchasable = $line->purchasable;
        $product = StorefrontCart::productOf($purchasable);

        return new self(
            id: $line->id,
            name: (string) ($product->name ?? $purchasable?->getAttribute('name') ?? ''),
            variant: $purchasable instanceof ProductVariant ? (string) $purchasable->name : null,
            image: $product !== null ? ProductImage::url($product) : null,
            url: $product !== null ? SeoUrls::entity('product', (string) $product->slug) : null,
            unitPrice: (int) $line->unit_price_amount,
            quantity: $line->quantity,
            maxQuantity: $purchasable !== null ? max($line->quantity, StorefrontCart::available($purchasable)) : $line->quantity,
            total: (int) ($line->unit_price_amount * $line->quantity),
            currency: $currency,
        );
    }

    public function formattedUnitPrice(): string
    {
        return shopper_money_format($this->unitPrice, $this->currency);
    }

    public function formattedTotal(): string
    {
        return shopper_money_format($this->total, $this->currency);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'variant' => $this->variant,
            'image' => $this->image,
            'url' => $this->url,
            'unit_price' => $this->unitPrice,
            'unit_price_formatted' => $this->formattedUnitPrice(),
            'quantity' => $this->quantity,
            'max_quantity' => $this->maxQuantity,
            'total' => $this->total,
            'total_formatted' => $this->formattedTotal(),
        ];
    }
}
