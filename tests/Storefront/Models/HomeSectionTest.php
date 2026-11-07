<?php

declare(strict_types=1);

use Shopper\Storefront\Enums\HomeSectionLayout;
use Shopper\Storefront\Enums\HomeSectionStyle;
use Shopper\Storefront\Enums\HomeSectionType;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Models\HomeSectionItem;

uses(Tests\Storefront\TestCase::class);

require_once __DIR__.'/../Support/helpers.php';

describe(HomeSection::class, function (): void {
    it('casts its type, style, layout and schedule', function (): void {
        $section = HomeSection::factory()->create([
            'style' => HomeSectionStyle::HotDeal,
            'layout' => HomeSectionLayout::Carousel,
            'ends_at' => now()->addDay(),
        ])->fresh();

        expect($section->type)->toBe(HomeSectionType::Products)
            ->and($section->style)->toBe(HomeSectionStyle::HotDeal)
            ->and($section->style->hasCountdown())->toBeTrue()
            ->and($section->layout)->toBe(HomeSectionLayout::Carousel)
            ->and($section->ends_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
    });

    it('is live only when enabled and inside its schedule', function (): void {
        $live = HomeSection::factory()->create();
        $disabled = HomeSection::factory()->disabled()->create();
        $scheduled = HomeSection::factory()->create(['starts_at' => now()->addHour()]);
        $expired = HomeSection::factory()->create(['ends_at' => now()->subMinute()]);
        $running = HomeSection::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        expect($live->isLive())->toBeTrue()
            ->and($disabled->isLive())->toBeFalse()
            ->and($scheduled->isLive())->toBeFalse()
            ->and($scheduled->isScheduled())->toBeTrue()
            ->and($expired->isLive())->toBeFalse()
            ->and($expired->isExpired())->toBeTrue()
            ->and($running->isLive())->toBeTrue()
            ->and(HomeSection::query()->live()->pluck('id')->sort()->values()->all())
            ->toBe([$live->id, $running->id]);
    });

    it('orders sections by `position`, lowest first', function (): void {
        $third = HomeSection::factory()->create(['position' => 30]);
        $first = HomeSection::factory()->create(['position' => 10]);
        $second = HomeSection::factory()->create(['position' => 20]);

        expect(HomeSection::query()->ordered()->pluck('id')->all())->toBe([$first->id, $second->id, $third->id]);
    });

    it('keeps its items in order and deletes them with the section', function (): void {
        $section = HomeSection::factory()->create();
        [$first, $second] = [storefrontProduct(), storefrontProduct()];

        $section->items()->create(['itemable_type' => $second->getMorphClass(), 'itemable_id' => $second->id, 'position' => 1]);
        $section->items()->create(['itemable_type' => $first->getMorphClass(), 'itemable_id' => $first->id, 'position' => 0]);

        expect($section->items->pluck('itemable_id')->all())->toBe([$first->id, $second->id])
            ->and($section->items->first()->itemable->is($first))->toBeTrue();

        $section->delete();

        expect(HomeSectionItem::query()->count())->toBe(0);
    });
});
