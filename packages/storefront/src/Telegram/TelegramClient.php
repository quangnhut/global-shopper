<?php

declare(strict_types=1);

namespace Shopper\Storefront\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends Bot API messages. Errors never include the bot token, which is part of the URL.
 */
final readonly class TelegramClient
{
    public function __construct(private TelegramConfig $config) {}

    /**
     * Sends the HTML message to every configured chat.
     */
    public function broadcast(string $html): void
    {
        foreach ($this->config->chatIds() as $chatId) {
            $this->sendMessage($chatId, $html);
        }
    }

    public function sendMessage(string $chatId, string $html): void
    {
        $payload = array_filter([
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'message_thread_id' => $this->config->messageThreadId(),
            'link_preview_options' => ['is_disabled' => true],
        ], fn (mixed $value): bool => $value !== null);

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->post('https://api.telegram.org/bot'.$this->config->botToken().'/sendMessage', $payload);
        } catch (ConnectionException) {
            throw new TelegramException("Telegram could not be reached (chat {$chatId}).");
        }

        if (! $response->successful()) {
            $description = (string) ($response->json('description') ?? 'no description');

            throw new TelegramException("Telegram refused the message for chat {$chatId}: HTTP {$response->status()}, {$description}");
        }
    }
}
