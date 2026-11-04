<?php

declare(strict_types=1);

namespace Database\Seeders;

use const STR_PAD_LEFT;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Shopper\Core\Enum\AddressType;
use Shopper\Core\Enum\OrderStatus;
use Shopper\Core\Enum\PaymentStatus;
use Shopper\Core\Enum\ShippingStatus;
use Shopper\Core\Models\Address;
use Shopper\Core\Models\Contracts\Channel as ChannelContract;
use Shopper\Core\Models\Contracts\Order as OrderContract;
use Shopper\Core\Models\Contracts\Product as ProductContract;
use Shopper\Core\Models\Currency;
use Shopper\Core\Models\OrderAddress;
use Shopper\Core\Models\PaymentMethod;

final class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $userModel = config('auth.providers.users.model');
        $customers = $userModel::query()->role(config('shopper.admin.roles.user'))->get();
        $currency = Currency::query()->where('code', 'VND')->firstOrFail();
        $products = resolve(ProductContract::class)::query()
            ->with(['prices' => fn ($query) => $query->where('currency_id', $currency->id)])
            ->get();
        $channel = resolve(ChannelContract::class)::query()->where('is_default', true)->firstOrFail();
        $paymentMethod = PaymentMethod::query()->firstOrFail();

        for ($i = 1; $i <= 80; $i++) {
            $customer = $customers->random();
            $createdAt = now()->subDays(fake()->numberBetween(0, 180))->subMinutes(fake()->numberBetween(0, 1440));
            [$status, $paymentStatus, $shippingStatus] = $this->randomStatuses();

            $shippingAddress = $this->createOrderAddress($customer, AddressType::Shipping);
            $billingAddress = $this->createOrderAddress($customer, AddressType::Billing);

            $order = resolve(OrderContract::class)::query()->create([
                'number' => 'ORD-'.mb_str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'currency_code' => $currency->code,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'shipping_status' => $shippingStatus,
                'notes' => fake()->optional(0.3)->sentence(),
                'customer_id' => $customer->id,
                'channel_id' => $channel->id,
                'payment_method_id' => $paymentMethod->id,
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $billingAddress->id,
                'cancelled_at' => $status === OrderStatus::Cancelled ? $createdAt->copy()->addDay() : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $total = 0;

            foreach ($products->random(fake()->numberBetween(1, 4)) as $product) {
                $quantity = fake()->numberBetween(1, 3);
                $unitPrice = (int) $product->prices->first()?->amount;
                $total += $quantity * $unitPrice;

                $order->items()->create([
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'product_type' => $product->getMorphClass(),
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price_amount' => $unitPrice,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            $order->update(['price_amount' => $total]);
        }
    }

    /**
     * @return array{0: OrderStatus, 1: PaymentStatus, 2: ShippingStatus}
     */
    private function randomStatuses(): array
    {
        return fake()->randomElement([
            [OrderStatus::New, PaymentStatus::Pending, ShippingStatus::Unfulfilled],
            [OrderStatus::New, PaymentStatus::Authorized, ShippingStatus::Unfulfilled],
            [OrderStatus::Processing, PaymentStatus::Paid, ShippingStatus::Unfulfilled],
            [OrderStatus::Processing, PaymentStatus::Paid, ShippingStatus::Shipped],
            [OrderStatus::Completed, PaymentStatus::Paid, ShippingStatus::Delivered],
            [OrderStatus::Completed, PaymentStatus::Paid, ShippingStatus::Delivered],
            [OrderStatus::Completed, PaymentStatus::PartiallyRefunded, ShippingStatus::PartiallyReturned],
            [OrderStatus::Cancelled, PaymentStatus::Voided, ShippingStatus::Unfulfilled],
        ]);
    }

    private function createOrderAddress(Model $customer, AddressType $type): OrderAddress
    {
        /** @var Address $address */
        $address = Address::query()
            ->with('country')
            ->where('user_id', $customer->getKey())
            ->where('type', $type)
            ->firstOrFail();

        return OrderAddress::query()->create([
            'customer_id' => $customer->getKey(),
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'street_address' => $address->street_address,
            'postal_code' => $address->postal_code,
            'city' => $address->city,
            'phone' => $address->phone_number,
            'country_name' => $address->country?->name,
        ]);
    }
}
