# 09: Storefront home page (header, configurable sections, footer)

Status: Done (agent reviews in Phase 5 not run, see log)
Depends on: 01–03 (CMS posts/pages), 08 (SEO head output). Uses VND as the only currency (2026-11-05).

## Goal

Give the shop a customer-facing home page whose content the admin controls:

- **Header**: logo, product search, cart, account icon (sign in / sign up), hotline, and every enabled
  product category as a menu.
- **Body**: any number of sections, ordered by the admin, top to bottom. Product sections (sale/promotion,
  hot deals, new products, …) and post sections, each with a title and a hand-picked list of items.
- **Footer**: company information (name, address, email, hotline, working hours, business registration),
  links to company pages (about, contact, policies), the DMCA and Bộ Công Thương badges, shipping
  partners and payment methods.

Admin gets a **Homepage** menu where all of this is set up.

## What already exists (reuse, do not rebuild)

| Need | Existing piece |
|------|----------------|
| Logo, store name, legal name, email, phone, address | `Setting` keys `logo`, `name`, `legal_name`, `email`, `phone_number`, `street_address`, `city`, `postal_code`, `country_id` (Settings → General) |
| Social links | `Setting` keys `facebook_link`, `instagram_link`, `twitter_link` |
| Category tree | `Shopper\Core\Models\Category` (`parent_id`, `position`, `is_enabled`, `scopeEnabled`) |
| Products, prices, stock, images | `Product` (`scopePublish`: visible + published), `HasPrices` (VND, `compare_amount` = old price), `HasStock`, Spatie media |
| Posts and company pages | CMS `Post` (`type` post/page, `isPublished()`, featured image, permalinks from plan 06) |
| Policy pages | `Shopper\Core\Models\Legal` (refund, privacy, terms, shipping) |
| Shipping partners / payment logos | `Carrier` (`logo`, `is_enabled`), `PaymentMethod` (`logo`, `is_enabled`) |
| Cart | `packages/cart` `CartSessionManager` (count for the header badge) |
| Image uploads | `Upload` model + `StoreUploadAction` (badges, banners) |
| SEO | `<x-shopper-seo::head />`, `/api/seo` (plan 08), home page = `WebPage` node |
| Money | `shopper_money_format()` → `49.975.000 ₫` under the `vi` locale |

Missing today: **no storefront at all**, **no customer sign-in / sign-up** (the `InteractsWithStoreAuthentication`
trait is admin 2FA), no hotline / working hours / registration settings, no section model.

## Architecture

1. **New package `packages/storefront`**, namespace `Shopper\Storefront` (follow the `shopper-package` skill),
   depends on `shopper/core`, `shopper/cart`, `shopper/cms`, `shopper/seo`.
   Admin screens are registered through a `StorefrontAddon` (same pattern as `CmsAddon`), so `packages/admin`
   is not edited.
2. **Headless first, same as the CMS decision of 2026-11-01**: one JSON endpoint returns header, sections and
   footer; the package also ships a Blade + Livewire + Alpine theme served by optional routes
   (`SHOPPER_STOREFRONT_ROUTES`, off by default; on in the workbench).
3. **Settings** live in the `Setting` model with keys prefixed `storefront_`. **Sections** get their own tables
   because they are ordered lists with ordered, polymorphic items.
