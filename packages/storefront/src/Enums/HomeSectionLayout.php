<?php

declare(strict_types=1);

namespace Shopper\Storefront\Enums;

use Shopper\Core\Contracts\HasLabel;
use Shopper\Core\Traits\ArrayableEnum;
use Shopper\Core\Traits\HasEnumStaticMethods;

enum HomeSectionLayout: string implements HasLabel
{
    use ArrayableEnum;
    use HasEnumStaticMethods;

    case Grid = 'grid';

    case Carousel = 'carousel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Grid => __('shopper-storefront::sections.layouts.grid'),
            self::Carousel => __('shopper-storefront::sections.layouts.carousel'),
        };
    }
}
