<?php

declare(strict_types=1);

namespace Shopper\Storefront\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Shopper\Core\Models\Contracts\Order as OrderContract;
use Shopper\Core\Models\Order;
use Shopper\Storefront\Telegram\OrderTelegramMessage;
use Shopper\Storefront\Telegram\TelegramClient;
use Shopper\Storefront\Telegram\TelegramConfig;
use Shopper\Storefront\Telegram\TelegramException;
use Throwable;

/**
 * Tells the shop about an order on Telegram. A failure is logged and never
 * reaches the customer, even when the queue runs jobs inline (sync).
 */
final class SendTelegramOrderMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
        public string $event = OrderTelegramMessage::NEW,
        public ?string $detail = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(TelegramConfig $config, TelegramClient $client, OrderTelegramMessage $message): void
    {
        if (! $config->isEnabled()) {
            return;
        }

        /** @var Order|null $order */
        $order = resolve(OrderContract::class)::query()->find($this->orderId);

        if ($order === null) {
            return;
        }

        try {
            $client->broadcast($message->build($order, $this->event, $this->detail));
        } catch (TelegramException $exception) {
            Log::warning($exception->getMessage(), ['order' => $order->number, 'event' => $this->event]);

            if ($this->job !== null && $this->attempts() < $this->tries && ! $this->runsInline()) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 60);
            }
        } catch (Throwable $exception) {
            Log::error('Telegram order alert failed.', ['order' => $order->number, 'error' => $exception->getMessage()]);
        }
    }

    private function runsInline(): bool
    {
        return $this->job?->getConnectionName() === 'sync';
    }
}
