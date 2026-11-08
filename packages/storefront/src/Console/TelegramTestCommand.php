<?php

declare(strict_types=1);

namespace Shopper\Storefront\Console;

use Illuminate\Console\Command;
use Shopper\Storefront\Telegram\TelegramClient;
use Shopper\Storefront\Telegram\TelegramConfig;
use Shopper\Storefront\Telegram\TelegramException;

final class TelegramTestCommand extends Command
{
    protected $signature = 'shopper:storefront:telegram-test';

    protected $description = 'Send a test message to the Telegram chats of the order alerts';

    public function handle(TelegramConfig $config, TelegramClient $client): int
    {
        if (! $config->isEnabled()) {
            $this->components->error("Telegram alerts are off. Check {$config->path()} (enabled, bot_token, chat_ids).");

            return self::FAILURE;
        }

        try {
            $client->broadcast(e(__('shopper-storefront::telegram.test', ['app' => config('app.name')], (string) config('shopper.storefront.locale', 'vi'))));
        } catch (TelegramException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Sent to '.count($config->chatIds()).' chat(s).');

        return self::SUCCESS;
    }
}
