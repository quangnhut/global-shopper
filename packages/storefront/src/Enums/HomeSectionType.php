<?php

declare(strict_types=1);

namespace Shopper\Storefront\Enums;

use Shopper\Core\Contracts\HasIcon;
use Shopper\Core\Contracts\HasLabel;
use Shopper\Core\Traits\ArrayableEnum;
use Shopper\Core\Traits\HasEnumStaticMethods;

/**
 * What a home page section lists. Products come from the catalog, posts from the CMS (type `post`).
 */
enum HomeSectionType: string implements HasIcon, HasLabel
{
    use ArrayableEnum;
    use HasEnumStaticMethods;

    case Products = 'products';

    case Posts = 'posts';

    public function getLabel(): string
    {
        return match ($this) {
            self::Products => __('shopper-storefront::sections.types.products'),
            self::Posts => __('shopper-storefront::sections.types.posts'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Products => 'untitledui-package',
            self::Posts => 'untitledui-file-06',
        };
    }
}
