<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Shopper\Storefront\Actions\SearchProductsAction;
use Shopper\Storefront\Support\StorefrontCache;

final class SearchController
{
    public function __invoke(Request $request, SearchProductsAction $search): View
    {
        $term = mb_substr(mb_trim((string) $request->query('q', '')), 0, SearchProductsAction::MAX_TERM_LENGTH);
        $page = max(1, (int) $request->query('page', 1));

        $results = $search->execute($term, 24, $page)->withQueryString();

        return view('shopper-storefront::search', [
            ...StorefrontCache::layout(),
            'term' => $term,
            'results' => $results,
        ]);
    }
}
