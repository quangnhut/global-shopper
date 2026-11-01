<?php

declare(strict_types=1);

namespace Shopper\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\IndexingRules;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional: sends `X-Robots-Tag` for non-HTML responses (PDFs, images, JSON…) on noindex paths, where a meta
 * tag cannot be used. Register it on the routes that serve such files.
 */
final class AddXRobotsTag
{
    public function __construct(
        private readonly SeoSettings $settings,
        private readonly IndexingRules $rules,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($response->headers->has('X-Robots-Tag') || str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        if ($this->settings->get('general.discourage_search_engines')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        }

        $rule = $this->rules->match($request->getPathInfo());

        if ($rule !== null) {
            $response->headers->set('X-Robots-Tag', $rule['nofollow'] ? 'noindex, nofollow' : 'noindex');
        }

        return $response;
    }
}
