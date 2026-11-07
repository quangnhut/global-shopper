<?php

declare(strict_types=1);

namespace Shopper\Storefront\Enums;

use Shopper\Core\Contracts\HasColor;
use Shopper\Core\Contracts\HasLabel;
use Shopper\Core\Traits\ArrayableEnum;
use Shopper\Core\Traits\HasEnumStaticMethods;

/**
 * The look of a section: badge on its cards, heading colour, and a countdown for hot deals.
 */
enum HomeSectionStyle: string implements HasColor, HasLabel
{
    use ArrayableEnum;
    use HasEnumStaticMethods;

    case Default = 'default';

    case Sale = 'sale';

    case HotDeal = 'hot_deal';

    case New = 'new';

    public function getLabel(): string
    {
        return match ($this) {
            self::Default => __('shopper-storefront::sections.styles.default'),
            self::Sale => __('shopper-storefront::sections.styles.sale'),
            self::HotDeal => __('shopper-storefront::sections.styles.hot_deal'),
            self::New => __('shopper-storefront::sections.styles.new'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Default => 'gray',
            self::Sale => 'danger',
            self::HotDeal => 'warning',
            self::New => 'success',
        };
    }

    /**
     * The badge shown on every card of the section, or null for none.
     */
    public function badge(): ?string
    {
        return match ($this) {
            self::Default => null,
            self::Sale => __('shopper-storefront::sections.badges.sale'),
            self::HotDeal => __('shopper-storefront::sections.badges.hot_deal'),
            self::New => __('shopper-storefront::sections.badges.new'),
        };
    }

    public function hasCountdown(): bool
    {
        return $this === self::HotDeal;
    }
}
