# 07: SEO module, settings and per-entry meta

Status: Done (phase 4, optional analysis, skipped)
Depends on: 01 (06 for canonical URLs of CMS content)

## Goal

An "SEO" menu in the admin (like Yoast / Rank Math) to configure site identity, title and
description templates, social defaults, schema, Google Tag Manager, verification codes, indexing
rules (noindex for many pages at once), plus an SEO panel on every post, page, term, product,
category, brand and collection. Plan 08 turns this data into the actual tags.

## Admin screens (sidebar group "SEO", permission `manage_seo`)

1. **General / Site identity**
   - Website name, tagline, title separator (`-`, `|`, `·`, `»`, `–`).
   - Website avatar / site icon: square image from the Uploads library (min 512×512). Generates
     16, 32, 180 (apple-touch), 192, 512 PNGs and `site.webmanifest` (spatie/image is available
     through media-library). Also the default Organization logo.
   - Home page title + meta description (when the front page is not a CMS page).
   - Site-wide "Discourage search engines from indexing this site" (WordPress Reading setting):
     forces `noindex, nofollow` everywhere and `Disallow: /` in robots.txt. Big warning banner in the admin while on.
2. **Titles & meta (templates)**: one tab per content type (Posts, Pages, Post categories, Tags,
   Products, Shop categories, Brands, Collections, Archives, Search, 404).
   Per type: title template, description template, default robots (index / noindex), show in sitemap,
   default schema type, default OG image.
   Variables: `%title%` `%sitename%` `%tagline%` `%sep%` `%excerpt%` `%category%` `%tag%`
   `%author%` `%date%` `%modified%` `%currentyear%` `%page%` (pagination "Page 2 of 5")
   `%price%` `%brand%` (products). Live preview with a Google-style snippet.
3. **Social**: default OG image, Facebook page URL, app ID, X/Twitter handle, default card type
   (`summary_large_image`), other profiles (Instagram, LinkedIn, YouTube, TikTok) used in
   Organization `sameAs`. Prefill from the existing store settings (`facebook_link`, `instagram_link`, `twitter_link`).
4. **Schema (JSON-LD)**: Organization vs Person, name, logo, contact (email/phone prefilled from store
   settings), address (store address), LocalBusiness subtype + opening hours (optional),
   WebSite with SearchAction (search URL template), default Article type for posts
   (Article / BlogPosting / NewsArticle).
5. **Indexing / noindex**:
   - Per content type defaults (from tab 2) shown together.
   - Noindex for: date archives, author archives, search results, paginated pages (page ≥ 2),
     empty taxonomy pages, attachment-like URLs.
   - Noindex URL patterns: a list of paths with `*` wildcards (`/cart*`, `/checkout*`, `/account/*`)
     with optional `nofollow`. Defaults seeded for shop pages (cart, checkout, account).
   - Bulk actions in every list (posts, pages, products, categories…): "Set noindex", "Set index",
     "Remove from sitemap".
6. **Tracking & verification**:
   - Google Tag Manager container ID (validated `GTM-XXXXXXX`), output in head and right after `<body>` (plan 08).
   - Google Analytics 4 measurement ID (only if GTM is not used).
   - Verification codes: Google Search Console, Bing, Yandex, Pinterest, Facebook domain verification.
     Stored as the content value only; the meta tag is built by the code.
   - Custom head code / body-open code / body-close code: raw HTML for anything else.
     Separate permission `manage_seo_scripts` (admins only), audit log of changes, clear warning.
7. **robots.txt editor**: editable body with the sitemap line appended automatically, reset to default.
8. **Redirects** link to plan 06's screen.

All values in the `Setting` model with `seo_` keys (it already stores arrays/JSON).

## Per-entry SEO panel

A reusable Filament schema section `SeoPanel` added to edit forms of posts, pages, post categories,
tags, products, shop categories, brands and collections:

- Google preview (desktop/mobile), character counters (title ~60, description ~160, pixel-width approximation).
- SEO title (placeholder shows the template result), meta description (placeholder = auto description),
  slug link, focus keyword (optional, for the analysis).
- Advanced: robots index (Default / Index / Noindex), follow (Default / Follow / Nofollow), noarchive,
  nosnippet, noimageindex, max-image-preview, canonical URL override, exclude from sitemap,
  breadcrumb title.
- Social: OG title, description, image (Uploads picker); X/Twitter title, description, image.
- Schema: type override (WebPage, AboutPage, ContactPage, FAQPage, Article, BlogPosting, NewsArticle,
  Product…) and an advanced "custom JSON-LD" field validated as JSON (permission `manage_seo_scripts`).
- Analysis (optional phase): checklist like Yoast: keyword in title/description/slug/first paragraph,
  title and description length, images have alt text, at least one internal link, content length.

## Data model

