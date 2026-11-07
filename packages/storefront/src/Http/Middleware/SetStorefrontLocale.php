<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront pages and API use `shopper.storefront.locale`, or `?locale=` when it is one of `locales`.
 */
final class SetStorefrontLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = (array) config('shopper.storefront.locales', ['vi', 'en']);
        $requested = $request->query('locale');

        app()->setLocale(is_string($requested) && in_array($requested, $locales, true)
            ? $requested
            : (string) config('shopper.storefront.locale', 'vi'));

        return $next($request);
    }
}
