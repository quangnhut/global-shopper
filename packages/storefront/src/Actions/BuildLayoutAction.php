<?php

declare(strict_types=1);

namespace Shopper\Storefront\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Permalinks\PermalinkGenerator;
use Shopper\Core\Models\Carrier;
use Shopper\Core\Models\Category;
use Shopper\Core\Models\Legal;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Core\Models\Upload;
use Shopper\Seo\Support\SeoUrls;
use Shopper\Storefront\Data\FooterData;
use Shopper\Storefront\Data\HeaderData;
use Shopper\Storefront\Data\LinkData;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\StorefrontUrls;

/**
 * Header and footer of every storefront page, from the Homepage → Header & footer settings
 * and Settings → General.
 */
final readonly class BuildLayoutAction
{
    public function __construct(private StorefrontSettings $settings) {}

    /**
     * @return array{header: HeaderData, footer: FooterData}
     */
    public function execute(): array
    {
        $uploads = $this->uploads();

        return [
            'header' => $this->header($uploads),
            'footer' => $this->footer($uploads),
        ];
    }

    /**
     * @param  Collection<int, Upload>  $uploads
     */
    private function header(Collection $uploads): HeaderData
    {
        $headerLogo = $this->settings->get('header_logo');
        $logo = $headerLogo !== null
            ? $this->uploadUrl($uploads, $headerLogo)
            : SeoUrls::disk($this->settings->get('store.logo'));

        return new HeaderData(
            storeName: $this->settings->storeName(),
            logo: $logo,
            hotline: $this->settings->hotline(),
            announcement: $this->settings->get('announcement'),
            categories: $this->categories(),
        );
    }

    /**
     * Chosen top-level categories (or every enabled one), each with its enabled children, in `position` order.
     *
     * @return array<int, LinkData>
     */
    private function categories(): array
    {
        $chosen = array_map('intval', (array) $this->settings->get('menu_category_ids', []));

        $roots = Category::query()
            ->enabled()
            ->whereNull('parent_id')
            ->when($chosen !== [], fn ($query) => $query->whereKey($chosen))
            ->with(['children' => fn ($query) => $query->where('is_enabled', true)->orderBy('position')->orderBy('name')])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        if ($chosen !== []) {
            $roots = $roots->sortBy(fn (Category $category): int|false => array_search($category->id, $chosen, true))->values();
        }

        return $roots->map(fn (Category $category): LinkData => new LinkData(
            label: $category->name,
            url: SeoUrls::entity('category', (string) $category->slug),
            children: $category->children->map(fn (Category $child): LinkData => new LinkData(
                label: $child->name,
                url: SeoUrls::entity('category', (string) $child->slug),
            ))->all(),
        ))->all();
    }

    /**
     * @param  Collection<int, Upload>  $uploads
     */
    private function footer(Collection $uploads): FooterData
    {
        return new FooterData(
            company: [
                'name' => $this->settings->companyName(),
                'store_name' => $this->settings->storeName(),
                'address' => $this->settings->address(),
                'business_registration' => $this->settings->get('business_registration'),
                'registration_issued' => $this->settings->get('registration_issued'),
                'phone' => $this->settings->hotline(),
                'email' => $this->settings->get('store.email'),
                'working_hours' => $this->settings->get('working_hours'),
            ],
            columns: $this->columns(),
            badges: $this->badges($uploads),
            shippingPartners: $this->partners('shipping', $uploads),
            paymentPartners: $this->partners('payment', $uploads),
            socials: $this->socials(),
        );
    }

    /**
     * Footer link columns; links to unpublished pages or disabled policies are dropped.
     *
     * @return array<int, array{title: string, links: array<int, LinkData>}>
     */
    private function columns(): array
    {
        $columns = (array) $this->settings->get('footer_columns', []);
        $links = collect($columns)->flatMap(fn (array $column): array => (array) ($column['links'] ?? []));

        $pageIds = $links->where('type', 'page')->pluck('id')->filter()->all();
        $legalIds = $links->where('type', 'legal')->pluck('id')->filter()->all();

        $pages = $pageIds === [] ? collect() : Post::configuredClass()::query()
            ->ofType('page')
            ->published()
            ->whereKey($pageIds)
            ->with('parent')
            ->get()
            ->keyBy('id');
        $legals = $legalIds === [] ? collect() : Legal::query()->enabled()->whereKey($legalIds)->get()->keyBy('id');
        $permalinks = resolve(PermalinkGenerator::class);

        return collect($columns)
            ->map(fn (array $column): array => [
                'title' => (string) ($column['title'] ?? ''),
                'links' => collect((array) ($column['links'] ?? []))
                    ->map(function (array $link) use ($pages, $legals, $permalinks): ?LinkData {
                        $label = filled($link['label'] ?? null) ? (string) $link['label'] : null;

                        return match ($link['type'] ?? null) {
                            'page' => ($page = $pages->get($link['id'] ?? 0)) instanceof Post
                                ? new LinkData($label ?? $page->title, $permalinks->url($page))
                                : null,
                            'legal' => ($legal = $legals->get($link['id'] ?? 0)) instanceof Legal
                                ? new LinkData($label ?? $legal->title, StorefrontUrls::to('legal', ['slug' => $legal->slug]))
                                : null,
                            'url' => filled($link['url'] ?? null) ? new LinkData($label ?? (string) $link['url'], $this->href((string) $link['url'])) : null,
                            default => null,
                        };
                    })
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $column): bool => $column['title'] !== '' || $column['links'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Upload>  $uploads
     * @return array<int, LinkData>
     */
    private function badges(Collection $uploads): array
    {
        $badges = [];

        foreach (['bct' => 'Bộ Công Thương', 'dmca' => 'DMCA'] as $key => $label) {
            $url = $this->settings->get("{$key}_url");
            $image = $this->uploadUrl($uploads, $this->settings->get("{$key}_badge"));

            if ($url !== null || $image !== null) {
                $badges[] = new LinkData($label, $url, $image);
            }
        }

        return $badges;
    }

    /**
     * Carriers or payment methods (the chosen ones, or every enabled one with a logo), then the extra partners.
     *
     * @param  Collection<int, Upload>  $uploads
     * @return array<int, LinkData>
     */
    private function partners(string $group, Collection $uploads): array
    {
        [$model, $key, $nameColumn] = $group === 'shipping'
            ? [Carrier::class, 'carrier_ids', 'name']
            : [PaymentMethod::class, 'payment_method_ids', 'title'];

        $chosen = array_map('intval', (array) $this->settings->get($key, []));

        $partners = $model::query()
            ->enabled()
            ->when($chosen !== [], fn ($query) => $query->whereKey($chosen))
            ->orderBy($nameColumn)
            ->get()
            ->map(fn (Model $partner): LinkData => new LinkData(
                label: (string) $partner->getAttribute($nameColumn),
                url: $partner->getAttribute('link_url'),
                image: $partner->logo(),
            ))
            ->filter(fn (LinkData $partner): bool => $chosen !== [] || $partner->image !== null);

        $extra = collect((array) $this->settings->get('extra_partners', []))
            ->filter(fn (array $partner): bool => ($partner['group'] ?? 'shipping') === $group)
            ->map(fn (array $partner): LinkData => new LinkData(
                label: (string) ($partner['name'] ?? ''),
                url: filled($partner['url'] ?? null) ? (string) $partner['url'] : null,
                image: $this->uploadUrl($uploads, $partner['logo'] ?? null),
            ));

        return $partners->concat($extra)->values()->all();
    }

    /**
     * @return array<int, LinkData>
     */
    private function socials(): array
    {
        return collect(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X'])
            ->map(fn (string $label, string $network): ?LinkData => filled($url = $this->settings->get("store.{$network}_link"))
                ? new LinkData($label, (string) $url)
                : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Every upload the header and footer use, in one query.
     *
     * @return Collection<int, Upload>
     */
    private function uploads(): Collection
    {
        $ids = collect([
            $this->settings->get('header_logo'),
            $this->settings->get('bct_badge'),
            $this->settings->get('dmca_badge'),
            ...collect((array) $this->settings->get('extra_partners', []))->pluck('logo')->all(),
        ])->filter(fn (mixed $id): bool => is_numeric($id))->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();

        return $ids === [] ? new Collection : Upload::query()->whereKey($ids)->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, Upload>  $uploads
     */
    private function uploadUrl(Collection $uploads, mixed $id): ?string
    {
        $upload = is_numeric($id) ? $uploads->get((int) $id) : null;

        return $upload instanceof Upload && $upload->isImage() ? $upload->url : null;
    }

    /**
     * Relative footer URLs point to the storefront.
     */
    private function href(string $url): string
    {
        return str_starts_with($url, '/') ? StorefrontUrls::base().$url : $url;
    }
}
