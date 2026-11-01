<?php

declare(strict_types=1);

namespace Shopper\Cms\Enums;

use Shopper\Core\Contracts\HasColor;
use Shopper\Core\Contracts\HasIcon;
use Shopper\Core\Contracts\HasLabel;
use Shopper\Core\Traits\ArrayableEnum;
use Shopper\Core\Traits\HasEnumStaticMethods;

/**
 * Values match WordPress's `post_status` so imports map one to one.
 * Trash is not a status: trashed posts are soft deleted and keep their status.
 *
 * @method static string Published()
 * @method static string Scheduled()
 * @method static string Draft()
 * @method static string Pending()
 * @method static string Private()
 */
enum PostStatus: string implements HasColor, HasIcon, HasLabel
{
    use ArrayableEnum;
    use HasEnumStaticMethods;

    case Published = 'publish';

    case Scheduled = 'future';

    case Draft = 'draft';

    case Pending = 'pending';

    case Private = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::Published => __('shopper-cms::enum/post.status.publish'),
            self::Scheduled => __('shopper-cms::enum/post.status.future'),
            self::Draft => __('shopper-cms::enum/post.status.draft'),
            self::Pending => __('shopper-cms::enum/post.status.pending'),
            self::Private => __('shopper-cms::enum/post.status.private'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Scheduled => 'info',
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Private => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Published => 'phosphor-globe-duotone',
            self::Scheduled => 'phosphor-clock-duotone',
            self::Draft => 'phosphor-pencil-simple-duotone',
            self::Pending => 'phosphor-hourglass-duotone',
            self::Private => 'phosphor-lock-duotone',
        };
    }
}
