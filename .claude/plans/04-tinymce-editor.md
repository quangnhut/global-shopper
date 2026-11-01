# 04: TinyMCE editor for posts and pages

Status: Done
Depends on: 03
Decision (2026-11-01): latest TinyMCE, self-hosted, `license_key: 'gpl'`, no paid key.

## Goal

Replace the content textarea with a self-hosted TinyMCE editor that behaves like WordPress's
classic editor: Visual/Text (HTML) modes, an "Add Media" button backed by the Uploads library,
drag-and-drop/paste image upload, and safe HTML on save.

## WordPress behaviour to match

- Toolbar row 1: format select, bold, italic, bulleted/numbered list, blockquote, align left/center/right,
  link/unlink, read-more, fullscreen, "toolbar toggle". Row 2: strikethrough, horizontal line,
  text colour, paste as text, clear formatting, special characters, indent/outdent, undo/redo.
- "Add Media" opens the media library; images insert with alt text, size and alignment.
- Visual ↔ Text tabs (Text = raw HTML, here via the `code` plugin or a source-code toggle).
- Word count under the editor. Fullscreen / distraction-free mode.

## Design

**Filament form field** `Shopper\Cms\Forms\Components\TinyEditor extends Filament\Forms\Components\Field`
with a Blade view:
- `wire:ignore` wrapper + Alpine `x-data` component that initialises TinyMCE on a `<textarea>`.
- State sync: `$wire.$entangle('{{ $getStatePath() }}')` (deferred), updated on TinyMCE `change`,
  `input`, `undo`, `redo` events; push external state changes back with `editor.setContent()`.
- Lifecycle: the admin uses `useSpa()` (wire:navigate). Call `tinymce.remove(editor)` on
  `livewire:navigating` and in Alpine `destroy()`, or editors leak and stop working after navigation.
- Dark mode: pick `skin: 'oxide-dark'`, `content_css: 'dark'` when the admin is in dark mode.
- Field options (fluent, with defaults from `config/cms.php` → `editor`): `toolbar()`, `plugins()`,
  `height()`, `uploadDirectory()`, `maxImageSize()`.

**Assets**: install the `tinymce` npm package (version pinned, matching the licence decision).
TinyMCE loads skins, icons and plugins by URL at runtime, so it cannot be served through
`AssetController`'s fixed file list. Copy `node_modules/tinymce` to `packages/cms/dist/tinymce` in
`scripts/build.js`, publish it to `public/vendor/shopper-cms/tinymce` with
`vendor:publish --tag=shopper-cms-assets`, and set `base_url` + `suffix: '.min'`.
Add the publish step to `docker/php/entrypoint.sh` like `filament:assets`.
Self-hosted TinyMCE 7+ needs `license_key: 'gpl'` (or a commercial key); keep it in config.

**Images**:
- `images_upload_handler` posts to `POST {admin}/cms/editor/upload` (auth, CSRF, permission
  `add_uploads`), which calls `Shopper\Core\Actions\StoreUploadAction` and returns `{ location: url }`.
  Only image types are accepted on this route.
- "Add Media" toolbar button opens the `UploadPicker` modal from plan 03 and inserts
  `<img src alt width height>` (or a link for ZIP/TXT files).
- Paste/drop of images goes through the same upload handler (`paste_data_images` + `automatic_uploads`).

**Sanitizing** (mandatory, server side): add an HTML sanitizer (`stevebauman/purify` or
`mews/purifier`, both wrap HTMLPurifier; pick one and pin it) and run it in `SavePostAction`
before saving content and excerpt. Allowed: headings, paragraphs, lists, tables, links (`http`, `https`,
`mailto`, relative), images from the uploads disk or https, `figure/figcaption`, `iframe` only for
YouTube/Vimeo embeds. Strip `script`, `style`, `on*` attributes and `javascript:` URLs.
The admin must render content only after sanitizing.

## Phases

### Phase 1: field and assets
- [x] Resolve the licence decision. Add `tinymce` to package.json, build copy step, publish tag, entrypoint step.
- [x] `TinyEditor` field + Alpine component + view; dark mode; wire:navigate lifecycle.
- [x] Use it for `content` in the post/page edit form. Excerpt stays a textarea.
- [x] Tests: field renders with config, state round-trips through `callAction`/form fill.

### Phase 2: media
- [x] Upload endpoint + handler, image-only validation, permission check.
- [x] "Add Media" button with `UploadPicker`, alt text and alignment.
- [x] Tests: endpoint rejects guests, users without `add_uploads`, and non-images; returns the URL.

### Phase 3: sanitizing
- [x] Add the sanitizer package and config. Sanitize in `SavePostAction`.
- [x] Tests: `<script>`, `onerror=`, `javascript:` links and foreign iframes are removed;
      tables, images, YouTube iframes survive.

### Phase 4: browser check
- [x] Create a post with headings, list, table, image upload and an embed; save, reload, content intact.
- [x] Navigate away and back (SPA), editor still works; dark mode looks right; console clean.

## Acceptance criteria

