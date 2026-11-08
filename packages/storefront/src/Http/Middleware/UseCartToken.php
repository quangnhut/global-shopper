<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shopper\Storefront\Cart\StorefrontCart;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headless carts: the `X-Cart-Token` header names the cart (the API has no session).
 */
final class UseCartToken
{
    public const string HEADER = 'X-Cart-Token';

    public function handle(Request $request, Closure $next): Response
    {
        $carts = resolve(StorefrontCart::class);
        $carts->useToken($request->header(self::HEADER));

        $response = $next($request);

        $token = $carts->token();

        if ($token !== null) {
            $response->headers->set(self::HEADER, $token);
        }

        return $response;
    }
}
