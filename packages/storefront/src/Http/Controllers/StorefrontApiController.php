<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shopper\Storefront\Actions\SearchProductsAction;
use Shopper\Storefront\Data\ProductCardData;
use Shopper\Storefront\Support\StorefrontCache;

final class StorefrontApiController
{
    public function home(): JsonResponse
    {
        return response()
            ->json(StorefrontCache::home()->toArray())
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function search(Request $request, SearchProductsAction $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:'.SearchProductsAction::MAX_TERM_LENGTH],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $results = $search->execute($validated['q'] ?? null, (int) ($validated['per_page'] ?? 6), (int) ($validated['page'] ?? 1));

        return response()->json([
            'query' => $validated['q'] ?? '',
            'total' => $results->total(),
            'items' => array_map(fn (ProductCardData $card): array => $card->toArray(), $results->items()),
        ]);
    }
}
