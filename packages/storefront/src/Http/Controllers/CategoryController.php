<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Shopper\Core\Models\Category;
use Shopper\Seo\Support\SeoUrls;
use Shopper\Storefront\Actions\ListCategoryProductsAction;
use Shopper\Storefront\Support\StorefrontCache;

/**
 * Category page at `shopper.seo.paths.category` (`/categories/{slug}` by default).
 */
final class CategoryController
{
    public function __invoke(Request $request, string $slug, ListCategoryProductsAction $listProducts): View
    {
        /** @var Category|null $category */
        $category = config('shopper.models.category', Category::class)::query()
            ->enabled()
            ->where('slug', $slug)
            ->first();

        // A category under a disabled parent is hidden too, like in the menu.
        abort_if($category === null || $category->ancestors()->where('is_enabled', false)->exists(), 404);

        $sort = (string) $request->query('sort', 'newest');
        $sort = in_array($sort, ListCategoryProductsAction::SORTS, true) ? $sort : 'newest';
        $page = max(1, (int) $request->query('page', 1));

        $products = $listProducts->execute($category, $sort, 24, $page)->withQueryString();

        abort_if($page > 1 && $page > $products->lastPage(), 404);

        $ancestors = $category->ancestors()->orderBy('depth', 'desc')->get()->reverse()->values();

        return view('shopper-storefront::category', [
            ...StorefrontCache::layout(),
            'category' => $category,
            'products' => $products,
            'sort' => $sort,
            'breadcrumbs' => $ancestors->map(fn (Category $parent): array => [
                'label' => $parent->name,
                'url' => SeoUrls::entity('category', (string) $parent->slug),
            ])->all(),
            'children' => $category->children()->enabled()->orderBy('position')->get()
                ->map(fn (Category $child): array => ['label' => $child->name, 'url' => SeoUrls::entity('category', (string) $child->slug)])
                ->all(),
        ]);
    }
}
