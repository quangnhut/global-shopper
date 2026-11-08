# 11: Automatic page translation for customers (Google Translate, VI / EN / ZH)

Status: Done (2026-11-09)
Depends on: 09 (storefront header and layout). Must be checked against 10 (cart and checkout Livewire pages).

## Goal

Add a language picker to the storefront header. It lets a customer read the whole site in one of three languages:

| Language | Google code | Icon |
|----------|-------------|------|
| Tiếng Việt (default, the page's real language) | `vi` | Vietnam flag |
| English | `en` | UK flag (see Open decisions) |
| 中文 (Simplified Chinese) | `zh-CN` | China flag |

- Translation is done in the browser by Google's website translator script:
  `https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit`.
  It rewrites the visible text on the page; nothing is translated or stored on our server.
- Each option shows a **flag icon in front of the language name**, in the language's own script
  (Tiếng Việt, English, 中文), so customers recognise their language whatever language the page is in.
- The choice persists while browsing: following links, reloading and coming back keep the language,
  through Google's `googtrans` cookie.

Out of scope:

- Real, human-written translations of products and CMS content (a multilingual catalogue would be a separate plan).
- SEO in other languages: no `hreflang`, no `/en/...` URLs. Search engines keep seeing Vietnamese (machine
  translation is not meant to be indexed).
- The admin panel (customers only).
- The JSON API / headless storefronts. They can add the same script themselves; the API only reports whether
  the feature is enabled (Phase 1).

## How the Google script works (facts the code depends on)

1. Loading `element.js?cb=googleTranslateElementInit` calls `window.googleTranslateElementInit()`. In it we create
   `new google.translate.TranslateElement({ pageLanguage: 'vi', includedLanguages: 'en,zh-CN', autoDisplay: false }, 'google_translate_element')`.
   This renders Google's own `<select class="goog-te-combo">` into a hidden container.
2. **Switching language:** set that select's value (`en`, `zh-CN`) and dispatch a `change` event. Google then translates
   the page in place and writes the cookie `googtrans=/vi/en`. On the next page load the script reads the cookie and
   translates automatically.
3. **Back to Vietnamese:** Google's "show original" is unreliable through the hidden select. Instead, delete the
   `googtrans` cookie on both the host and the parent domain (`.example.com`), then reload the page.
4. **Google's UI must be hidden.** Otherwise it adds:
   - a top banner iframe (`.goog-te-banner-frame`, `body { top: 40px }`);
   - hover tooltips (`#goog-gt-tt`, `.goog-tooltip`);
   - text highlighting (`.goog-text-highlight`);
   - the gadget (`.goog-te-gadget`).

   Our CSS hides all four and forces `body { top: 0 !important }`.
5. Google wraps translated text in `<font>` tags and keeps translating new DOM nodes through a MutationObserver.
   Content added later by Livewire (cart updates, search results) is therefore translated too, but see Risks.
6. Elements with `translate="no"` or `class="notranslate"` are left untouched.
7. The translator is a legacy Google product: still served and working, but no longer offered to new sites
   and without an SLA. The picker must fail quietly if the script does not load (Phase 2).

## Design

### Picker UI (`partials/language-picker.blade.php`)

- **Not a native `<select>`.** An `<option>` cannot contain an image, so flags would not show. It is a small Alpine
  listbox that looks like a select, in the same style as the account menu:
  - the button shows the current flag, the short code (`VI`, `EN`, `中文`) and a chevron;
  - the dropdown lists three rows, each with its flag and full name, and a check mark on the current one.
- **Placement:**
  - desktop: in the red header bar, before the hotline;
  - mobile: flag-only button next to the cart; the full list is also inside the mobile drawer.
- **Flags are inline SVG** (`<x-shopper-storefront::flag code="vn|gb|cn" />`), about 20×14 with rounded corners
  and a thin ring so they read on the red header. Emoji flags are not used: Windows shows them as two letters.
- **Accessibility:**
  - button: `aria-haspopup="listbox"`, `aria-expanded`, and the label "Ngôn ngữ: Tiếng Việt";
  - list: `role="listbox"`, `role="option"` with `aria-selected`;
  - keys: arrow up/down, Enter, Escape; focus returns to the button.
  - The picker carries `translate="no"` so Google never translates the language names.

### Script loading (`partials/translate-scripts.blade.php`, inline like `cart-scripts`)

- **Lazy loading:** keep Lighthouse scores and avoid sending text to Google when nobody asked.
  - If the `googtrans` cookie selects a non-Vietnamese language, `element.js` is injected on page load.
  - Otherwise it is injected only when the customer picks English or Chinese.
- Alpine store `sfTranslate`: `current` (from the cookie), `ready`, `failed`, `choose(code)`.
  - **Picking `en` / `zh-CN`:** load the script if needed, wait for `.goog-te-combo` (poll up to ~5 s), then set it
    and dispatch `change`. While waiting, show a small spinner on the button.
  - **Picking `vi`:** clear the cookies (host and `.<domain>`, `path=/`) and run `location.reload()`.
  - **On failure** (`onerror`, or no combo after the timeout): set `failed`, revert `current` to `vi`, and show a short
    message "Không thể dịch trang lúc này".
- `<div id="google_translate_element" class="hidden" translate="no">` sits in the layout, outside `<main>`.
- CSS for hiding Google's UI goes in `packages/storefront/resources/css` (built into `dist/storefront.css`).

### What must not be translated (`translate="no"`)

Machine translation garbles these, or changes values the customer has to copy:

- prices and totals (`ProductPrice` output, cart summary);
- quantities, order numbers, phone numbers and the hotline, SKUs;
- the store name and logo `alt`;
- the bank account block and VietQR transfer note on the thank-you page;
- the payment method slugs and any hidden inputs.

Form inputs and their values are never translated by Google, so addresses and notes the customer types stay as typed.

### Configuration and admin

- **Config** `shopper.storefront.translate`:
  - `enabled`: `env('SHOPPER_STOREFRONT_TRANSLATE', true)`;
  - `page_language`: `vi`;
  - `languages`: `['vi' => ['label' => 'Tiếng Việt', 'flag' => 'vn'], 'en' => [...], 'zh-CN' => [...]]`.

  Languages stay in config, not in the admin, so codes always match Google's.
- **Admin switch:** Storefront → Header & footer gets a "Show language picker (Google Translate)" toggle,
  key `storefront_translate_enabled`, default on. The picker shows only when both the config and the setting are on.
- **Headless:** `GET /api/storefront/home` header data gets `translate: { enabled, languages }`.

### Privacy

When a customer turns translation on, the page text is sent to Google.
- Add a sentence to the privacy policy seed text (`Legal` privacy page).
- Add a note in CLAUDE.md.
- The script never loads unless the customer chose a language or already has the cookie.

## Risks and how we handle them

| Risk | Handling |
|------|----------|
| Livewire morphing fights Google's `<font>` wrappers: duplicated or stale text after cart updates, or JS errors in the morph | Test cart, mini cart, checkout and header search while translated (Phase 3). Fallbacks: put `wire:key` on the changing parts, or mark small dynamic number parts `translate="no"`. If the checkout cannot be made reliable, mark the checkout form area `translate="no"` (labels stay Vietnamese) and record it. |
| Alpine `x-text` bindings get replaced by translated text and then overwritten in Vietnamese | They are re-translated by Google's observer; check the cart add button and notices. |
| Script blocked (ad blockers, China firewall, `translate.google.com` unreachable) | `failed` state, picker reverts to VI, short message, no console error loop. |
| Layout shift from Google's `body { top }` | CSS override (`top: 0 !important`); check with the banner frame present. |
| Google stops serving `element.js` | Picker hides itself on load failure; config switch turns the feature off without a deploy of code changes. |
| Cookie on the wrong domain, so "back to Vietnamese" doesn't stick | Delete on host and `.domain`, test on `localhost` and on a dotted host. |

## Phases

### Phase 1: Config, setting, picker markup

- [x] `translate` block in `packages/storefront/config/storefront.php` (+ `SHOPPER_STOREFRONT_TRANSLATE` in `workbench/.env.example`, `.env.production.example`).
- [x] `storefront_translate_enabled` default in `StorefrontSettings`, toggle in `HeaderFooterSettings`, translations (vi, en) in `admin.php`.
- [x] `HeaderData`: `translateEnabled` and `languages` (built in `BuildLayoutAction`), included in the API array.
- [x] `components/flag.blade.php` with inline SVG for `vn`, `gb`, `cn`.
- [x] `partials/language-picker.blade.php` (desktop, mobile, and the drawer variant), included in `partials/header.blade.php`.
- [x] Translation strings in `storefront.php` (vi, en): `header.language`, `header.translate_failed`.
- [x] Tests, `tests/Storefront/Feature/LanguagePickerTest.php`:
  - the header shows three options with flags and native names, with `translate="no"` on the picker;
  - the picker is hidden when the setting is off, and hidden when the config is off;
  - `element.js` is **not** in the page HTML as a `<script src>` (it is lazily loaded);
  - the API header has `translate.enabled` and the languages;
  - the admin toggle saves.

### Phase 2: Loader script and Google UI hiding

- [x] `partials/translate-scripts.blade.php`:
  - Alpine store `sfTranslate`;
  - cookie read/write/delete;
  - lazy injection of `element.js`;
  - `googleTranslateElementInit`;
  - combo polling with timeout;
  - failure handling.

  Included from `layout.blade.php` only when enabled.
- [x] Hidden `#google_translate_element` in the layout.
- [x] CSS rules hiding the banner, tooltip, highlight and gadget; `body { top: 0 !important }`. Then
  `npm run build:storefront` and `vendor:publish --tag=shopper-storefront-assets --force`.
- [x] Browser check (chrome devtools), with no console errors at each step:
  1. VI → EN: the header, home sections, footer, a category page and a product page are translated.
  2. Reload: still EN.
  3. Follow a link: still EN.
  4. EN → 中文.
  5. 中文 → VI restores the original text.
  6. Blocking `translate.google.com` shows the failure message.

### Phase 3: Livewire/Alpine compatibility and "do not translate" markings

- [x] Add `translate="no"` to prices, totals, quantities, order numbers, phone/hotline, SKU, store name, bank block, VietQR note.
- [x] Browser check while in EN and 中文:
  1. Add to cart from a card.
  2. Mini cart count updates.
  3. Change quantity and remove on `/cart`.
  4. Change quantity on `/checkout`, then show validation errors.
  5. Place an order (office payment).
  6. The thank-you page shows the order number and amounts untranslated.
  7. Header search suggestions.

  Fix any morph problems (see Risks) and record each fallback used.
- [x] Mobile (375 px): the picker fits next to the cart, the drawer list works, no horizontal scroll.
- [x] Home page with the cookie absent: Google's script is not loaded (checked in the browser; no full Lighthouse run).
- [x] Privacy text in the demo data (`workbench/database/seeders/HomepageSeeder.php` fills the empty `privacy` legal page; the core seeder is untouched).
- [x] CLAUDE.md:
  - the feature;
  - the config switch and admin toggle;
  - that text goes to Google;
  - that `element.js` is a legacy Google product.
- [x] `.claude/plans/README.md`: mark row 11.
- [x] Storefront Pest suite, Pint, PHPStan.

## Decisions (confirmed by the user 2026-11-09)

| Decision | Options | Recommendation |
|----------|---------|----------------|
| Flag for English | UK, US, or a neutral globe icon | **UK flag** (decided) |
| Chinese variant | Simplified (`zh-CN`), Traditional (`zh-TW`), or both | **Simplified only** (decided) |
| Picker on the admin side or checkout area | Translate everything, or keep checkout form labels in Vietnamese | **Translate everything** (decided); fallback only if testing found problems. It did not, so no fallback is used. |

## Progress log

- 2026-11-09: Plan written.
- 2026-11-09: Implemented all three phases.
  - Config `shopper.storefront.translate` (`SHOPPER_STOREFRONT_TRANSLATE`), setting `storefront_translate_enabled`
    with a toggle on the Header tab, `HeaderData::$languages` / `translates()`, and the API block `header.translate`.
  - Views: `components/flag.blade.php` (VN, GB, CN), `partials/language-picker.blade.php` (header listbox + drawer
    radio group) and `partials/translate-scripts.blade.php` (Alpine store `sfTranslate`). CSS hides Google's banner,
    tooltip and highlight.
  - Prices, order numbers, the bank account, phone numbers, SKUs, the store name and the "Hotline" label (Google turned
    it into "Group") are wrapped in `translate="no"`.
  - Deviation: `StorefrontCache::SCHEMA` was added to the cache key, so header data cached before a deploy (without
    `languages`) is never read back.
  - Browser checks (chrome devtools), with no console errors:
    - VI → EN, reload and following a link stay EN, then EN → 中文 and 中文 → VI (cookie removed, script not loaded).
    - In 中文: add to cart, change cart quantities, change checkout quantities, validation messages, and an office order
      (ORD-20261108-000084) all work. Livewire updates are translated again by Google.
    - The checkout form fallback was not needed.
    - With the script blocked, the toast appears and the page stays in VI.
    - At 375 px there is no horizontal scroll; flag-only button; the list is in the drawer.
  - Tests: `tests/Storefront/Feature/LanguagePickerTest.php` (6) and a toggle test in `HeaderFooterSettingsTest`.
    Storefront suite 160 passed; Pint and PHPStan (packages/storefront) clean.
  - Bug found and fixed during the browser check: Blade eats the newline after `@js(...)`, so the next `const` line
    joined it. The fix was a semicolon after `@js($translateConfig)`.