`seo_meta` (morph one per entity): common fields, `seoable_type`, `seoable_id` (unique pair),
`title`, `description`, `focus_keyword`, `canonical_url`, `robots_index` (nullable bool, null = inherit),
`robots_follow` (nullable bool), `robots_advanced` (json), `exclude_from_sitemap` (bool),
`breadcrumb_title`, `og_title`, `og_description`, `og_image_upload_id`, `twitter_title`,
`twitter_description`, `twitter_image_upload_id`, `schema_type`, `schema_custom` (json).

Existing columns `seo_title` / `seo_description` on products, categories, collections and brands
stay the source of truth for title/description of those models (the admin already edits them);
`seo_meta` adds the other fields. A `HasSeo` trait gives every model `seo()` and a unified
`seoTitle()` / `seoDescription()` that reads the column when it exists, otherwise `seo_meta`.

Auto-generation rules (used when fields are empty, implemented in plan 08 but defined here):
- Title: type template → `%title% %sep% %sitename%`.
- Description: type template → otherwise excerpt → otherwise first ~155 characters of the content
  with HTML stripped, cut at a word boundary.
- Canonical: the permalink (plan 06) or the storefront URL for shop entities; page ≥ 2 canonical
  to itself (not to page 1, per Google guidance).
- OG image: entry OG image → featured image / product image → type default → site default.
- Robots: entry value → URL pattern rules → type default → site-wide "discourage" switch wins over all.

## Code layout (packages/seo)

```
src/SeoServiceProvider.php, src/SeoAddon.php
src/Models/SeoMeta.php, src/Concerns/HasSeo.php
src/Settings/SeoSettings.php            typed accessors over Setting (seo_* keys), cached
src/Registry/SeoableRegistry.php        which models get the panel and sitemap: shop models registered here, CMS registers its own
src/Filament/SeoPanel.php               schema section reused by all edit forms
src/Filament/Actions/SetIndexingBulkAction.php
src/Livewire/Pages/{General,Titles,Social,Schema,Indexing,Tracking,RobotsTxt}.php
src/Actions/GenerateSiteIconsAction.php
src/Rules/GtmContainerId.php, src/Rules/JsonLd.php
```

How the panel reaches existing admin forms (products etc.) without editing them: check for render
hooks / form extension points first. If none exist, add one generic hook in `packages/admin`
(for example a configurable list of extra schema components per form) rather than hard-coding SEO.

## Phases

### Phase 1: package and settings storage
- [x] Create `packages/seo` (shopper-package skill), `SeoAddon`, sidebar group, permissions
      `manage_seo`, `manage_seo_scripts`.
- [x] `SeoSettings` with defaults, cache invalidated on save.
- [x] Tests: defaults, cache busting.

### Phase 2: settings screens
- [x] General (incl. site icon generation), Titles & meta (with variable picker + preview), Social, Schema,
      Indexing, Tracking & verification, robots.txt.
- [x] Validation: GTM ID, GA4 ID (`G-XXXXXXX`), verification codes (no HTML), JSON-LD.
- [x] Tests per screen: authorization, validation, persistence; scripts require `manage_seo_scripts`.

### Phase 3: per-entry panel
- [x] `seo_meta` migration, `SeoMeta`, `HasSeo`, registry.
- [x] `SeoPanel` on post/page/term forms (CMS) and product/category/brand/collection forms (via hook).
- [x] Bulk "Set noindex / Set index / Remove from sitemap" actions on those tables.
- [x] Tests: panel saves and reloads, inherit vs explicit robots, bulk actions.

### Phase 4: analysis (optional)
- [ ] Content checks + score badge column in post lists.

## Acceptance criteria

- Every SEO value can be set globally, per content type, and per entry, and the panel shows exactly
  what will be output when a field is left empty.

## Progress log

- 2026-11-01 Integration decisions (from reading the admin): the shop already edits `seo_title`/`seo_description`
  for products, categories, brands and collections (`Shopper\Components\Form\SeoField`); categories, brands and
  collections are edited in slide-overs without render hooks, and render-hook closures get no context. So: (1) an
  "Advanced SEO" tab is added to the product edit page through `ProductRenderHook::EDIT_TABS_END` /
  `EDIT_CONTENT_AFTER` (the product comes from the route); (2) an SEO "bulk editor" screen per content type gives
  every entry (shop entities and CMS content) the SEO panel plus bulk index/noindex/sitemap actions, without editing
  admin forms; (3) the CMS editor and term forms embed the panel directly; (4) the CMS now depends on `shopper/seo`
  and registers its types, SEO stays independent of the CMS; (5) the upload picker moves from the CMS to the admin
  package (next to the Uploads library) so SEO can use it.
