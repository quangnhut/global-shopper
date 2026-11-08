<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shopper\Core\Models\Order;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Storefront\Jobs\SendTelegramOrderMessage;
use Shopper\Storefront\Telegram\OrderTelegramMessage;
use Shopper\Storefront\Telegram\TelegramClient;
use Shopper\Storefront\Telegram\TelegramConfig;
use Shopper\Storefront\Telegram\TelegramException;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/../Support/helpers.php';

function telegramConfigFile(array|string|null $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'telegram').'.json';

    if ($content !== null) {
        file_put_contents($path, is_array($content) ? json_encode($content) : $content);
    }

    config()->set('shopper.storefront.telegram.config_path', $path);
    app()->forgetScopedInstances();

    return $path;
}

function enabledTelegram(array $overrides = []): string
{
    return telegramConfigFile([
        'enabled' => true,
        'bot_token' => '123:SECRET-TOKEN',
        'chat_ids' => ['-100111', '-100222'],
        'admin_order_url' => 'https://shop.test/cpanel/orders/{id}',
        ...$overrides,
    ]);
}

function telegramOrder(array $attributes = []): Order
{
    $method = PaymentMethod::query()->create(['title' => 'OnePay', 'slug' => 'onepay', 'is_enabled' => true]);

    $order = Order::factory()->create([
        'number' => 'ORD-000123',
        'price_amount' => 548_000,
        'currency_code' => 'VND',
        'payment_method_id' => $method->id,
        'notes' => 'Lấy hàng <chiều> thứ 7',
        'metadata' => ['contact' => ['name' => 'Nguyễn <b>Văn</b> A', 'phone' => '0901234567', 'email' => 'a@example.com']],
        ...$attributes,
    ]);

    $order->items()->create(['name' => 'Áo thun & quần', 'sku' => 'A1', 'quantity' => 2, 'unit_price_amount' => 199_000, 'product_type' => 'product', 'product_id' => 1]);

    return $order;
}

beforeEach(function (): void {
    storefrontVnd();
    config()->set('shopper.storefront.locale', 'vi');
});

it('is off when the config file is missing', function (): void {
    Log::spy();
    telegramConfigFile(null);

    expect(resolve(TelegramConfig::class)->isEnabled())->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'not found'))->once();
});

it('is off when the config file is not valid JSON', function (): void {
    Log::spy();
    telegramConfigFile('{"enabled": true,');

    expect(resolve(TelegramConfig::class)->isEnabled())->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'not valid JSON'))->once();
});

it('is off when disabled or without chats', function (array $content): void {
    telegramConfigFile($content);

    expect(resolve(TelegramConfig::class)->isEnabled())->toBeFalse();
})->with([
    'disabled' => [['enabled' => false, 'bot_token' => '1:a', 'chat_ids' => ['1']]],
    'no chats' => [['enabled' => true, 'bot_token' => '1:a', 'chat_ids' => []]],
    'no token' => [['enabled' => true, 'bot_token' => '', 'chat_ids' => ['1']]],
]);

it('reads a single `chat_id` too', function (): void {
    telegramConfigFile(['enabled' => true, 'bot_token' => '1:a', 'chat_id' => -100999]);

    expect(resolve(TelegramConfig::class)->chatIds())->toBe(['-100999']);
});

it('builds the new order message with every customer value escaped', function (): void {
    enabledTelegram();
    $order = telegramOrder();

    $text = resolve(OrderTelegramMessage::class)->build($order);

    expect($text)
        ->toContain('Đơn hàng mới #ORD-000123')
        ->toContain('Nguyễn &lt;b&gt;Văn&lt;/b&gt; A · 0901234567 · a@example.com')
        ->toContain('Thanh toán: OnePay')
        ->toContain('— Áo thun &amp; quần x2 · 398.000')
        ->toContain('Tổng: 548.000')
        ->toContain('Lấy hàng &lt;chiều&gt; thứ 7')
        ->toContain('<a href="https://shop.test/cpanel/orders/'.$order->id.'">Xem đơn</a>')
        ->not->toContain('<b>Văn</b>');
});

it('builds the paid and failed messages', function (): void {
    enabledTelegram(['admin_order_url' => null]);
    $order = telegramOrder();
    $builder = resolve(OrderTelegramMessage::class);

    expect($builder->build($order, OrderTelegramMessage::PAID))->toContain('Đã thanh toán #ORD-000123')->toContain('548.000')->not->toContain('<a ')
        ->and($builder->build($order, OrderTelegramMessage::PAYMENT_FAILED, 'Thẻ <hết> hạn'))->toContain('Thanh toán thất bại #ORD-000123')->toContain('Thẻ &lt;hết&gt; hạn');
});

it('keeps the message under the Telegram limit without cutting a tag', function (): void {
    enabledTelegram();
    $order = telegramOrder();

    foreach (range(1, 120) as $index) {
        $order->items()->create(['name' => "Sản phẩm rất dài số {$index} ".str_repeat('x', 30), 'sku' => "S{$index}", 'quantity' => 1, 'unit_price_amount' => 1000, 'product_type' => 'product', 'product_id' => $index]);
    }

    $text = resolve(OrderTelegramMessage::class)->build($order->fresh());

    expect(mb_strlen($text))->toBeLessThanOrEqual(OrderTelegramMessage::MAX_LENGTH)
        ->and($text)->toEndWith("\n…")
        ->and(mb_substr_count($text, '<b>'))->toBe(mb_substr_count($text, '</b>'));
});

it('sends the order to every chat as HTML', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    enabledTelegram(['message_thread_id' => 7]);
    $order = telegramOrder();

    SendTelegramOrderMessage::dispatchSync($order->id);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot123:SECRET-TOKEN/sendMessage'
        && $request['chat_id'] === '-100111'
        && $request['parse_mode'] === 'HTML'
        && $request['message_thread_id'] === 7
        && str_contains($request['text'], 'ORD-000123'));
});

it('sends nothing when alerts are off', function (): void {
    Http::fake();
    telegramConfigFile(null);

    SendTelegramOrderMessage::dispatchSync(telegramOrder()->id);

    Http::assertNothingSent();
});

it('never leaks the bot token in errors', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);
    enabledTelegram();

    expect(fn () => resolve(TelegramClient::class)->sendMessage('-100111', 'hi'))
        ->toThrow(function (TelegramException $exception): void {
            expect($exception->getMessage())->toContain('chat not found')->not->toContain('SECRET-TOKEN');
        });
});

it('logs a failed send instead of throwing on a sync queue', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response([], 500)]);
    Log::spy();
    enabledTelegram();

    SendTelegramOrderMessage::dispatchSync(telegramOrder()->id);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'HTTP 500') && ! str_contains($message, 'SECRET'));
});

it('sends a test message from the console', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    enabledTelegram();

    $this->artisan('shopper:storefront:telegram-test')->assertSuccessful();

    Http::assertSentCount(2);
});

it('fails the console test when alerts are off', function (): void {
    telegramConfigFile(['enabled' => false]);

    $this->artisan('shopper:storefront:telegram-test')->assertFailed();
});
