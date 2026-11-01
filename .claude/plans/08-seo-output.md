# 08: SEO output: head tags, JSON-LD, sitemaps, robots.txt, GTM

Status: Done
Depends on: 06, 07

## Goal

Turn plan 07's settings and per-entry data into correct output for every page, for both
server-rendered Laravel apps (Blade components) and headless storefronts (JSON API), plus
`sitemap.xml` and `robots.txt`. Audit the result with the `seo-auditor` agent.

## Core: `SeoManager` → `SeoData`

`Shopper\Seo\SeoManager::for(Model|string $pathOrModel, array $context = []): SeoData`

`SeoData` (immutable DTO, `toArray()` for the API) holds:
- `title`, `description`, `canonical`, `robots` (string, e.g. `index, follow, max-image-preview:large`)
- `meta[]` (name/property + content: description, robots, OG, Twitter, verification codes)
- `links[]` (canonical, prev/next for paginated archives, icons, manifest, `alternate` hreflang later)
- `jsonLd` (one `@graph` array)
- `gtm` (container ID or null), `customHead`, `customBodyOpen`, `customBodyClose` (raw, from plan 07)

Resolution order and auto-generation rules are defined in plan 07 ("Auto-generation rules").
Title/description templates are rendered by a small `TemplateRenderer` with the variables listed there.
Everything is cached per entity + locale and busted when the entity, its SEO meta or SEO settings change.

## Head and body output

Blade components (namespace `shopper-seo`):
- `<x-shopper-seo::head :for="$post" />`: title, meta, links, JSON-LD script, GTM head snippet,
  custom head code.
- `<x-shopper-seo::body-open />`: GTM `<noscript><iframe …></noscript>`, custom body-open code.
- `<x-shopper-seo::body-close />`: custom body-close code.

Escaping: every value is escaped except the explicitly raw custom code. GTM snippets are built from
the validated container ID only. JSON-LD is encoded with `JSON_HEX_TAG | JSON_UNESCAPED_SLASHES |
JSON_UNESCAPED_UNICODE` so `</script>` cannot break out.

Optional middleware `AddXRobotsTag` sends `X-Robots-Tag` for non-HTML responses on noindex paths.

## JSON-LD graph

One `@graph` with `@id` references (Yoast style):

| Page | Nodes |
|------|-------|
| Every page | `WebSite` (+ `SearchAction` when a search URL template is set), `Organization` or `Person` (logo, `sameAs`, contact, address from store settings) |
| Home | + `WebPage` |
| Post | `WebPage` + `Article`/`BlogPosting`/`NewsArticle` (headline, image, datePublished, dateModified, author `Person`, publisher → Organization, articleSection = categories, keywords = tags) + `BreadcrumbList` |
| Page | `WebPage` (or AboutPage/ContactPage/FAQPage override) + `BreadcrumbList` |
| Post category / tag | `CollectionPage` + `BreadcrumbList` |
| Product | `Product` (name, description, sku, gtin from barcode, brand, image, `offers` `Offer` with price/currency/availability from stock, `aggregateRating` + `review` from approved reviews) + `BreadcrumbList` |
| Shop category / collection / brand | `CollectionPage` + `BreadcrumbList` (+ `ItemList` of products, optional) |

Entry-level `schema_custom` JSON is merged in last. Validate output against schema.org types in tests
(structure checks) and manually with Google's Rich Results Test.

## Sitemaps

- `GET /sitemap.xml`: sitemap index listing one sitemap per registered type that has indexable entries.
- `GET /sitemap-{type}-{page}.xml`: max 1,000 URLs per file (well under the 50,000 limit), `<loc>`,
  `<lastmod>` (updated_at), `<image:image>` for featured/product images.
- Includes only published, public, indexable entries not marked `exclude_from_sitemap`, and
  respects type defaults and URL-pattern noindex rules. Products: only visible/published ones.
- Cached; regenerated on content/SEO changes (queued job), `Cache-Control` headers.
- Optional XSL stylesheet so the sitemap is readable in a browser.
- No search-engine "ping" (Google retired it in 2023); the robots.txt `Sitemap:` line is enough.

## robots.txt

`GET /robots.txt` serves the editable body from plan 07, appends `Sitemap: {app_url}/sitemap.xml`,
and returns `User-agent: * / Disallow: /` while "Discourage search engines" is on. Must not conflict
with a static `public/robots.txt` (document: delete it or the route never runs).

## Headless API

