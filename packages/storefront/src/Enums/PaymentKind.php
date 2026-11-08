<?php

declare(strict_types=1);

namespace Shopper\Storefront\Enums;

/**
 * The three ways to pay at checkout (`shopper.storefront.checkout.payment_methods`).
 */
enum PaymentKind: string
{
    case Office = 'office';
    case BankTransfer = 'bank_transfer';
    case OnePay = 'onepay';

    public static function fromSlug(?string $slug): ?self
    {
        foreach (self::cases() as $kind) {
            if ($slug !== null && $kind->slug() === $slug) {
                return $kind;
            }
        }

        return null;
    }

    public function slug(): string
    {
        return (string) config("shopper.storefront.checkout.payment_methods.{$this->value}", $this->value);
    }

    public function isOnline(): bool
    {
        return $this === self::OnePay;
    }
}
