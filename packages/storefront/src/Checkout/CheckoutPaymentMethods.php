<?php

declare(strict_types=1);

namespace Shopper\Storefront\Checkout;

use Illuminate\Support\Collection;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Payment\Facades\Payment;
use Shopper\Storefront\Enums\PaymentKind;

/**
 * Payment methods the checkout offers: enabled, listed in the config, and for
 * OnePay only when its credentials are set.
 */
final class CheckoutPaymentMethods
{
    public static function kindOf(?PaymentMethod $method): ?PaymentKind
    {
        return PaymentKind::fromSlug($method?->slug);
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    public function available(): Collection
    {
        $slugs = array_map(fn (PaymentKind $kind): string => $kind->slug(), PaymentKind::cases());

        return PaymentMethod::query()
            ->enabled()
            ->whereIn('slug', $slugs)
            ->get()
            ->filter(fn (PaymentMethod $method): bool => $this->isUsable($method))
            ->sortBy(fn (PaymentMethod $method): int|false => array_search($method->slug, $slugs, true))
            ->values();
    }

    public function find(?string $slug): ?PaymentMethod
    {
        return $this->available()->firstWhere('slug', $slug);
    }

    private function isUsable(PaymentMethod $method): bool
    {
        $kind = self::kindOf($method);

        return match ($kind) {
            PaymentKind::OnePay => (bool) config('shopper.payment.drivers.onepay.enabled', false)
                && Payment::isConfigured($method->driver ?? 'onepay'),
            null => false,
            default => true,
        };
    }
}
