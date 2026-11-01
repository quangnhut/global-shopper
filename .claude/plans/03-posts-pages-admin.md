# 03: Posts and pages admin (statuses, trash, bulk actions)

Status: Done
Depends on: 01, 02

## Goal

WordPress-like list and edit screens for posts and pages: status tabs, trash and restore,
bulk actions, quick edit, and an edit screen with a "Publish" box. The content field is a plain
textarea in this plan; plan 04 swaps it for TinyMCE.

## WordPress behaviour to match

**List screen** (Posts → All Posts, Pages → All Pages)
- Status tabs with counts: All (everything except trash) | Mine | Published | Scheduled | Drafts |
  Pending | Private | Trash. Empty tabs are hidden.
- Columns: title (with "— Draft", "— Pending", "— Private", "— Scheduled" suffix), author,
  categories, tags (posts), date (shows "Published", "Last modified" or "Scheduled" + date).
  Pages show the hierarchy with "— " prefixes for children.
- Filters: month, category (posts), author. Search by title/content.
- Row actions: Edit, Quick Edit, Trash, View/Preview. In the Trash tab: Restore, Delete Permanently,
  plus an "Empty Trash" button.
- Bulk actions: Edit (status/author/categories for many), Move to Trash; in Trash: Restore, Delete Permanently.

**Edit screen**
- Title, slug (editable "Permalink: … Edit" under the title), content, excerpt.
- Publish box: Status (Draft / Pending Review / Published), Visibility (Public / Private /
  Password protected + password), "Publish immediately" or schedule a date, buttons
  "Save Draft", "Preview", "Publish"/"Schedule"/"Update", "Move to Trash".
  Users without `publish_posts` see "Submit for Review" (sets Pending) instead of Publish.
- Posts: categories checklist (with "+ Add New Category"), tags input (comma separated, autocomplete),
  primary category selector.
- Pages: Page Attributes box: parent (tree), template, order.
- Featured image: pick from or upload to the Uploads library.
- "Last edited by X on <date>".

## Code layout (in packages/cms)

```
src/Livewire/Pages/Posts/Index.php      type-aware list (posts and pages share it, route parameter or two thin subclasses)
src/Livewire/Pages/Posts/Edit.php       create + edit (route model binding, withTrashed for the Trash notice)
src/Livewire/Components/PublishBox.php  status/visibility/schedule logic in the form schema
src/Livewire/Components/UploadPicker.php  modal to choose an existing upload or upload a new one (reused by plans 04 and 07)
src/Filament/Tables/PostStatusTabs.php  builds the tabs + counts with one grouped query
resources/views/livewire/pages/posts/{index,edit}.blade.php
routes/admin.php                         registered via CmsAddon::addonRoutes
```

Routes (inside the admin prefix):
`/posts`, `/posts/create`, `/posts/{post}/edit`, `/pages`, `/pages/create`, `/pages/{post}/edit`.

Sidebar (addon `addSidebar`): group "Content" with Posts (All posts, Categories, Tags), Pages, Uploads.
Consider moving the existing Uploads item from Catalog into this group.

## Phases

### Phase 1: list screen
- [x] Table with columns, status tabs + counts, filters, search, pagination.
- [x] Row actions: edit, trash; Trash tab: restore, delete permanently, "Empty Trash".
- [x] Bulk actions: trash, restore, delete permanently, bulk edit (status, author, categories).
- [x] Quick Edit modal: title, slug, date, status, password, categories/tags (posts), parent/order (pages).
- [x] Tests: tab counts, trash/restore keeps the original status, delete permanently only from trash,
      users without `edit_others_posts` only see edit actions on their own posts.

### Phase 2: edit screen
- [x] Form: title, slug (auto from title until edited, see Tag page pattern), content (Textarea for now), excerpt.
- [x] Publish box with the status/visibility/schedule rules from plan 01 (`SavePostAction`, `PublishPostAction`).
- [x] "Submit for Review" for users without `publish_*`.
- [x] Categories/tags/primary category (posts); parent/template/order (pages).
- [x] Featured image through `UploadPicker`.
- [x] Unsaved-changes warning when leaving (Livewire `wire:dirty` + `beforeunload`, careful with `useSpa()` navigation).
- [x] Tests: create draft, publish, schedule, pending for contributors, password visibility, featured image.

### Phase 3: preview
- [x] Signed, short-lived preview URL (`URL::temporarySignedRoute`) usable by the storefront / API (plan 06).
- [x] Test: preview URL works for drafts and expires.