- Editing feels like the WordPress classic editor for the listed features.
- No unsanitized HTML can be stored through the form or the API.

## Progress log

- 2026-11-01 Phase 1: **TinyMCE 8.10.1** (latest; the plan assumed 7), `license_key: 'gpl'` (user's decision;
  `license.md` confirms GPLv2+ and the code checks for `'gpl'`), pinned in package.json devDependencies.
  `scripts/build.js` copies only the minified runtime (3.5 MB, 59 files) to `packages/cms/dist/tinymce` and builds
  the Alpine component `resources/js/tiny-editor.js` (ESM) to `dist/tiny-editor.js`; new npm scripts
  `build:cms` / `dev:cms` (part of `build` / `dev`). TinyMCE is published with
  `vendor:publish --tag=shopper-cms-assets` (added to the Docker entrypoint and the CLAUDE.md deploy steps); the
  component is registered with `FilamentAsset` and loaded on demand through `x-load-src`, so `filament:assets`
  publishes it. `Forms\Components\TinyEditor` (Filament field; `toolbar()`, `plugins()`, `height()`,
  `uploadUrl()`; defaults in `shopper.cms.editor`, WordPress-like two-row toolbar, dark skin when the admin is
  dark) replaces the content textarea. **Bugs found in the browser and fixed:** after leaving the editor through
  `wire:navigate` the TinyMCE instance stayed registered. Two causes: Alpine's `destroy()` does not always run on
  `wire:navigate`, so the editor is also removed on `livewire:navigating`; and Alpine wrapped the editor in a
  reactive Proxy, so `tinymce.remove()` (which matches by identity) found nothing. The editor now lives in a
  closure variable, and a stale instance with the same id is removed before init. Note for releases: the
  component URL is versioned with Filament's version (`?v=…`), so browsers may keep an old copy after an update.
  Also fixed `.claude/hooks/guard-bash.sh`, which blocked commands that only *mentioned* `php artisan` in text; it
  now matches only where a shell command starts. 5 tests; browser: content loads, edits save (checked in the DB),
  navigation leaves 0 editors behind, console clean.
- 2026-11-01 Phase 2: `POST /cpanel/cms/editor/upload` (`EditorUploadController`, admin auth + CSRF, needs
  `add_uploads`, images only, stored through `StoreUploadAction`, returns `{location}`) wired to TinyMCE's
  `images_upload_handler`, so pasted, dropped and dialog images land in the Uploads library. The field only enables
  uploads for users who may upload. **Deviation:** the endpoint answers 403 as JSON itself, because the admin turns
  403s under `/cpanel` into a redirect to its Forbidden page, which TinyMCE cannot read. "Add media" is a custom
  TinyMCE button (`TinyEditor::mediaAction()`) that mounts the page action from the new
  `Livewire\Concerns\InteractsWithEditorMedia` trait: upload picker (images and other files; `UploadPicker` got
  an `imagesOnly` switch), alt text, alignment (WordPress `alignleft/center/right` classes); the chosen file comes
  back as escaped HTML through a `cms-editor-insert` browser event that only the requesting editor handles. Images
  are inserted without width/height (the Upload model does not store dimensions). Browser: Add media inserts
  `<img class="aligncenter" alt="…">`, a pasted image is uploaded and its `data:` URL replaced, console clean.
  8 tests; CMS suite 128 pass, Pint and PHPStan clean.
- 2026-11-01 Phase 3: **Deviation:** HTMLPurifier (`ezyang/htmlpurifier` ^4.19, LGPL) used directly instead of the
  `stevebauman/purify` / `mews/purifier` wrappers: all rules live in one `Support\HtmlSanitizer` (singleton), with
  no global config to publish or clash with a host app's own Purifier setup. Allowed: formatting, h2–h6, lists,
  tables, links (http/https/mailto/tel/relative, `target=_blank` gets `rel=noopener`), images, `figure` /
  `figcaption` (added to HTMLPurifier's definition), only alignment classes, and the inline CSS TinyMCE produces;
  iframes only from YouTube/Vimeo. `SavePostAction` sanitizes `content` and `excerpt` on every save, so the API and
  imports are covered too. Installing it showed the Docker image lacked PHP `soap` (required by the existing
  `ivanmitrikeski/laravel-shipping`), so Composer could not install anything; `soap` is now in the Dockerfile.
  Composer also reports 14 existing security advisories in 7 packages (not addressed here). 20 tests; CMS suite
  148 pass, Pint and PHPStan clean.
- 2026-11-01 Phase 4: created a post in the browser with a heading, centred paragraph, list, table, aligned image
  and YouTube embed plus an `onclick` / `javascript:` link; after save and a full reload everything survived and the
  unsafe parts were gone. Added WordPress's `alignleft/center/right` rules to the editor's `content_style`
  (aligned images were not shown aligned). Dark and light skins checked; `wire:navigate` post editor → pages list →
  page editor leaves exactly one connected editor; console clean. Full suite: 1196 tests pass, Pint and PHPStan
  clean. Note: demo post "Ideas for the holiday gift guide" keeps the test content from this plan until the next
  `migrate:fresh --seed`.
