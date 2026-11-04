<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 25,000 ₫ per USD. USD amounts are stored in cents and VND in whole dong,
     * so one stored USD unit is worth 250 stored VND units.
     */
    private const int USD_CENTS_TO_VND = 250;

    public function up(): void
    {
        $currencies = shopper_table('currencies');
        $vndId = DB::table($currencies)->where('code', 'VND')->value('id');

        // Fresh install: the seeder only creates VND afterwards.
        if (DB::table($currencies)->doesntExist()) {
            return;
        }

        if ($vndId === null) {
            throw new RuntimeException('The VND currency is missing, cannot make it the only currency.');
        }

        $unsupported = DB::table(shopper_table('orders'))
            ->whereNotIn('currency_code', ['USD', 'VND'])
            ->distinct()
            ->pluck('currency_code');

        if ($unsupported->isNotEmpty()) {
            throw new RuntimeException("Orders exist in currencies without a known VND rate: {$unsupported->implode(', ')}.");
        }

        DB::transaction(function () use ($currencies, $vndId): void {
            $this->convertPrices((int) $vndId);
            $this->convertOrders();
            $this->convertCarts();
            $this->convertDiscounts();
            $this->convertCollectionRules();

            DB::table(shopper_table('zones'))->update(['currency_id' => $vndId]);

            DB::table(shopper_table('settings'))
                ->where('key', 'default_currency_id')
                ->update(['value' => json_encode((int) $vndId)]);
            DB::table(shopper_table('settings'))
                ->where('key', 'currencies')
                ->update(['value' => json_encode([(int) $vndId])]);

            DB::table($currencies)->where('id', '!=', $vndId)->delete();
        });

        Cache::forget('shopper-setting.default_currency');
        Cache::forget('shopper-setting.default_currency_id');
        Cache::forget('shopper-setting.currencies');
    }

    /**
     * Irreversible: the deleted currencies and original amounts are not kept.
     */
    public function down(): void {}

    private function convertPrices(int $vndId): void
    {
        $prices = shopper_table('prices');
        $usdId = DB::table(shopper_table('currencies'))->where('code', 'USD')->value('id');

        if ($usdId !== null) {
            $this->multiply(
                DB::table($prices)->where('currency_id', $usdId),
                ['amount', 'compare_amount', 'cost_amount'],
            );
            DB::table($prices)->where('currency_id', $usdId)->update(['currency_id' => $vndId]);
        }

        // Prices in any other currency are dropped: USD is the source of truth.
        DB::table($prices)->where('currency_id', '!=', $vndId)->delete();
    }

    private function convertOrders(): void
    {
        $orders = shopper_table('orders');
        $usdOrders = DB::table($orders)->where('currency_code', 'USD')->select('id');

        $this->multiply(
            DB::table(shopper_table('order_items'))->whereIn('order_id', $usdOrders),
            ['unit_price_amount', 'tax_amount', 'discount_amount'],
        );
        $this->multiply(
            DB::table(shopper_table('order_refunds'))->where('currency', 'USD'),
            ['amount'],
        );
        DB::table(shopper_table('order_refunds'))->where('currency', 'USD')->update(['currency' => 'VND']);

        // Polymorphic (order or order item): `up()` has already ruled out any currency but USD and VND.
        if (DB::table($orders)->where('currency_code', 'VND')->doesntExist()) {
            $this->multiply(DB::table(shopper_table('order_tax_lines')), ['amount']);
        }

        if (Schema::hasTable(shopper_table('payment_transactions'))) {
            $transactions = shopper_table('payment_transactions');

            $this->multiply(DB::table($transactions)->where('currency_code', 'USD'), ['amount']);
            DB::table($transactions)->where('currency_code', 'USD')->update(['currency_code' => 'VND']);
        }

        $this->multiply(DB::table($orders)->where('currency_code', 'USD'), ['price_amount', 'tax_amount']);

        DB::table($orders)->where('discount_currency_code', 'USD')->update(['discount_currency_code' => 'VND']);
        DB::table($orders)->where('currency_code', 'USD')->update(['currency_code' => 'VND']);
    }

    private function convertCarts(): void
    {
        // The cart package is optional.
        if (! Schema::hasTable(shopper_table('carts'))) {
            return;
        }

        $carts = shopper_table('carts');
        $lines = shopper_table('cart_lines');
        $usdCarts = DB::table($carts)->where('currency_code', 'USD')->select('id');
        $usdLines = DB::table($lines)->whereIn('cart_id', $usdCarts)->select('id');

        foreach (['cart_line_adjustments', 'cart_line_tax_lines'] as $table) {
            $this->multiply(
                DB::table(shopper_table($table))->whereIn('cart_line_id', $usdLines),
                ['amount'],
            );
        }

        $this->multiply(DB::table($lines)->whereIn('cart_id', $usdCarts), ['unit_price_amount']);

        DB::table($carts)->update(['currency_code' => 'VND']);
    }

    private function convertDiscounts(): void
    {
        $discounts = shopper_table('discounts');

        $this->multiply(DB::table($discounts)->where('type', 'fixed_amount'), ['value']);

        DB::table($discounts)
            ->where('min_required', 'price')
            ->whereNotNull('min_required_value')
            ->eachById(fn (object $discount): int => DB::table($discounts)
                ->where('id', $discount->id)
                ->update(['min_required_value' => (string) ((int) $discount->min_required_value * self::USD_CENTS_TO_VND)]));
    }

    private function convertCollectionRules(): void
    {
        $rules = shopper_table('collection_rules');

        DB::table($rules)
            ->whereIn('rule', ['product_price', 'compare_at_price'])
            ->eachById(fn (object $rule): int => DB::table($rules)
                ->where('id', $rule->id)
                ->update(['value' => (string) ((int) $rule->value * self::USD_CENTS_TO_VND)]));
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function multiply(Illuminate\Database\Query\Builder $query, array $columns): void
    {
        $query->update(collect($columns)->mapWithKeys(fn (string $column): array => [
            $column => DB::raw("{$column} * ".self::USD_CENTS_TO_VND),
        ])->all());
    }
};
