# CMS + SEO roadmap (WordPress-style)

Goal: add WordPress-like content management (posts, pages, categories, tags, statuses, trash,
TinyMCE, revisions, permalinks) and an SEO module (global settings, per-page meta, schema JSON-LD,
GTM, noindex rules, sitemaps, robots.txt) to Shopper.

Run a plan with the `implement-plan` skill: "implement plan 01", "continue plan 03 phase 2".

## Plans and order

| # | Plan | Depends on | Status |
|---|------|------------|--------|
| 01 | [CMS package foundation and data model](01-cms-foundation.md) | none | Done |
| 02 | [Post categories and tags](02-post-taxonomies.md) | 01 | Done |
| 03 | [Posts and pages admin (statuses, trash, bulk actions)](03-posts-pages-admin.md) | 01, 02 | Done |
| 04 | [TinyMCE editor](04-tinymce-editor.md) | 03 | Done |
| 05 | [Revisions and autosave](05-revisions.md) | 03 (04 for autosave) | Done |
| 06 | [Permalinks, redirects and the content API](06-permalinks.md) | 03 | Done |
| 07 | [SEO module: settings and per-entry meta](07-seo-module.md) | 01 (06 for canonical URLs) | Done |
| 08 | [SEO output: head tags, JSON-LD, sitemaps, robots.txt, GTM](08-seo-output.md) | 06, 07 | Done |

02 and 07 can run in parallel with other work once 01 is done.

## Architecture decisions (apply to every plan)

1. **Two new monorepo packages**, following the existing layout (see the `shopper-package` skill):
   - `packages/cms`: namespace `Shopper\Cms`, posts, pages, taxonomies, revisions, permalinks.
   - `packages/seo`: namespace `Shopper\Seo`, SEO settings, `seo_meta`, output, sitemaps.
     It must also work for existing shop entities (products, categories, brands, collections),
     so it depends only on `shopper/core`, not on `shopper/cms`. CMS registers its models with SEO.
2. **Admin UI through the addon system** (`Shopper\Contracts\ShopperAddon`, `Shopper\Addon\BaseAddon`):
   each package registers its routes, sidebar, Livewire components, views, setting items,
   permissions and scripts. No edits to `packages/admin` except generic hooks if one is missing.
3. **Headless first**. Shopper is a headless admin with no storefront, so:
   - every public-facing feature has a JSON API (resolve a path → content + SEO data);
   - Blade components and optional routes are provided for apps that render server-side.
4. **Reuse what exists**: `Setting` model for site-wide options (keys prefixed `cms_` / `seo_`),
   the Uploads library (`Shopper\Core\Models\Upload`, `StoreUploadAction`) for featured images,
   OG images, logos and editor images, the existing store settings (name, logo, address, socials)
   for Organization schema, and the existing `seo_title` / `seo_description` columns on products,
   categories, collections and brands.
5. **Trash = soft deletes**. A trashed post keeps its real status (draft, published…), so restoring
   it returns it to that status, like WordPress's `_wp_trash_meta_status`. Trash empties after
   `cms.trash_days` (default 30, WordPress's `EMPTY_TRASH_DAYS`).
6. **Untrusted HTML**: everything from TinyMCE and every admin-entered snippet is sanitized on save
   (HTMLPurifier). Raw script injection (custom head/body code) is a separate, explicitly
   permissioned feature.
7. Conventions from `CLAUDE.md` apply: strict types, final action classes, `casts()`, contracts,
   enums with Filament `HasLabel/HasColor/HasIcon`, Pest tests, translations (en + fr).

## Open decisions (ask the user before the plan that needs them)

| Decision | Options | Recommendation | Needed by |
|----------|---------|----------------|-----------|
| TinyMCE licence | TinyMCE 7+ self-hosted under GPLv2+ (`license_key: 'gpl'`), TinyMCE 6.8 (MIT, no longer updated), or a commercial key | **Decided 2026-11-01 by the user:** latest TinyMCE, self-hosted, `license_key: 'gpl'`, no paid key (the code is not redistributed under MIT). | 04 |
| Custom post types | Only `post` and `page`, or a registry for custom types | Registry from day one (cheap), but ship only post + page in the UI | 01 |
| Comments | WordPress has comments | Out of scope for now | n/a |
| Multilingual (hreflang) | none / later | Leave hooks in the SEO data object, implement later | 08 |
| Frontend rendering | Headless API only, or also Blade routes | **Decided 2026-11-01 by the user:** JSON API always, plus optional Blade routes switched off by default (config). | 06, 08 |

## WordPress parity map

| WordPress | Here |
|-----------|------|
| `wp_posts` (post_type post/page, post_status, post_parent, menu_order, post_password) | `cms_posts` (`type`, `status`, `parent_id`, `menu_order`, `password`), plan 01 |
| Categories / tags (`wp_terms`, `wp_term_taxonomy`) | `cms_post_categories`, `cms_post_tags`, plan 02 |
| Publish box (status, visibility, schedule), Trash, bulk actions, Quick Edit | plan 03 |
| Classic editor (TinyMCE), Add Media | plan 04 |
| Revisions, autosave, compare screen | plan 05 |
| Settings → Permalinks, old-slug redirects | plan 06 |
| Settings → General (site title, tagline, site icon), Reading ("discourage search engines") | plan 07 |
| Yoast/Rank Math: SEO title/description templates, per-post meta box, schema, sitemaps, noindex | plans 07, 08 |
