<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Contracts\View\View;
use Shopper\Storefront\Support\StorefrontCache;

final class HomeController
{
    public function __invoke(): View
    {
        $home = StorefrontCache::home();

        return view('shopper-storefront::home', [
            'header' => $home->header,
            'footer' => $home->footer,
            'sections' => $home->sections,
        ]);
    }
}