### Phase 4: polish
- [x] Dashboard widget "At a glance" (posts/pages counts) and "Recent drafts" via render hooks.
- [x] Browser check of all screens, both themes, console clean.

## Acceptance criteria

- Every WordPress behaviour listed above works in the browser.
- Trash and restore never lose the original status.

## Progress log

- 2026-10-29 Phases 1-2 (built together: every row links to the edit screen, so its routes had to exist):
  - **List** (`Livewire\Pages\Posts\AbstractPostIndex`, thin `Posts\Index` / `Pages\Index`): Filament `HasTabs` +
    the admin's `tabs-navigation` (All, Mine, one tab per status, Trash; empty tabs hidden, counts from one grouped
    query), title with WordPress suffixes ("— Draft", "— Password protected"), "— " depth prefix and parent-first
    ordering for pages, author/categories/tags/date columns ("Published" / "Scheduled" / "Last modified"), month,
    category and author filters, Quick Edit, bulk Edit (adds categories, never removes, like WordPress), Trash /
    Restore / Delete permanently / Empty trash.
  - **Editor** (`AbstractPostEditor`, thin `Posts\Editor` / `Pages\Editor`; routes `/cpanel/{posts,pages}/create`
    and `/{post}/edit` with `withTrashed()`): Publish box (status Draft/Pending, plus Published once live; visibility
    Public/Private/Password; publish date; "Last edited by"), buttons Save draft / Publish / Schedule / Update /
    Submit for review / Move to trash / Restore, categories (tree checklist) + primary category + tags with
    suggestions, page attributes (parent, template, order), featured image via the new reusable
    `Filament\UploadPicker` (pick from or upload to the Uploads library).
  - **Permissions** in `Support\PostPermissions` (own vs `edit_others_*`, `publish_*` required to touch live
    content), plus `PostType::permission()` for type-specific names. The sidebar is now Content → Posts
    (Categories, Tags) and Pages.
  - **Bugs found and fixed:** (1) Filament injects closure arguments by name: tab closures named `$builder`
    received a fresh, model-less builder (`onlyTrashed()` undefined); they must be named `$query`. (2) `isset()` is
    false for null values, so page depths stopped one level early. (3) Header actions rendered even when hidden
    ("Empty trash" on the All tab); the views now check `isVisible()`, like the admin wraps its header actions.
    (4) The unsaved-changes prompt fired on the redirect after saving a new post; the guard now only watches link
    clicks and tab close/reload, through Alpine listeners that are removed with the page (global listeners would
    have survived `wire:navigate` and blocked later navigation).
  - **Admin change:** `tabs-navigation` buttons get `shrink-0`, so badges with two digits are no longer cut to
    "1.." when many tabs overflow (the class already exists in the compiled CSS).
  - **Not done:** `Permission::groups()` (admin, `final`) still has no labels for the posts/pages groups; it only
    feeds the "custom permission" dropdown on the roles screen. Browser: list, trash tab, pages hierarchy, create
    → redirect → edit, unsaved-changes prompt all checked, console clean. 35 new tests; full suite 1154 pass,
    Pint and PHPStan clean.
- 2026-10-29 Phase 3: `GeneratePreviewUrlAction` makes a temporary signed URL (`shopper.cms.preview_minutes`,
  default 30) to `/{shopper.cms.preview_path}/{post}` (default `cms-preview`), served by `PreviewPostController`
  with `noindex`. **Deviation:** the route lives outside the admin prefix, because the admin turns every 403 under
  `/cpanel` into a redirect to its Forbidden page, and the link must work for reviewers and the storefront without
  an admin account. Until the editor sanitizes HTML (plan 04), content is shown in a script-less `<iframe sandbox>`.
  "Preview" in the editor opens the *saved* version (WordPress autosaves first; plan 05 adds autosave). Plan 06's
  API should accept the same signature. Checked in a logged-out browser. 5 tests.
- 2026-10-29 Phase 4: `Livewire\Components\DashboardOverview` ("At a glance": published/draft/pending per type the
  user may browse; "Your recent drafts": the user's 5 latest drafts), added through the admin's
  `LayoutRenderHook::DASHBOARD_END` from `CmsAddon`. Browser pass in dark and light themes at 900px and 1280px,
  console clean; the title and tags columns now wrap so Date and the row actions fit at 1280px. 4 tests.
  Full suite: 1163 tests pass, Pint and PHPStan clean.
