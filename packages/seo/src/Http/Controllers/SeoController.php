<?php

declare(strict_types=1);

namespace Shopper\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shopper\Seo\SeoManager;

/**
 * `GET {api}/seo?path=/2026/10/hello-world` → the SEO data a headless storefront renders in its `<head>`.
 * Paths without a public entry (`/cart`, drafts) get the site defaults and the URL pattern rules.
 */
final class SeoController
{
    public function __invoke(Request $request, SeoManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
            'page' => ['nullable', 'integer', 'min:1'],
            'last_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'not_found' => ['nullable', 'boolean'],
        ]);

        $context = array_filter([
            'page' => isset($validated['page']) ? (int) $validated['page'] : null,
            'last_page' => isset($validated['last_page']) ? (int) $validated['last_page'] : null,
            'search' => $validated['search'] ?? null,
            'not_found' => $request->boolean('not_found') ?: null,
        ], fn (mixed $value): bool => $value !== null);

        return response()->json(['data' => $manager->for((string) $validated['path'], $context)->toArray()]);
    }
}
