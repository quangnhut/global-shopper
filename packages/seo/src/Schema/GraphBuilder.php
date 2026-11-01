<?php

declare(strict_types=1);

namespace Shopper\Seo\Schema;

use Illuminate\Support\Facades\Storage;
use Shopper\Core\Models\Country;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\SeoUrls;

/**
 * One JSON-LD `@graph` per page, nodes linked by `@id` (Yoast style): WebSite, Organization or Person,
 * the WebPage, a BreadcrumbList and the entry's own nodes (Article, Product…).
 */
final class GraphBuilder
{
    public const array WEBPAGE_TYPES = [
        'WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'CollectionPage', 'ProfilePage', 'SearchResultsPage',
        'CheckoutPage', 'ItemPage', 'QAPage',
    ];

    public const array ARTICLE_TYPES = ['Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'Report'];

    public function __construct(private readonly SeoSettings $settings) {}

    public static function websiteId(): string
    {
        return SeoUrls::home().'#website';
    }

    public static function language(): string
    {
        return str_replace('_', '-', app()->getLocale());
    }

    /**
     * Drops null and empty values, keeps `0` and `false`.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function filter(array $node): array
    {
        return array_filter($node, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    public function publisherId(): string
    {
        return SeoUrls::home().($this->settings->get('schema.entity') === 'person' ? '#person' : '#organization');
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs  after the home page
     * @param  array<int, array<string, mixed>>  $nodes  the entry's own nodes
     * @param  array<array-key, mixed>|null  $custom  the entry's custom JSON-LD, merged in last
     * @return array<string, mixed>
     */
    public function build(GraphContext $context, array $breadcrumbs = [], array $nodes = [], ?array $custom = null, bool $isHome = false): array
    {
        $graph = [$this->website(), $this->publisher(), $this->webpage($context, $isHome)];

        if ($context->breadcrumbId !== null && $breadcrumbs !== []) {
            $graph[] = $this->breadcrumbList($context->breadcrumbId, $breadcrumbs);
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [...$graph, ...$nodes, ...$this->customNodes($custom)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        $searchUrl = $this->settings->get('schema.search_url');

        return self::filter([
            '@type' => 'WebSite',
            '@id' => self::websiteId(),
            'url' => SeoUrls::home(),
            'name' => $this->siteName(),
            'description' => $this->settings->get('general.tagline'),
            'publisher' => ['@id' => $this->publisherId()],
            'inLanguage' => self::language(),
            'potentialAction' => filled($searchUrl) ? [[
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => SeoUrls::absolute((string) $searchUrl)],
                'query-input' => 'required name=search_term_string',
            ]] : null,
        ]);
    }

    /**
     * The Organization (optionally a LocalBusiness subtype) or Person behind the site.
     *
     * @return array<string, mixed>
     */
    public function publisher(): array
    {
        $schema = $this->settings->group('schema');
        $name = $schema['name'] ?: $this->siteName();

        if (($schema['entity'] ?? 'organization') === 'person') {
            return self::filter([
                '@type' => 'Person',
                '@id' => $this->publisherId(),
                'name' => $name,
                'image' => $this->logo(),
                'sameAs' => $this->sameAs() ?: null,
            ]);
        }

        $localBusiness = $schema['local_business_type'] ?? null;
        $logo = $this->logo();

        return self::filter([
            '@type' => filled($localBusiness) ? $localBusiness : 'Organization',
            '@id' => $this->publisherId(),
            'name' => $name,
            'url' => SeoUrls::home(),
            'logo' => $logo === null ? null : ['@type' => 'ImageObject', '@id' => SeoUrls::home().'#logo', 'url' => $logo, 'contentUrl' => $logo, 'caption' => $name],
            'image' => $logo === null ? null : ['@id' => SeoUrls::home().'#logo'],
            'email' => $schema['email'] ?: shopper_setting('email'),
            'telephone' => $schema['phone'] ?: shopper_setting('phone_number'),
            'address' => $this->address(),
            'openingHours' => filled($localBusiness) ? (array_values(array_filter((array) ($schema['opening_hours'] ?? []))) ?: null) : null,
            'sameAs' => $this->sameAs() ?: null,
        ]);
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumbList(string $id, array $items): array
    {
        $items = [['name' => __('shopper-seo::output.home'), 'url' => SeoUrls::home()], ...$items];

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $id,
            'itemListElement' => array_map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function webpage(GraphContext $context, bool $isHome): array
    {
        $type = in_array($context->schemaType, self::WEBPAGE_TYPES, true) ? $context->schemaType : 'WebPage';

        return self::filter([
            '@type' => $type,
            '@id' => $context->webpageId,
            'url' => $context->url,
            'name' => $context->name,
            'description' => $context->description,
            'isPartOf' => ['@id' => $context->websiteId],
            'about' => $isHome ? ['@id' => $context->publisherId] : null,
            'primaryImageOfPage' => $context->image === null ? null : ['@type' => 'ImageObject', 'url' => $context->image],
            'datePublished' => $context->datePublished,
            'dateModified' => $context->dateModified,
            'breadcrumb' => $context->breadcrumbId === null ? null : ['@id' => $context->breadcrumbId],
            'inLanguage' => $context->language,
        ]);
    }

    /**
     * Custom JSON-LD of an entry: a single node, a list of nodes or a whole `{"@graph": [...]}`.
     *
     * @param  array<array-key, mixed>|null  $custom
     * @return array<int, array<string, mixed>>
     */
    private function customNodes(?array $custom): array
    {
        if ($custom === null || $custom === []) {
            return [];
        }

        $nodes = isset($custom['@graph']) && is_array($custom['@graph'])
            ? $custom['@graph']
            : (array_is_list($custom) ? $custom : [$custom]);

        return array_values(array_map(function (mixed $node): array {
            unset($node['@context']);

            return $node;
        }, array_filter($nodes, fn (mixed $node): bool => is_array($node) && isset($node['@type']))));
    }

    private function siteName(): string
    {
        return (string) ($this->settings->get('general.site_name') ?: shopper_setting('name') ?: config('app.name'));
    }

    private function logo(): ?string
    {
        $logo = SeoUrls::upload($this->settings->get('schema.logo_upload_id'));

        if ($logo !== null) {
            return $logo;
        }

        $storeLogo = shopper_setting('logo');

        if (is_string($storeLogo) && $storeLogo !== '') {
            return Storage::disk((string) config('shopper.media.storage.disk_name', 'public'))->url($storeLogo);
        }

        return SeoUrls::disk($this->settings->get('general.site_icons.512'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function address(): ?array
    {
        $countryId = shopper_setting('country_id');
        $country = $countryId ? Country::query()->find($countryId) : null;

        $address = self::filter([
            '@type' => 'PostalAddress',
            'streetAddress' => shopper_setting('street_address'),
            'addressLocality' => shopper_setting('city'),
            'addressRegion' => shopper_setting('state'),
            'postalCode' => shopper_setting('postal_code'),
            'addressCountry' => $country?->cca2,
        ]);

        return count($address) > 1 ? $address : null;
    }

    /**
     * Social profiles: the SEO Social tab, then the store's own links.
     *
     * @return array<int, string>
     */
    private function sameAs(): array
    {
        $social = $this->settings->group('social');
        $handle = mb_ltrim((string) ($social['twitter_handle'] ?? ''), '@');

        $urls = [
            $social['facebook_url'] ?? null,
            $handle !== '' ? 'https://x.com/'.$handle : null,
            ...array_values((array) ($social['profiles'] ?? [])),
            shopper_setting('facebook_link'),
            shopper_setting('instagram_link'),
            shopper_setting('twitter_link'),
        ];

        return array_values(array_unique(array_filter(
            $urls,
            fn (mixed $url): bool => is_string($url) && preg_match('#^https?://#i', $url) === 1,
        )));
    }
}
