<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Shopper\Core\Enum\DiscountApplyTo;
use Shopper\Core\Enum\DiscountEligibility;
use Shopper\Core\Enum\DiscountRequirement;
use Shopper\Core\Enum\DiscountType;
use Shopper\Core\Models\Discount;

final class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        $discounts = [
            ['WELCOME10', DiscountType::Percentage, 10, true, now()->subMonth(), now()->addMonths(6)],
            ['FREESHIP', DiscountType::FixedAmount, 250000, true, now()->subWeek(), now()->addMonth()],
            ['BLACKFRIDAY', DiscountType::Percentage, 25, false, now()->addMonth(), now()->addMonth()->addDays(4)],
        ];

        foreach ($discounts as [$code, $type, $value, $isActive, $startAt, $endAt]) {
            Discount::query()->create([
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'is_active' => $isActive,
                'apply_to' => DiscountApplyTo::Order,
                'min_required' => DiscountRequirement::None,
                'eligibility' => DiscountEligibility::Everyone,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);
        }
    }
}
