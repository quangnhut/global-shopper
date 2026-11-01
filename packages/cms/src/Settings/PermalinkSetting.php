<?php

declare(strict_types=1);

namespace Shopper\Cms\Settings;

use Shopper\Settings\Setting;

final class PermalinkSetting extends Setting
{
    public function name(): string
    {
        return __('shopper-cms::permalinks.title');
    }

    public function description(): string
    {
        return __('shopper-cms::permalinks.description');
    }

    public function icon(): string
    {
        return 'phosphor-link';
    }

    public function url(): string
    {
        return route('shopper.cms.settings.permalinks');
    }

    public function permission(): string
    {
        return 'manage_permalinks';
    }

    public function order(): int
    {
        return 20;
    }
}
