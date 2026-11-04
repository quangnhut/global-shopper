<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Shopper\Core\Models\Currency;
use Shopper\Core\Models\Order;
use Shopper\Core\Models\Setting;

uses(Tests\Core\TestCase::class);

function runVndMigration(): void
{
    $migration = require __DIR__.'/../../../packages/core/database/migrations/2026_11_05_000001_make_vnd_the_only_currency.php';

    $migration->up();
}

function createMigrationCurrency(string $code): int
{
    return (int) Currency::query()->firstOrCreate(
        ['code' => $code],
        ['name' => $code, 'symbol' => $code, 'format' => '1,0.00', 'exchange_rate' => 1, 'is_enabled' => true],
    )->id;
}

beforeEach(function (): void {
    $this->vndId = createMigrationCurrency('VND');
    $this->usdId = createMigrationCurrency('USD');
    $this->eurId = createMigrationCurrency('EUR');
});

function insertMigrationPrice(int $currencyId, ?int $compareAmount = null): int
{
    return DB::table(shopper_table('prices'))->insertGetId([
        'priceable_type' => 'product',
        'priceable_id' => 1,
        'amount' => 4499,
        'compare_amount' => $compareAmount,
        'cost_amount' => 2699,
        'currency_id' => $currencyId,
    ]);
}

it('converts USD prices to VND and drops prices in other currencies', function (): void {
    $usdPrice = insertMigrationPrice($this->usdId, compareAmount: 5999);
    insertMigrationPrice($this->eurId);

    runVndMigration();

    $prices = DB::table(shopper_table('prices'))->get();

    expect($prices)->toHaveCount(1)
        ->and($prices->first()->id)->toBe($usdPrice)
        ->and((int) $prices->first()->currency_id)->toBe($this->vndId)
        ->and((int) $prices->first()->amount)->toBe(1_124_750)
        ->and((int) $prices->first()->compare_amount)->toBe(1_499_750)
        ->and((int) $prices->first()->cost_amount)->toBe(674_750);
});

it('keeps zones by moving them to VND before deleting the other currencies', function (): void {
    $zoneId = DB::table(shopper_table('zones'))->insertGetId([
        'name' => 'Viet nam',
        'slug' => 'viet-nam',
        'currency_id' => $this->usdId,
    ]);

    runVndMigration();

    expect(Currency::query()->pluck('code')->all())->toBe(['VND'])
        ->and((int) DB::table(shopper_table('zones'))->where('id', $zoneId)->value('currency_id'))->toBe($this->vndId);
});

it('converts fixed discounts but not percentage discounts', function (): void {
    foreach ([['FIXED', 'fixed_amount', 1000], ['PERCENT', 'percentage', 10]] as [$code, $type, $value]) {
        DB::table(shopper_table('discounts'))->insert([
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'apply_to' => 'order',
            'min_required' => 'none',
            'eligibility' => 'everyone',
            'start_at' => now(),
        ]);
    }

    runVndMigration();

    expect((int) DB::table(shopper_table('discounts'))->where('code', 'FIXED')->value('value'))->toBe(250_000)
        ->and((int) DB::table(shopper_table('discounts'))->where('code', 'PERCENT')->value('value'))->toBe(10);
});

it('points the currency settings at VND', function (): void {
    Setting::query()->create(['key' => 'default_currency_id', 'value' => $this->usdId, 'display_name' => 'Currency', 'locked' => true]);
    Setting::query()->create(['key' => 'currencies', 'value' => [$this->usdId, $this->eurId], 'display_name' => 'Currencies', 'locked' => true]);

    runVndMigration();

    expect(shopper_setting('default_currency_id', false))->toBe($this->vndId)
        ->and(shopper_setting('currencies', false))->toBe([$this->vndId])
        ->and(shopper_currency())->toBe('VND');
});

it('refuses to run when orders exist in a currency without a known rate', function (): void {
    Order::factory()->create(['currency_code' => 'EUR']);

    expect(fn () => runVndMigration())->toThrow(RuntimeException::class, 'EUR');

    expect(Currency::query()->count())->toBe(3);
});

it('converts USD orders and their items', function (): void {
    $order = Order::factory()->create(['currency_code' => 'USD', 'price_amount' => 4499]);

    $itemId = DB::table(shopper_table('order_items'))->insertGetId([
        'name' => 'Demo',
        'sku' => 'DEMO',
        'quantity' => 1,
        'unit_price_amount' => 4499,
        'tax_amount' => 100,
        'discount_amount' => 0,
        'order_id' => $order->id,
        'product_type' => 'product',
        'product_id' => 1,
    ]);

    runVndMigration();

    $order->refresh();
    $item = DB::table(shopper_table('order_items'))->find($itemId);

    expect($order->currency_code)->toBe('VND')
        ->and($order->price_amount)->toBe(1_124_750)
        ->and((int) $item->unit_price_amount)->toBe(1_124_750)
        ->and((int) $item->tax_amount)->toBe(25_000);
});

it('does nothing on a fresh install', function (): void {
    DB::table(shopper_table('currencies'))->delete();

    runVndMigration();

    expect(Currency::query()->count())->toBe(0);
});
