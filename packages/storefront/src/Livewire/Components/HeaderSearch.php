<?php

declare(strict_types=1);

namespace Shopper\Storefront\Livewire\Components;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Shopper\Storefront\Actions\SearchProductsAction;
use Shopper\Storefront\Data\ProductCardData;

/**
 * Header search box with product suggestions; submitting opens the search page.
 */
class HeaderSearch extends Component
{
    public const int SUGGESTIONS = 6;

    public string $q = '';

    /**
     * Not named `q`: Livewire would assign it to the property directly, before `mount()`.
     */
    public function mount(?string $query = null): void
    {
        $this->q = mb_substr((string) $query, 0, SearchProductsAction::MAX_TERM_LENGTH);
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, SearchProductsAction::MAX_TERM_LENGTH);
    }

    /**
     * @return array{items: array<int, ProductCardData>, total: int}
     */
    #[Computed]
    public function suggestions(): array
    {
        if (mb_strlen(mb_trim($this->q)) < 2) {
            return ['items' => [], 'total' => 0];
        }

        $results = resolve(SearchProductsAction::class)->execute($this->q, self::SUGGESTIONS);

        return ['items' => $results->items(), 'total' => $results->total()];
    }

    public function render(): View
    {
        return view('shopper-storefront::livewire.header-search');
    }
}
