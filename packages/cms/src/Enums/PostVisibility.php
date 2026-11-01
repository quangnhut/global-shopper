<?php

declare(strict_types=1);

namespace Shopper\Cms\Enums;

use Shopper\Core\Contracts\HasLabel;
use Shopper\Core\Traits\ArrayableEnum;
use Shopper\Core\Traits\HasEnumStaticMethods;

/**
 * The "Visibility" choice of WordPress's publish box. It is not stored: like WordPress,
 * private means the `private` status and password protected means a password is set.
 *
 * @method static string Public()
 * @method static string Private()
 * @method static string Password()
 */
enum PostVisibility: string implements HasLabel
{
    use ArrayableEnum;
    use HasEnumStaticMethods;

    case Public = 'public';

    case Private = 'private';

    case Password = 'password';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => __('shopper-cms::enum/post.visibility.public'),
            self::Private => __('shopper-cms::enum/post.visibility.private'),
            self::Password => __('shopper-cms::enum/post.visibility.password'),
        };
    }
}