- `GET {api}/seo?path=/2026/10/hello-world`: `SeoData::toArray()` for that path (uses plan 06's resolver,
  falls back to URL-pattern rules and type defaults for paths the CMS doesn't own, such as `/cart`).
- The CMS resolve endpoint (plan 06) embeds the same `seo` object.
- Sitemaps and robots.txt are also available under the API prefix for storefronts on another domain,
  with URLs built from `config('shopper.seo.frontend_url')`.

## Phases

### Phase 1: SeoManager
- [x] `SeoData`, `TemplateRenderer`, `SeoManager` with resolution order, caching and busting.
- [x] Tests: each fallback level (entry → template → defaults → site switch), description generation
      (HTML stripped, word boundary), pagination titles, canonical rules.

### Phase 2: head/body output
- [x] Blade components, escaping, GTM snippets, verification metas, icons/manifest links.
- [x] Tests: snapshot of the head for a post/page/product/home, XSS attempts in titles and descriptions,
      GTM rejected when invalid, custom code only when set.

### Phase 3: JSON-LD
- [x] Graph builder with node factories per type; Product uses prices, stock and reviews from core.
- [x] Tests: valid JSON, `@id` links resolve within the graph, required properties present per type.

### Phase 4: sitemaps and robots.txt
- [x] Routes, generators per registered type, index, caching and regeneration job, XSL.
- [x] robots.txt route.
- [x] Tests: noindex/excluded entries absent, pagination of sitemap files, discourage switch.

### Phase 5: API and audit
- [x] SEO API endpoint + embedding in the CMS resolve endpoint.
- [x] Demo: a minimal Blade layout in the workbench that renders posts/pages with the components,
      so the output can be checked in a browser.
- [x] Run the `seo-auditor` agent on the home page, a post, a page, a category, a product,
      a noindex URL, `sitemap.xml` and `robots.txt`; fix findings.

## Acceptance criteria

- Every indexable page has a unique title and description, a canonical, OG/Twitter tags and a valid
  JSON-LD graph; noindex pages are excluded from sitemaps; GTM appears in head and body when set.

## Progress log

- 2026-11-01 Phase 1: `SeoManager::for(Model|string|null, context)` → `Data\SeoData` (readonly, `toArray()`/`fromArray()`),
  `build()` uncached. Each registered type now has a `Contracts\SeoSource` (URL, public?, template variables, image,
  og:type, dates, empty?, breadcrumbs, schema nodes, sitemap query/images): shop sources in `packages/seo/src/Sources`
  (`ProductSource`, `CategorySource`, `BrandSource`, `CollectionSource`, storefront paths from `shopper.seo.paths`,
  base URL `shopper.seo.frontend_url` → CMS frontend URL → APP_URL), CMS sources in `packages/cms/src/Seo`
  (`PostSource` for posts and pages, `TermSource`). `Contracts\PathResolver` turns a path into an entry
  (`ShopPathResolver`, `CmsPathResolver` on top of the permalink resolver); drafts and disabled entries are never
  resolved, so their titles cannot leak through the API. Title: entry → type template → `%title% %sep% %sitename%`,
  " - Page 2 of 5" appended on page ≥ 2 when the template has no `%page%`. Description: entry → rendered template →
  excerpt/content (HTML stripped, word boundary, 160 chars); password-protected posts have none. Canonical: override →
  permalink/storefront URL, page ≥ 2 → `?page=N` (self), omitted on noindex pages (Yoast). Robots: entry →
  `Support\IndexingRules` (URL patterns) → type default / paginated / empty terms → not public = noindex;
  "discourage" wins; adds `max-image-preview`, `max-snippet`, `max-video-preview` and the advanced flags. OG image:
  entry → featured/product image → type default → site default. `Support\SeoCache` (version key, `cache_ttl`)
  flushed by settings saves, `seo_meta`, every registered model, prices, reviews and `CmsCache::flush()`.
  **Deviation:** `schema.article_type` is only the fallback when the post type template has no schema type.
  **Gotcha:** core's `getPrice()` misses the currency when `prices` is not loaded; the product source eager-loads
  `prices.currency`. 19 tests.
- 2026-11-01 Phase 2: anonymous components `<x-shopper-seo::head :for :context />` (model, path, `SeoData` or null =
  current path), `<x-shopper-seo::body-open />`, `<x-shopper-seo::body-close />` (`SeoManager::tracking()`, no page
  lookup). Everything escaped except custom code; JSON-LD encoded with `JSON_HEX_TAG|JSON_HEX_AMP`; GTM/GA4 IDs and
  verification codes re-validated before output (GA4 only without GTM); icons + manifest links from the generated
  site icons. Middleware alias `shopper-seo.robots` (`AddXRobotsTag`) for non-HTML responses. 9 tests.
- 2026-11-01 Phase 3: `Schema\GraphBuilder`: WebSite (+ SearchAction), Organization (or LocalBusiness subtype with
  opening hours, or Person; logo from the SEO setting → store logo → site icon; contact and address from store
  settings; `sameAs` from SEO social + store links), WebPage (type override: About/Contact/FAQ/Collection…),
  BreadcrumbList (breadcrumb title override), then the source nodes: Article/BlogPosting/NewsArticle for posts
  (author, publisher, sections, keywords, word count), Product (sku, gtin8/12/13/14 from the barcode, brand, images,
  `Offer` or `AggregateOffer` from variants, availability from stock/backorder, `aggregateRating` + 5 latest approved
  reviews); custom JSON-LD merged last (`@graph`, list or single node). 9 tests (every `@id` reference resolves).
- 2026-11-01 Phase 4: `Sitemaps\SitemapBuilder` + `Http\Controllers\SitemapController`: `/sitemap.xml`,
  `/sitemap-{type}-{page}.xml` (`sitemaps.per_page`, default 1000; `lastmod`, `image:image`), `/sitemap.xsl`,
  `/robots.txt` (`Support\RobotsTxt`: edited body + `Sitemap:` line; `Disallow: /` while discouraged), headers
  `Cache-Control` and `X-Robots-Tag: noindex, follow`; same routes under `{api}/seo/…` with storefront URLs. Entries
  must be public, indexable (same rules as the head) and not excluded; types with "show in sitemap" off are left out.
  Cached through `SeoCache`; `Jobs\GenerateSitemapsJob` (unique) rebuilds after changes when
  `shopper.seo.sitemaps.queue` is on. **Deviation:** off by default (a sync queue would rebuild sitemaps inside every
  save); without it, sitemaps rebuild lazily on the next request. **Deviation:** the XSL stylesheet is opt-in
  (`sitemaps.stylesheet`): Chrome now warns that XSLT is being removed. Root routes can be turned off
  (`SHOPPER_SEO_ROUTES=false`); a static `public/robots.txt` must be deleted. 10 tests.
- 2026-11-01 Phase 5 (part): `GET {api}/seo?path=&page=&last_page=&search=&not_found=` (`SeoController`), the CMS
  resolve endpoint now returns the same `seo` object (archives with page context). The CMS public layout uses the
  three components, `SHOPPER_CMS_ROUTES=true` in `workbench/.env(.example)` for the demo. **Fixed on the way:** the
  demo `CatalogSeeder` never set slugs on products/brands/categories/collections (dev rows backfilled). 6 tests; full
  suite 1356 pass, Pint and PHPStan clean.
- 2026-11-01 Phase 5 (audit): `seo-auditor` on the dev server (post, pages, category archive, noindex post, product
  and home through the API, `/checkout`, sitemaps, robots.txt). Fixed: (1) **archive heads described the last post of
  the loop** (the layout read the leftover `$post`): the controller now passes `seoSubject`/`seoContext`
  explicitly; (2) pages past the last one: 404 in the CMS archive, and `noindex` + no "Page 4 of 1" in
  `SeoManager`; (3) archive pagination links now match the canonical/prev/next URLs (no `?page=1`); (4) parent
  categories (shop and CMS) with products/posts only in subcategories were "empty" → noindex, so breadcrumbs pointed
  to noindex pages: emptiness now counts descendants; (5) no images anywhere in the demo: `SeoSeeder` (workbench)
  creates a default social image and a logo (+ site icons), and the Product node falls back to the page image;
  (6) term sitemaps get `lastmod` (newest post); (7) the home page gets `sitemap-home-1.xml` unless a CMS front page
  already lists it. **Not changed:** unknown paths stay indexable in `/api/seo` (the storefront must pass
  `not_found=1` or `search=`); slash/no-slash spellings are not redirected (Laravel's default .htaccess strips
  trailing slashes, a redirect adding one would loop), the canonical covers them; no admin warning yet when the
  default social image or logo is missing. Regression tests in `tests/Seo/PublicSeoOutputTest.php` (7). Full suite
  1363 pass, Pint and PHPStan clean.
