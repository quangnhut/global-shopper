<?php

declare(strict_types=1);

namespace Shopper\Seo\Sitemaps;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Shopper\Seo\Models\SeoMeta;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\Seo\SeoManager;
use Shopper\Seo\Settings\SeoSettings;
use Shopper\Seo\Support\SeoCache;
use Shopper\Seo\Support\SeoUrls;

/**
 * XML sitemaps: an index (`/sitemap.xml`) listing one or more files per content type
 * (`/sitemap-{type}-{page}.xml`). Only public, indexable entries not excluded from the sitemap are listed.
 */
final class SitemapBuilder
{
    public const string NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public const string IMAGE_NAMESPACE = 'http://www.google.com/schemas/sitemap-image/1.1';

    public function __construct(
        private readonly SeoableRegistry $registry,
        private readonly SeoSettings $settings,
        private readonly SeoManager $manager,
    ) {}

    /**
     * Files of the index: type, page number, last modification.
     *
     * @return array<int, array{type: string, page: int, loc: string, lastmod: ?string}>
     */
    public function files(): array
    {
        if ($this->settings->get('general.discourage_search_engines')) {
            return [];
        }

        $files = [];
        $homeListed = false;

        foreach (array_keys($this->registry->all()) as $type) {
            $homeListed = $homeListed || in_array(SeoUrls::home(), array_column($this->urls($type), 'loc'), true);

            foreach (array_chunk($this->urls($type), $this->perPage()) as $index => $urls) {
                $dates = array_filter(array_column($urls, 'lastmod'));

                $files[] = [
                    'type' => $type,
                    'page' => $index + 1,
                    'loc' => SeoUrls::absolute('/sitemap-'.$type.'-'.($index + 1).'.xml'),
                    'lastmod' => $dates === [] ? null : max($dates),
                ];
            }
        }

        // The home page has its own file unless a CMS front page already lists it.
        if (! $homeListed && $this->homeUrls() !== []) {
            array_unshift($files, ['type' => 'home', 'page' => 1, 'loc' => SeoUrls::absolute('/sitemap-home-1.xml'), 'lastmod' => null]);
        }

        return $files;
    }

    public function index(): string
    {
        return SeoCache::remember('sitemap:index', function (): string {
            $xml = $this->open('sitemapindex', '');

            foreach ($this->files() as $file) {
                $xml .= '<sitemap><loc>'.self::escape($file['loc']).'</loc>'
                    .($file['lastmod'] !== null ? '<lastmod>'.$file['lastmod'].'</lastmod>' : '')
                    ."</sitemap>\n";
            }

            return $xml.'</sitemapindex>'."\n";
        });
    }

    /**
     * One file of a type, or null when that page does not exist.
     */
    public function page(string $type, int $page): ?string
    {
        if ($page < 1 || $this->settings->get('general.discourage_search_engines')) {
            return null;
        }

        if ($type === 'home') {
            $listed = collect($this->files())->contains(fn (array $file): bool => $file['type'] === 'home');

            return $listed && $page === 1 ? $this->urlset($this->homeUrls()) : null;
        }

        if (! $this->registry->has($type)) {
            return null;
        }

        return SeoCache::remember("sitemap:{$type}:{$page}", function () use ($type, $page): ?string {
            $urls = array_slice($this->urls($type), ($page - 1) * $this->perPage(), $this->perPage());

            return $urls === [] ? null : $this->urlset($urls);
        });
    }

    /**
     * Builds and caches every file, e.g. from the queued job after a change.
     */
    public function warm(): void
    {
        $this->index();

        foreach ($this->files() as $file) {
            $this->page($file['type'], $file['page']);
        }
    }

    /**
     * Indexable entries of a type, in the source's order.
     *
     * @return array<int, array{loc: string, lastmod: ?string, images: array<int, string>}>
     */
    public function urls(string $type): array
    {
        $source = $this->registry->source($type);

        if ($source === null || ! ($this->settings->get("titles.{$type}.sitemap") ?? true)) {
            return [];
        }

        return SeoCache::remember("sitemap:urls:{$type}", function () use ($type, $source): array {
            $urls = [];

            $source->sitemapQuery($type)->chunk(500, function (Collection $entries) use ($type, $source, &$urls): void {
                $metas = $this->metas($entries);

                foreach ($entries as $entry) {
                    if (! $this->manager->inSitemap($entry, $type, $metas[$entry->getKey()] ?? null, $source)) {
                        continue;
                    }

                    $modified = $source->modifiedAt($entry);

                    $urls[] = [
                        'loc' => $source->url($entry),
                        'lastmod' => $modified instanceof CarbonInterface ? $modified->toAtomString() : null,
                        'images' => $source->sitemapImages($entry),
                    ];
                }
            });

            return $urls;
        });
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param  array<int, array{loc: string, lastmod: ?string, images: array<int, string>}>  $urls
     */
    private function urlset(array $urls): string
    {
        $xml = $this->open('urlset', ' xmlns:image="'.self::IMAGE_NAMESPACE.'"');

        foreach ($urls as $url) {
            $xml .= '<url><loc>'.self::escape($url['loc']).'</loc>'
                .($url['lastmod'] !== null ? '<lastmod>'.$url['lastmod'].'</lastmod>' : '');

            foreach ($url['images'] as $image) {
                $xml .= '<image:image><image:loc>'.self::escape($image).'</image:loc></image:image>';
            }

            $xml .= "</url>\n";
        }

        return $xml.'</urlset>'."\n";
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string, images: array<int, string>}>
     */
    private function homeUrls(): array
    {
        return $this->manager->for('/')->isIndexable()
            ? [['loc' => SeoUrls::home(), 'lastmod' => null, 'images' => []]]
            : [];
    }

    private function perPage(): int
    {
        return max(1, min(50000, (int) config('shopper.seo.sitemaps.per_page', 1000)));
    }

    /**
     * @param  Collection<int, Model>  $entries
     * @return array<int|string, SeoMeta>
     */
    private function metas(Collection $entries): array
    {
        $first = $entries->first();

        if ($first === null) {
            return [];
        }

        return SeoMeta::query()
            ->where('seoable_type', $first->getMorphClass())
            ->whereIn('seoable_id', $entries->modelKeys())
            ->get()
            ->keyBy('seoable_id')
            ->all();
    }

    private function open(string $root, string $attributes): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .(config('shopper.seo.sitemaps.stylesheet') ? '<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>'."\n" : '')
            .'<'.$root.' xmlns="'.self::NAMESPACE.'"'.$attributes.'>'."\n";
    }
}
