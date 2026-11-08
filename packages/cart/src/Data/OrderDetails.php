<?php

declare(strict_types=1);

namespace Shopper\Cart\Data;

/**
 * What the checkout adds to the order created from a cart.
 */
final readonly class OrderDetails
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?int $paymentMethodId = null,
        public ?string $notes = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toOrderAttributes(): array
    {
        return array_filter([
            'payment_method_id' => $this->paymentMethodId,
            'notes' => $this->notes,
            'metadata' => $this->metadata !== [] ? $this->metadata : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
