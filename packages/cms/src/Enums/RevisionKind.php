<?php

declare(strict_types=1);

namespace Shopper\Cms\Enums;

use Shopper\Core\Traits\ArrayableEnum;
use Shopper\Core\Traits\HasEnumStaticMethods;

/**
 * @method static string Revision()
 * @method static string Autosave()
 */
enum RevisionKind: string
{
    use ArrayableEnum;
    use HasEnumStaticMethods;

    case Revision = 'revision';

    case Autosave = 'autosave';
}
