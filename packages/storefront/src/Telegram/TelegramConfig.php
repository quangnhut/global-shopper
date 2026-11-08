<?php

declare(strict_types=1);

namespace Shopper\Storefront\Telegram;

use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Telegram settings read from a JSON file kept out of git, so production and
 * localhost each use their own bot and chat (`shopper.storefront.telegram.config_path`).
 */
final class TelegramConfig
{
    /** @var array{enabled: bool, bot_token: string, chat_ids: array<int, string>, message_thread_id: ?int, admin_order_url: ?string}|null */
    private ?array $values = null;

    private bool $loaded = false;

    public function __construct(private readonly ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? (string) config('shopper.storefront.telegram.config_path');
    }

    public function isEnabled(): bool
    {
        $values = $this->values();

        return $values !== null
            && $values['enabled']
            && $values['bot_token'] !== ''
            && $values['chat_ids'] !== [];
    }

    public function botToken(): string
    {
        return $this->values()['bot_token'] ?? '';
    }

    /**
     * @return array<int, string>
     */
    public function chatIds(): array
    {
        return $this->values()['chat_ids'] ?? [];
    }

    public function messageThreadId(): ?int
    {
        return $this->values()['message_thread_id'] ?? null;
    }

    public function adminOrderUrl(int $orderId): ?string
    {
        $template = $this->values()['admin_order_url'] ?? null;

        if ($template === null || $template === '') {
            return null;
        }

        return str_replace('{id}', (string) $orderId, $template);
    }

    /**
     * @return array{enabled: bool, bot_token: string, chat_ids: array<int, string>, message_thread_id: ?int, admin_order_url: ?string}|null
     */
    private function values(): ?array
    {
        if ($this->loaded) {
            return $this->values;
        }

        $this->loaded = true;
        $path = $this->path();

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            Log::warning('Telegram order alerts are off: config file not found.', ['path' => $path]);

            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('Telegram order alerts are off: config file is not valid JSON.', ['path' => $path, 'error' => $exception->getMessage()]);

            return null;
        }

        if (! is_array($data)) {
            Log::warning('Telegram order alerts are off: config file must hold a JSON object.', ['path' => $path]);

            return null;
        }

        $chatIds = $data['chat_ids'] ?? (isset($data['chat_id']) ? [$data['chat_id']] : []);
        $threadId = $data['message_thread_id'] ?? null;

        return $this->values = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'bot_token' => is_string($data['bot_token'] ?? null) ? trim($data['bot_token']) : '',
            'chat_ids' => array_values(array_filter(
                array_map(fn (mixed $id): string => is_scalar($id) ? trim((string) $id) : '', is_array($chatIds) ? $chatIds : [$chatIds]),
                fn (string $id): bool => $id !== '',
            )),
            'message_thread_id' => is_numeric($threadId) ? (int) $threadId : null,
            'admin_order_url' => is_string($data['admin_order_url'] ?? null) ? $data['admin_order_url'] : null,
        ];
    }
}
