<?php

declare(strict_types=1);

namespace Shopper\Cms\Settings;

use Shopper\Settings\Setting;

final class RedirectSetting extends Setting
{
    public function name(): string
    {
        return __('shopper-cms::permalinks.redirects.title');
    }

    public function description(): string
    {
        return __('shopper-cms::permalinks.redirects.description');
    }

    public function icon(): string
    {
        return 'phosphor-arrow-bend-up-right';
    }

    public function url(): string
    {
        return route('shopper.cms.settings.redirects');
    }

    public function permission(): string
    {
        return 'manage_permalinks';
    }

    public function order(): int
    {
        return 21;
    }
}
