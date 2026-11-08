<?php

declare(strict_types=1);

namespace Shopper\Storefront\Telegram;

use Illuminate\Support\Traits\Localizable;
use Shopper\Core\Models\Order;
use Shopper\Core\Models\OrderItem;

/**
 * Builds the HTML text of the order alerts. Everything that came from a customer is escaped.
 */
final readonly class OrderTelegramMessage
{
    use Localizable;

    public const string NEW = 'new';

    public const string PAID = 'paid';

    public const string PAYMENT_FAILED = 'payment_failed';

    /** Telegram rejects longer messages. */
    public const int MAX_LENGTH = 4096;

    public function __construct(private TelegramConfig $config) {}

    /**
     * Written in the storefront language, whatever the language of the request that placed the order.
     */
    public function build(Order $order, string $event = self::NEW, ?string $detail = null): string
    {
        $locale = (string) config('shopper.storefront.locale', 'vi');

        return $this->withLocale($locale, fn (): string => $this->compose($order, $event, $detail, $locale));
    }

    private function compose(Order $order, string $event, ?string $detail, string $locale): string
    {
        $order->loadMissing(['items', 'paymentMethod']);

        $trans = fn (string $key, array $replace = []): string => __("shopper-storefront::telegram.{$key}", $replace, $locale);
        $money = fn (int $amount): string => e(shopper_money_format($amount, $order->currency_code));
        $number = e($order->number);
        $method = e($order->paymentMethod->title ?? '—');

        $lines = match ($event) {
            self::PAID => [
                '✅ <b>'.$trans('paid', ['number' => $number]).'</b>',
                $trans('payment', ['method' => $method]).' · '.$money($order->price_amount),
            ],
            self::PAYMENT_FAILED => array_filter([
                '⚠️ <b>'.$trans('payment_failed', ['number' => $number]).'</b>',
                $trans('payment', ['method' => $method]),
                $detail !== null ? e($detail) : null,
            ]),
            default => $this->newOrderLines($order, $trans, $money, $method),
        };

        $url = $this->config->adminOrderUrl($order->id);

        if ($url !== null) {
            $lines[] = '<a href="'.e($url).'">'.$trans('view').'</a>';
        }

        return $this->truncate(implode("\n", $lines));
    }

    /**
     * @param  callable(string, array<string, string>=): string  $trans
     * @param  callable(int): string  $money
     * @return array<int, string>
     */
    private function newOrderLines(Order $order, callable $trans, callable $money, string $method): array
    {
        $contact = (array) ($order->metadata['contact'] ?? []);
        $customer = implode(' · ', array_filter([
            e((string) ($contact['name'] ?? '')),
            e((string) ($contact['phone'] ?? '')),
            e((string) ($contact['email'] ?? '')),
        ]));

        $lines = [
            '🛒 <b>'.$trans('new', ['number' => e($order->number)]).'</b>',
            $trans('customer', ['customer' => $customer !== '' ? $customer : '—']),
            $trans('payment', ['method' => $method]).' ('.e((string) $order->payment_status->getLabel()).')',
        ];

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $lines[] = '— '.e($item->name).' x'.$item->quantity.' · '.$money($item->unit_price_amount * $item->quantity);
        }

        $lines[] = '<b>'.$trans('total', ['total' => $money($order->price_amount)]).'</b>';

        if (filled($order->notes)) {
            $lines[] = $trans('note', ['note' => e($order->notes)]);
        }

        return $lines;
    }

    /**
     * Cuts on a line break so no HTML tag is left open.
     */
    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::MAX_LENGTH - 2);
        $lastBreak = mb_strrpos($cut, "\n");

        return ($lastBreak !== false ? mb_substr($cut, 0, $lastBreak) : strip_tags($cut))."\n…";
    }
}