4. Storefront text in `vi` (default) and `en`. Admin labels in `en`, `vi` (the repo's admin locales).
5. One cached payload for the home page (`storefront.home.{locale}`), busted by model events on sections,
   section items, products, prices, stock, categories, posts, carriers, payment methods and `storefront_*` settings.

## Data model

`storefront_home_sections` (via `shopper_table()`):

| Column | Type | Notes |
|--------|------|-------|
| `id`, timestamps | | |
| `type` | string, enum `HomeSectionType` | `products`, `posts` (extensible: `banner`, `categories` later) |
| `title` | string | e.g. "Khuyến mãi", "Deal hot", "Sản phẩm mới" |
| `subtitle` | string, nullable | |
| `style` | string, enum `HomeSectionStyle`, nullable | `sale`, `hot_deal`, `new`, `default`: badge colour, countdown, icon |
| `layout` | string, enum | `grid`, `carousel` |
| `columns` | tinyint, default 5 | desktop items per row (mobile is always 2) |
| `limit` | smallint, nullable | max items shown; null = all picked |
| `view_all_url` | string, nullable | "Xem tất cả" link |
| `position` | unsigned int, indexed | **priority: lower = higher on the page** |
| `is_enabled` | boolean | |
| `starts_at`, `ends_at` | timestamp, nullable | scheduled sections (flash sale); hot deals show a countdown to `ends_at` |

`storefront_home_section_items`:

| Column | Type | Notes |
|--------|------|-------|
| `id` | | |
| `home_section_id` | FK, cascade delete | |
| `itemable_type`, `itemable_id` | morph (`product`, `post` aliases from the morph map) | |
| `position` | unsigned int | order inside the section |
| unique | (`home_section_id`, `itemable_type`, `itemable_id`) | |

Rendering rules: a section is shown when enabled, inside its schedule, and has ≥ 1 visible item.
Products must be published, visible, and have a VND price; posts must be published, public and not password-protected.
Items that stop qualifying are skipped silently (never break the page); the admin list flags them.

## Settings (`storefront_*` keys)

| Group | Keys |
|-------|------|
| Header | `storefront_hotline`, `storefront_header_logo` (upload id, falls back to `logo`), `storefront_announcement` (optional top bar text), `storefront_menu_category_ids` (empty = all enabled root categories, by `position`) |
| Company | `storefront_working_hours` (text, e.g. "8:00 – 21:00, Thứ 2 – Chủ nhật"), `storefront_business_registration` (MST / số GCN ĐKKD), `storefront_registration_issued` (ngày cấp, nơi cấp). Name, legal name, address, email, phone are read from Settings → General. |
| Footer links | `storefront_footer_columns`: repeater of `{title, links: [{type: cms_page|legal|url, id|url, label?}]}` (e.g. "Về chúng tôi": About, Contact; "Chính sách": refund, privacy, terms, shipping) |
| Badges | `storefront_dmca_url` + `storefront_dmca_badge` (upload), `storefront_bct_url` (online.gov.vn link) + `storefront_bct_badge` (upload, "Đã thông báo" / "Đã đăng ký" image) |
| Partners | `storefront_carrier_ids`, `storefront_payment_method_ids` (empty = all enabled with a logo), `storefront_extra_partners` (repeater `{name, logo upload, url}` for partners not modelled, e.g. GHN, GHTK, VNPay, MoMo) |

Vietnamese e-commerce sites must show the company name, address, MST/registration number, phone and email
(Nghị định 52/2013, amended by 85/2021), and link the Bộ Công Thương notification. The footer shows a warning in
the admin preview when any of these is empty.

## Admin: Homepage menu

Sidebar item **Homepage** (icon `untitledui-home-line`), permission `manage_homepage` (seeded, given to the admin role):

1. **Sections** (`/homepage/sections`): Filament table, drag-to-reorder (`reorderable('position')`), columns
   type, title, style, items count, schedule state (live / scheduled / expired), enabled toggle.
   Actions: create, edit, duplicate, delete, enable/disable.
2. **Section editor** (slide-over or page):
   - Title, subtitle, type (locked after creation), style, layout, columns, limit, view-all URL, schedule, enabled.
   - **Items picker**: searchable multi-select (by name / SKU for products, title for posts) with thumbnails,
     price and stock shown for products; selected items in a sortable list (drag to order, remove).
     Products: only publishable ones selectable; posts: only `type=post` (pages are for the footer).
3. **Header & footer** settings page (`/homepage/settings`): the keys above, uploads through the Uploads library,
   live mini-preview of the footer.
4. "View home page" button opens the storefront (`storefront.url` config → APP_URL).

## Storefront UI

Layout (Tailwind v4, Alpine, Livewire for search and cart), mobile first, max width 1280px.

**Header** (sticky, shrinks on scroll):
- Optional announcement bar.
- Row 1: logo → `/`; search box (Livewire, debounced suggestions: top 6 products with image + price, "see all"
  → `/search?q=`); hotline (`tel:` link, phone icon); account icon (guest: dropdown "Đăng nhập" / "Đăng ký";
  signed in: name, "Tài khoản", "Đơn hàng", "Đăng xuất"); cart icon with item count badge.
- Row 2: "Danh mục sản phẩm" mega menu: root categories with their children (2 levels), category images if set.
- Mobile: hamburger opens a drawer with the category tree (accordion), hotline and account links; search moves below the logo.

**Body**: sections in `position` order.
- Product card: image (lazy, fixed aspect ratio), name (2 lines), price in VND, old price struck through +
  "-23%" badge when `compare_amount > amount`, "Hết hàng" when out of stock, style badge (Sale / Hot / Mới).
- Hot deal sections with `ends_at`: countdown timer (Alpine), hidden once expired (server-side check too).
- Post card: featured image, title, excerpt, date, link to the post permalink.
- Carousel layout: CSS scroll-snap + arrow buttons (no carousel library); grid layout: responsive columns.

**Footer**: 4 columns on desktop, stacked accordions on mobile:
company info (legal name, address, MST, phone/hotline, email, working hours) · footer link columns ·
partners (shipping and payment logos, greyscale until hover) · badges (DMCA, Bộ Công Thương) + socials.
Bottom bar: © year legal name.

**Auth pages** (Phase 4): `/dang-nhap`, `/dang-ky`, `/quen-mat-khau`, logout, using the configured user model and
the customer role `config('shopper.admin.roles.user')`; rate limited; customers cannot reach the admin panel.

**Search page**: `/search?q=`: product grid, paginated, same card. **Cart**: header badge + mini cart dropdown
(lines, total, "Xem giỏ hàng"); the full cart and checkout pages are out of scope (separate plan).

Accessibility/performance: semantic landmarks, keyboard-usable menus, alt text, `loading="lazy"` except the first row,
responsive image conversions, no layout shift (fixed aspect ratios), home page TTFB served from cache.

## API

`GET {api}/storefront/home` (cached, `Cache-Control: public, max-age=60`):

```json
{
  "header": { "logo": "...", "hotline": "...", "announcement": null, "categories": [{ "id": 1, "name": "...", "url": "...", "children": [] }] },
  "sections": [
    { "id": 3, "type": "products", "title": "Deal hot", "style": "hot_deal", "layout": "carousel", "columns": 5,
      "ends_at": "2026-11-30T23:59:59+07:00", "view_all_url": null,
      "items": [{ "id": 6, "name": "...", "url": "...", "image": "...", "price": { "amount": 49975000, "formatted": "49.975.000 ₫" },
                  "compare_price": { "amount": 53725000, "formatted": "53.725.000 ₫" }, "discount_percent": 7, "in_stock": true }] }
  ],
  "footer": { "company": {}, "columns": [], "badges": {}, "partners": { "shipping": [], "payment": [] }, "socials": {} }
}
```

Plus `GET {api}/storefront/search?q=` (suggestions) for headless storefronts. Same DTOs feed the Blade theme.

## Phases

### Phase 1: package, data model, settings
- [x] Scaffold `packages/storefront` (composer, service provider, config, addon, autoload, PHPStan, Pest, split workflow).
- [x] Migrations, `HomeSection` + `HomeSectionItem` models (contracts, `casts()`, morph items), enums
      `HomeSectionType`, `HomeSectionStyle`, `HomeSectionLayout` (Filament label/colour/icon).
- [x] `StorefrontSettings` reader with defaults and fallbacks to General settings.
- [x] Permission `manage_homepage`; translations `en`, `vi`.
- [x] Tests: models, visibility rules (schedule, unpublished/no-price products skipped, private posts skipped), settings fallbacks.

### Phase 2: admin Homepage menu
- [x] Sidebar item + routes via the addon.
- [x] Sections table with drag reorder, toggles, duplicate.
- [x] Section editor with item picker (search, thumbnails, sortable selection).
- [x] Header & footer settings page (uploads, repeaters, compliance warnings).
- [x] Tests (Livewire): create/edit/reorder/delete sections, pick and order items, permission checks, settings save.

### Phase 3: home data, cache and API
- [x] `BuildHomePageAction` → DTOs (`HeaderData`, `SectionData`, `ProductCardData`, `PostCardData`, `FooterData`),
      eager loading (no N+1: assert query count), cache + busting observers.
- [x] `GET {api}/storefront/home`, `GET {api}/storefront/search`.
- [x] Tests: payload shape, ordering by `position`, cache busting on product/price/section/setting change, query count.

### Phase 4: storefront theme (Blade) and customer auth
- [x] Optional routes (`/`, `/search`, auth pages), layout with `<x-shopper-seo::head />`, header, footer, section components.
- [x] Customer sign-in / sign-up / password reset / logout (role `user`, throttled, redirect admins to the panel).
- [x] Livewire search suggestions, mini cart (from `CartSessionManager`), mega menu, mobile drawer, countdown, carousel.
- [x] Storefront CSS/JS bundle in `scripts/build.js` → `packages/storefront/dist/` (committed), published with
      `vendor:publish --tag=shopper-storefront-assets`.
- [x] Tests: routes render, guest vs signed-in header, registration creates a customer without admin access,
      throttling, section HTML order, XSS escaping of titles and settings.

### Phase 5: demo data, docs, review
- [x] Workbench seeder: Sale / Deal hot / Sản phẩm mới / Tin tức sections, footer columns, partners, badges.
- [x] Enable the routes in the workbench; check desktop and mobile in the browser (Chrome DevTools), Lighthouse ≥ 90
      performance and accessibility on the home page.
- [x] `CLAUDE.md` (running + production notes), `README.md` plan table.
- [ ] `seo-auditor` on `/`, `code-reviewer` agent (not run: subagents were not requested; Lighthouse SEO and a manual review instead).

## Acceptance criteria

- Admin can create product and post sections, pick and order their items, reorder sections by drag and drop,
  schedule and disable them; the home page reflects changes immediately (cache busted).
- Header shows logo, search with suggestions, hotline, account menu (working sign-in/sign-up), cart count and all
  enabled categories; footer shows company details, page links, DMCA and Bộ Công Thương badges, shipping and payment partners.
- Prices show as VND; unpublished, out-of-schedule or price-less items never appear; no N+1 queries;
  the same content is available from `GET /api/storefront/home`.

## Open decisions (ask the user before the phase that needs them)

**Decided 2026-11-07 by the user:** Blade theme inside this repo (`packages/storefront`); basic sign-in,
sign-up and password reset in this plan, profile and order history later; hand-picked products only; cart and
checkout pages in a separate plan. Not answered, so the recommendations were applied: storefront at `/` in the
same app; footer columns mix CMS pages, legal pages and custom URLs.

| Decision | Options | Recommendation | Needed by |
|----------|---------|----------------|-----------|
| Where the storefront UI lives | Blade theme in `packages/storefront` (+ API), or a separate JS app (Next.js/Nuxt) using the API only | Blade/Livewire theme in the package (same TALL stack, SEO components already exist), API kept for a JS app later | 1 |
| Customer accounts | Build sign-in / sign-up in this plan, or only link to a future accounts plan | Build the basic flows here (the account icon is useless without them); profile/orders pages in a later plan | 4 |
| Automatic product sources | Hand-picked only, or also "newest N", "on sale (compare price set)", "from collection X" | Hand-picked now (what was asked); add a `source` column later. Existing Collections (manual/auto) could back sections | 1 |
| Cart and checkout pages | In this plan or a separate one | Separate plan; here only the header badge and mini cart | 4 |
| Storefront URL / domain | Same app at `/`, or a separate domain | Same app at `/` with the admin under `SHOPPER_PREFIX` | 4 |
| Footer pages | CMS pages (plan 03) for About/Contact, core `Legal` for policies | Both, mixed freely in the footer columns | 2 |

## Progress log

- 2026-11-06 Plan written.
- 2026-11-07 Phase 1: `packages/storefront` (`Shopper\Storefront`, provider, config `shopper.storefront`, root autoload,
  `replace`, split workflow, phpunit sources). Tables `storefront_home_sections` / `storefront_home_section_items`,
  models `HomeSection` (`live()`/`ordered()` scopes, `isLive()`, `isScheduled()`, `isExpired()`) and `HomeSectionItem`
  with contracts, enums `HomeSectionType` / `HomeSectionStyle` (badge, countdown) / `HomeSectionLayout` on core's
  `HasLabel`/`HasColor`/`HasIcon`, `manage_homepage` permission (group `storefront`, given to the admin role).
  `Settings\StorefrontSettings`: one `storefront_*` row per key, cached, store keys exposed as `store.*`, fallbacks
  (hotline → phone, header logo → logo, company → legal name → name), `missingLegalDetails()`; flushed when any
  storefront or store setting is saved. `Support\SectionItems` resolves the picked items of many sections with one
  products query and one posts query (rules: published + visible + price in the store currency on the product or a
  variant; posts: type `post`, published, no password). Posts have no morph alias, so items store the post class name.
  17 tests in `tests/Storefront`; Pint and PHPStan clean.
- 2026-11-07 Phase 2: `StorefrontAddon` (routes under `{prefix}/homepage`, sidebar group "Storefront" → Homepage →
  Sections / Header & footer, all gated by `manage_homepage`). Sections list (`Livewire\Pages\Sections\Index`):
  drag reorder on `position`, enable toggle, schedule status, "N shown / M picked" (picked items that are hidden on the
  storefront are counted, all rows resolved in one pass), duplicate (`DuplicateHomeSectionAction`: disabled copy at the
  bottom), delete, bulk enable/disable/delete. Editor (`Sections\Editor`, full page instead of a slide-over): simple
  repeater of searchable selects, drag to order, distinct values; options only offer showable items
  (`Support\ItemPicker`, HTML labels with thumbnail, SKU and VND price, every value escaped; already-picked items that
  became hidden are flagged). Type is locked after creation; `view_all_url` must be `/path` or `http(s)://`.
  `SaveHomeSectionAction` replaces the items in order; new sections go to the bottom. Header & footer page
  (`HeaderFooterSettings`): tabs Header / Company / Footer links / Badges & partners, uploads through `UploadPicker`,
  footer link URLs limited to `/path`, `http(s)://`, `mailto:`, `tel:`; banner listing the legally required details
  still missing. Added to the plan: a `group` (shipping/payment) on extra partners. `Support\ProductPrice` (own price,
  else cheapest variant, in the store currency) and `Support\ProductImage` (thumbnail, else first gallery image)
  for the picker and the cards in Phase 3. Browser check found that repeaters need `HasActions` on the page
  (`Repeater::fake()` had hidden it in tests); fixed and covered by tests that click "Add". 37 tests; Pint and
  PHPStan clean. A "Deal hot" section with one product was created in the dev database during the browser check.
- 2026-11-07 Phase 3: DTOs in `Data\` (`HomePageData`, `HeaderData`, `SectionData`, `ProductCardData`, `PostCardData`,
  `FooterData`, `LinkData`, all `Arrayable`). Split into `BuildLayoutAction` (header + footer, also used by other
  storefront pages) and `BuildHomeSectionsAction` instead of one `BuildHomePageAction`. Cards: price from
  `ProductPrice` (variant minimum when the product has no own price), `discount_percent`, `in_stock` (backorder,
  virtual and external products always in stock; stock batch-loaded for products and variants, 2 queries). Menu:
  chosen or all enabled root categories in `position` order with enabled children. Footer: page links only to
  published CMS pages, policies to `shopper.storefront.paths.legal` (`/policies/{slug}`, route in Phase 4), relative
  URLs made absolute; partners = chosen carriers/payment methods or every enabled one with a logo, then extra partners.
  `StorefrontCache`: versioned keys per locale (works on any cache store), TTL `shopper.storefront.cache_ttl` capped at
  the next scheduled start/end; flushed by model events (sections, items, product, variant, category, price,
  inventory history, post, legal, carrier, payment method, upload, media, storefront/store/currency settings) and
  explicitly after `SaveHomeSectionAction` (items replaced by query) and table reordering (`afterReordering`).
  API: `GET /api/storefront/home` (`Cache-Control: public, max-age=60`), `GET /api/storefront/search?q=&per_page=&page=`
  (`SearchProductsAction`: every word must match name or SKU, LIKE wildcards escaped, query ≤ 100 chars).
  Query count is constant in the number of sections (CMS `PermalinkGenerator` is not a singleton, so one instance is
  reused per build). 47 tests; Pint and PHPStan clean. Checked against the dev database: real payload correct; prices
  are formatted in the request locale (`en` → `₫49,975,000`), so Phase 4 adds a storefront locale (default `vi`).
- 2026-11-07 Phase 4: Blade theme (`routes/web.php`, loaded when `SHOPPER_STOREFRONT_ROUTES=true`; on in
  `workbench/.env(.example)` and `.env.production.example`): `/` (home), `/search`, `/policies/{slug}` (enabled
  `Legal` only, content passed through the CMS `HtmlSanitizer` because the admin rich editor saves it raw), `/login`,
  `/register`, `/forgot-password`, `/reset-password/{token}`, `POST /logout`; paths from `shopper.storefront.paths`.
  Locale: `shopper.storefront.locale` (default `vi`) via `SetStorefrontLocale` on web and API (`?locale=` for `vi|en`).
  Layout uses `<x-shopper-seo::head>` (search pages get `noindex`), header (announcement, logo, Livewire search with
  6 suggestions, hotline `tel:`, account menu, Livewire mini cart, desktop category bar with hover/focus dropdowns,
  mobile drawer with accordion and focus trap), sections (grid or scroll-snap carousel with arrows, hot deal gradient and
  countdown, badges), product and post cards, footer (company, link columns as mobile accordions, partners in
  greyscale, badges, socials). Customer auth: guard `shopper.auth.guard`; sign-up assigns `shopper.admin.roles.user`
  (no admin access, checked: customer gets the admin 403 page); sign-in rate-limited 5 tries per email+IP plus route
  throttles; the session cart is associated with the user. Password reset uses `StorefrontResetPassword` (link to the
  storefront reset page) through the broker callback, so the admin reset email is unchanged; the form answers the same
  for unknown emails. Mini cart reads the session cart without creating one. Not done (by decision): profile and order
  pages, cart/checkout pages (the plan's "Xem giỏ hàng" link is left out until those exist). CSS: Tailwind v4
  (`source(none)`, own sources only) in `packages/storefront/resources/css/storefront.css` → `dist/storefront.css`
  (39 KB, 7.5 KB gzip), `npm run build:storefront`, published by `vendor:publish --tag=shopper-storefront-assets`
  (added to the container entrypoint); Alpine comes with Livewire (focus/collapse plugins), no extra JS bundle.
  Browser check (desktop 1440 and phone 390): home, suggestions, search page, drawer, sign-up, sign-in with a wrong
  then right password, sign-out, customer blocked from the admin; no console errors. Fixed during the check: Livewire
  assigned the `q` mount argument straight to the typed property (renamed to `query`), cart badge overlap, double
  period in the copyright. 63 tests; Pint and PHPStan clean.
- 2026-11-07 Phase 5: `workbench/database/seeders/HomepageSeeder` (called by `DatabaseSeeder`, skipped when sections
  exist): Deal hot (carousel, ends in 3 days), Khuyến mãi (products with a compare price), Sản phẩm mới, Tin tức posts,
  hotline, announcement, working hours, placeholder tax ID marked "(demo)", footer columns (About Us, Contact, search;
  the four policies), Bộ Công Thương / DMCA links, GHN, GHTK, VNPay, MoMo. Run on the dev database after deleting the
  test section and test customer created during the browser checks. Lighthouse (mobile): accessibility 100 after
  darkening the struck-through prices (2.6:1 → gray-500) and naming the account button; best practices 100; SEO 100.
  Performance trace (4× CPU, Fast 4G): LCP 534 ms, CLS 0, TTFB 53 ms; the remaining hints (no text compression,
  short cache lifetimes) are server settings, covered by the reverse proxy note in `CLAUDE.md`. Hot deal text colours
  moved into the template (component CSS lost to utility classes). Docs: `CLAUDE.md` (package, local URL, CSS build,
  deploy step, production notes), plans README. Full suite: 1441 passed; Pint clean; PHPStan: only the existing
  `Shopper\Cms\Enums\RevisionKind` error. Known limits: product images depend on product media (the demo products
  have none); search uses SQL `LIKE` with escaped wildcards (SQLite has no default escape character, so a literal `%`
  only matches on MySQL/PostgreSQL); the admin 403 page shows the admin sidebar headings to customers (existing admin
  behaviour).
