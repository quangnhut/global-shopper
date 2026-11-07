<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Contracts\View\View;
use Shopper\Cms\Support\HtmlSanitizer;
use Shopper\Core\Models\Legal;
use Shopper\Storefront\Support\StorefrontCache;

/**
 * Policy pages (Settings → Legal): refund, privacy, terms, shipping.
 */
final class LegalController
{
    public function __invoke(Legal $legal, HtmlSanitizer $sanitizer): View
    {
        abort_unless($legal->is_enabled, 404);

        return view('shopper-storefront::legal', [
            ...StorefrontCache::layout(),
            'legal' => $legal,
            // Saved from a rich editor without sanitizing: cleaned on output.
            'content' => $sanitizer->clean($legal->content),
        ]);
    }
}
