# 06: Permalinks, redirects and the content API

Status: Done
Depends on: 03
Decision (2026-11-01): JSON API always + optional Blade routes, off by default.

## Goal

A Settings → Permalinks screen like WordPress, a URL generator and resolver for posts, pages,
categories and tags, automatic 301 redirects when URLs change, and a headless API so a storefront
can turn any path into content (and, after plan 08, its SEO data).

## WordPress behaviour to match

Common settings (radio list, with an example URL next to each):

| Name | Structure |
|------|-----------|
| Plain | `/?p=123` |
| Day and name | `/%year%/%monthnum%/%day%/%postname%/` |
| Month and name | `/%year%/%monthnum%/%postname%/` |
| Numeric | `/archives/%post_id%` |
| Post name | `/%postname%/` |
| Custom | free text using tags |

Tags: `%year%` `%monthnum%` `%day%` `%hour%` `%minute%` `%second%` `%post_id%` `%postname%`
`%category%` (primary category path, nested as `parent/child`) `%author%`.

Optional: category base (default `category`), tag base (default `tag`).
Pages always use their hierarchical path: `/parent/child/`. A page can be the front page
(Settings → Reading "Your homepage displays") and another the posts page.

When a slug changes, the old URL 301-redirects to the new one (WordPress's `_wp_old_slug`).

## Settings (`Setting` model)

`cms_permalink_structure`, `cms_category_base`, `cms_tag_base`, `cms_trailing_slash` (bool),
`cms_front_page_id`, `cms_posts_page_id`, `cms_permalink_history` (previous structures, so old URLs
still resolve and redirect after a structure change).

## Data model

`cms_redirects`: common fields, `from_path` (string, unique, normalized: leading slash, no trailing
slash, lowercase), `to_path`, `status_code` (301/302/410), `hits` (int), `last_hit_at`, `source`
(`auto` / `manual`). Chains are collapsed on save (A→B, B→C becomes A→C); loops are rejected.

## Code layout (packages/cms)

```
src/Permalinks/PermalinkStructure.php   parse a structure string, validate tags, build a regex
src/Permalinks/PermalinkGenerator.php   url(Post|PostCategory|PostTag): absolute URL using config('app.url') or a configurable frontend URL
src/Permalinks/PermalinkResolver.php    resolve(string $path): ResolvedRoute (model + type) or Redirect or null
src/Permalinks/ReservedPaths.php        admin prefix, api prefix, storage, sitemap*.xml, robots.txt, and the shop's own routes from config
src/Actions/RecordSlugChangeAction.php  on slug/parent/primary-category/date change → redirect from the old URL
src/Livewire/Pages/Settings/Permalinks.php   setting item registered by CmsAddon (Settings screen)
src/Http/Controllers/Api/ResolveController.php   GET {api}/cms/resolve?path=/2026/10/hello-world
src/Http/Controllers/Api/PostsController.php     GET {api}/cms/posts (filters: type, category, tag, search, page)
src/Http/Middleware/HandleCmsRedirects.php       optional, for apps rendering through Laravel routes
routes/api.php, routes/web.php (web routes only when config('shopper.cms.routes.enabled'))
```

API response for resolve (200): `{ type: "post"|"page"|"category"|"tag", data: {...}, seo: {...} (plan 08) }`;
`301` with `{ redirect: "/new-path" }`; `404` otherwise. Only published + public content is returned
unless a valid preview signature (plan 03) is present. Password-protected posts return the title and
`protected: true` without content until the password is posted.

## Phases

### Phase 1: generator and resolver
- [x] `PermalinkStructure` parsing + validation (must contain `%postname%` or `%post_id%`; no reserved prefixes).
- [x] Generator for posts, pages (hierarchical path), categories (nested path), tags; front page → `/`.
- [x] Resolver, including old structures from `cms_permalink_history`.
- [x] Tests: every common structure round-trips (generate → resolve gives the same post), nested categories,
      nested pages, conflicts with reserved paths rejected, trailing-slash setting.

### Phase 2: settings screen
- [x] Settings → Permalinks: radio list with live example URLs, custom field with tag buttons, bases.
- [x] Settings → Reading: front page and posts page selectors (could live in plan 07's Reading screen instead).
- [x] Changing the structure stores the previous one in history.
- [x] Permission `manage_permalinks`. Tests for validation and history.

### Phase 3: redirects
- [x] `cms_redirects` table, model, chain collapsing, loop detection.
- [x] Automatic redirects on slug/parent/category/date changes and on structure changes.
- [x] Admin screen to list/add/edit/delete manual redirects with hit counts.
- [x] Tests: old slug redirects, chains collapse, loops rejected, 410 support.

### Phase 4: API and optional web routes
- [x] Resolve and posts endpoints (JSON resources), rate limited, cached per path (cache busted on save).
- [x] Optional web routes: catch-all `Route::fallback` style registration behind config, rendering
      publishable views `shopper-cms::post`, `page`, `archive`.
- [x] Tests: published/draft/private/password cases, preview signature, redirects, 404.

## Acceptance criteria

- Changing a slug or the structure never breaks an old URL (it redirects).
- A storefront can render any CMS URL using only the resolve endpoint.

## Progress log

- 2026-11-01 Decision (user): JSON API always + optional Blade routes, off by default.
- 2026-11-01 Phase 1: `Permalinks\PermalinkSettings` (Setting keys `cms_permalink_structure`, `cms_category_base`,
  `cms_tag_base`, `cms_permalink_history`, `cms_front_page_id`, `cms_posts_page_id`; `save()` pushes the old
  structure to the history), `PermalinkStructure` (tags, validation, named-group regex, build), `PermalinkGenerator`
  (posts per structure incl. nested primary `%category%` and `%author%`, hierarchical pages, front page → `/`,
  `/{base}/{nested}` categories, `/{base}/{slug}` tags, `/{type}/{slug}` for other types, plain `/?p=ID`, absolute
  URLs from `shopper.cms.frontend_url` or APP_URL), `PermalinkResolver` (order: reserved paths → category/tag bases →
  pages (so a page wins over a post with the same slug, like WordPress) → other types → current structure → old
  structures; content found under a non-canonical path, e.g. a wrong date or `?p=ID`, returns a 301 to the
  canonical path), `ReservedPaths` (admin prefix, API prefix, preview path, storage, assets, sitemap/robots,
  plus `shopper.cms.permalinks.reserved`). **Deviation:** no `cms_trailing_slash` setting; like WordPress the
  trailing slash follows the structure. The resolver ignores status/visibility on purpose: the API decides
  (phase 4). 20 tests, Pint and PHPStan clean.
- 2026-11-01 Phase 2: `Livewire\Pages\Settings\Permalinks` at `/cpanel/setting/permalinks` (admin settings layout,
  listed on the Settings screen through the addon's `PermalinkSetting` item): WordPress's "Common settings" radio list
  with an example URL under each option, custom structure (validated with `PermalinkStructure::errors()`, live
  example), category/tag bases (slugged, may not be reserved, must differ), and "Your homepage displays" (front page /
  posts page selectors) on the same screen. Permission `manage_permalinks` (migration). **Deviation:** the tag
  list is shown as help text instead of clickable tag buttons. Browser checked, console clean. 6 tests.
- 2026-11-01 Phase 3: `cms_redirects` + `Models\Redirect` (`from_path` normalized: leading slash, no trailing
  slash, lowercase, no query; targets keep their exact form; absolute `https://` targets allowed),
  `SaveRedirectAction` (chains collapse both ways, loops and self-redirects refused, 410 = gone),
  `Permalinks\PermalinkChangeRecorder` used by `SavePostAction` (slug, parent, date) and `SyncPostTermsAction`
  (primary category in `%category%` URLs): it snapshots the paths of *published* content (and all descendant pages)
  before a change and stores 301s afterwards; a path that becomes live content again loses its redirect. Structure
  changes need no rows (resolver history, phase 1). The resolver checks stored redirects last and counts hits;
  410 resolves to type `gone`. Admin screen Settings → Redirects (`/cpanel/setting/redirects`, `manage_permalinks`)
  with create/edit/delete, source filter, hit counts. Browser: a slug change in the editor created the automatic
  redirect; console clean. 12 tests; CMS suite 209 pass, Pint and PHPStan clean.
- 2026-11-01 Phase 4: JSON API under `shopper.cms.api.prefix` (default `api`, middleware `api` + `throttle:120,1`):
  `GET /api/cms/resolve?path=` (post/page → `PostResource`; category/tag → `TermResource` + paginated published posts;
  redirect → HTTP 301 with `{type: redirect, redirect, status}` and **no** `Location` header so clients do not follow
  it blindly; 410 `gone`; 404 `not_found`), `GET /api/cms/posts` (type/category/tag/search filters, paginated),
  `GET /api/cms/preview/{post}` (signed, `GeneratePreviewUrlAction::api()`; the plan 03 preview signature is for the
  HTML preview, so the API has its own signed route). Only published, non-trashed content is public; private,
  drafts, pending and scheduled content is 404 (`Support\ContentVisibility`); password-protected content returns
  `protected: true` and no content/excerpt unless `X-Post-Password` matches. `Support\CmsCache` caches successful
  responses per path (`shopper.cms.api.cache_ttl`, default 3600s) and flushes everything through a version key on
  any post/term/redirect change, term sync or permalink settings change; redirects are not cached so hits keep
  counting. Optional public pages (`shopper.cms.routes.enabled` / `SHOPPER_CMS_ROUTES`, off by default):
  `Route::fallback()` → `PublicContentController` with publishable views `shopper-cms-public::{layout,post,page,
  archive,password}` (`--tag=shopper-cms-views`); password form posts to a dedicated `/cms-unlock/{post}` (a catch-all
  POST route would swallow the app's own forms). SEO fields in responses are `null` until plan 08. Checked against
  the dev server with curl. 16 tests; full suite 1273 pass, Pint and PHPStan clean.