- 2026-11-01 Phase 1: `packages/seo` (`Shopper\Seo`, provider, `SeoAddon` attached via `callAfterResolving`, root
  autoload/replace/providers, split workflow, phpunit sources), `Registry\SeoableRegistry` (shop types from
  `shopper.seo.types`; CMS registers post/page/post_category/post_tag; `keyFor()` tells posts and pages apart),
  `Settings\SeoSettings` (one `Setting` row per group `seo_{general,titles,social,schema,indexing,tracking,robots}`
  merged with `SeoDefaults`, cached forever, flushed on save), permissions `manage_seo` and `manage_seo_scripts`
  (group `seo`, granted to administrators). **Deviation:** settings are stored per group (7 rows) rather than one
  key per option. Sidebar group comes with the screens in phase 2. 6 tests; CMS suite still 225 pass.
- 2026-11-01 Phase 2: **Deviation:** one screen `/cpanel/seo/settings` (`Livewire\Pages\SeoSettingsPage`) with seven tabs
  (General, Titles & meta, Social, Schema, Indexing, Tracking & verification, robots.txt; tab kept in the query
  string) instead of seven pages; sidebar group "SEO" with "SEO settings" and the CMS "Redirects". General: site
  name/tagline/separator, website avatar → `GenerateSiteIconsAction` (16/32/180/192/512 PNGs + `site.webmanifest`
  on the uploads disk, regenerated when the avatar changes), homepage title/description, "Discourage search
  engines" (red banner on the screen while on). Titles & meta: per registered type title/description templates
  with a live Google-style preview (`Support\TemplateRenderer`, also used by plan 08: variables, empty variables
  vanish without dangling separators, `excerpt()` cuts at a word boundary), noindex, sitemap, schema type, default
  social image; plus search and 404 pages. Social, Schema (organization/person, logo, contact, LocalBusiness type +
  opening hours, search URL with `{search_term_string}`, article type), Indexing (archive toggles + noindex path
  patterns), Tracking (GTM `GTM-…`, GA4 `G-…`, verification codes without HTML, custom head/body code disabled and
  ignored on save without `manage_seo_scripts`), robots.txt. The upload picker moved to
  `Shopper\Components\Form\UploadPicker` (admin package). **Bugs found and fixed:** `array_replace_recursive()`
  merged lists item by item, so removed noindex paths came back; settings now replace lists whole
  (`SeoSettings::merge()`). `Route::has()` was unreliable while the sidebar is built (routes named after being
  added), so the Redirects item checks that the CMS is installed instead. Custom JSON-LD validation belongs to the
  per-entry panel (phase 3). Browser: tabs, previews, save; console clean. 9 tests (+6 renderer).
- 2026-11-01 Phase 3: `seo_meta` (morph one per entry, unique pair, FKs to uploads for OG/Twitter images) +
  `Models\SeoMeta`; meta rows are deleted with their entry (`forceDeleted` for soft-deleting models, else
  `deleted`). **Deviation:** no `HasSeo` trait (it would mean editing the shop models); `Support\SeoFields` reads and
  writes an entry's SEO state for any registered model instead (title/description go to the existing
  `seo_title`/`seo_description` columns when the model has them, everything else to `seo_meta`; tri-state robots
  `default`/`yes`/`no` with null = inherit; unsafe canonicals dropped; custom JSON-LD validated and only stored with
  `manage_seo_scripts`). `Filament\SeoPanel` (General with Google-style preview + counters / Advanced / Social /
  Schema) is embedded in the post and page editors (preview built from the type template and the permalink) and in
  the post category/tag dialogs. Products get an "Advanced SEO" tab through `ProductRenderHook::EDIT_TABS_END` /
  `EDIT_CONTENT_AFTER` (`Livewire\Components\EntrySeoPanel`, without title/description, which stay in the shop's own
  SEO tab). **Deviation:** shop categories, brands and collections are edited in slide-overs without hooks, so they
  (and every other type) get the panel from the new `/cpanel/seo/content` screen (`Livewire\Pages\ContentSeo`, one
  tab per registered type, edit modal). Bulk actions (`Filament\SeoBulkActions`: hide from / show in search engines,
  use the type default, remove from / add to sitemap) are on Content SEO and on the post and page lists. The SEO
  settings screen moved to `/cpanel/seo/settings` (route name unchanged): the sidebar's prefix match highlighted it
  on Content SEO. Browser: post editor panel saved (explicit noindex, follow left on inherit), Content SEO list
  badges, bulk "remove from sitemap" on 2 posts, product tab save; console clean apart from Chrome's form-label
  notices that every Filament page shows. **Not done:** plan 05's revision `meta` snapshot of SEO fields (revisions
  still restore title/content/excerpt only). 15 tests; full suite 1303 pass, Pint and PHPStan clean.
- 2026-11-01 Phase 4 (optional content analysis) skipped: it needs the rendered output from plan 08 to be worth much.
  It can be added later on top of `SeoFields` and `TemplateRenderer`.
