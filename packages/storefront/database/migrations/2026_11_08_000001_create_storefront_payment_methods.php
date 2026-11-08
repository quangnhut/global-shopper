<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Shopper\Core\Models\Order;
use Shopper\Core\Models\PaymentMethod;

/**
 * The three ways to pay at checkout. Existing rows with these slugs are kept as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $methods = [
            'office' => ['title' => 'Thanh toán tại văn phòng', 'driver' => 'manual', 'description' => 'Thanh toán tiền mặt hoặc quẹt thẻ khi nhận hàng tại văn phòng.'],
            'bank-transfer' => ['title' => 'Chuyển khoản ngân hàng (VietQR)', 'driver' => 'manual', 'description' => 'Quét mã VietQR hoặc chuyển khoản với nội dung là mã đơn hàng.'],
            'onepay' => ['title' => 'Thẻ ATM nội địa (OnePay)', 'driver' => 'onepay', 'description' => 'Thanh toán bằng thẻ ATM/Napas qua cổng OnePay.'],
        ];

        foreach ($methods as $slug => $attributes) {
            PaymentMethod::query()->firstOrCreate(['slug' => $slug], [...$attributes, 'is_enabled' => true]);
        }
    }

    public function down(): void
    {
        PaymentMethod::query()
            ->whereIn('slug', ['office', 'bank-transfer', 'onepay'])
            ->whereNotIn('id', Order::query()->whereNotNull('payment_method_id')->select('payment_method_id'))
            ->delete();
    }
};
