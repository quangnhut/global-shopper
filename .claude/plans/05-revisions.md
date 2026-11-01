# 05: Revisions and autosave

Status: Done
Depends on: 03 (04 for autosave from the editor)

## Goal

Every save of a post or page keeps a revision. Editors can compare any two revisions and restore
one, exactly like WordPress's Revisions screen. The editor also autosaves.

## WordPress behaviour to match

- Each update that changes title, content or excerpt stores a revision (a copy of the new state).
  Saves with no changes create nothing.
- The edit screen shows "Revisions: N" with a "Browse" link.
- The revisions screen has a slider over all revisions, shows author + date ("2 hours ago"),
  and a side-by-side diff of title, content and excerpt. A "Compare any two revisions" toggle
  turns it into a two-handle range.
- "Restore This Revision" copies the revision into the post and saves, which itself creates a new
  revision, so restoring can be undone.
- `WP_POST_REVISIONS`: keep all, none, or the last N.
- Autosave every 60 seconds while editing, one autosave per user per post. If an autosave is newer
  than the saved post, the edit screen shows "There is an autosave of this post that is more recent
  than the version below. View the autosave".
- Revisions are deleted with the post (delete permanently), kept while it is in the trash.

## Data model

`cms_post_revisions`:

| Column | Type | Notes |
|--------|------|-------|
| id, created_at, updated_at | common fields | |
| post_id | FK cms_posts, cascadeOnDelete | |
| user_id | FK users, nullable, nullOnDelete | author of the change |
| kind | string(16) | `revision` or `autosave` |
| title | string | |
| content | longText, nullable | already sanitized |
| excerpt | text, nullable | |
| meta | json, nullable | snapshot of SEO fields (plan 07) so they can be restored too |

Index `(post_id, kind, created_at)`. Unique `(post_id, user_id)` is not possible with revisions in
the same table, so autosave uniqueness is enforced in the action (update-or-create).

Config `cms.revisions`: `enabled` (bool), `limit` (null = unlimited, 0 = none, N = keep last N),
`autosave_interval` (seconds, default 60).

## Code layout (packages/cms)

```
src/Models/PostRevision.php
src/Actions/CreateRevisionAction.php    called from SavePostAction after a successful save when title/content/excerpt changed; prunes to the limit
src/Actions/AutosavePostAction.php      update-or-create the user's autosave; never touches the post
src/Actions/RestoreRevisionAction.php   copies fields to the post via SavePostAction (which creates the new revision)
src/Support/RevisionDiff.php            side-by-side diff; library: jfcherng/php-diff (pick and pin at implementation; sebastian/diff is dev-only here)
src/Livewire/Pages/Posts/Revisions.php  route /posts/{post}/revisions?from=&to=
resources/views/livewire/pages/posts/revisions.blade.php
```

Diff output must escape HTML: compare the HTML source as text (like WordPress does), never render it.

## Phases

### Phase 1: storing revisions
- [x] Migration, model, relation `Post::revisions()`.
- [x] `CreateRevisionAction` hooked into `SavePostAction`; pruning by limit.
- [x] Tests: revision only when fields change; limit prunes oldest; deleting a post permanently removes
      its revisions; trashing keeps them.

### Phase 2: revisions screen
- [x] Slider (Alpine) over revisions, compare-two mode, side-by-side diff via `RevisionDiff`.
- [x] "Restore This Revision" (permission: edit the post) with confirmation.
- [x] "Revisions: N · Browse" in the edit screen's publish box.
- [x] Tests: diff escapes HTML, restore updates the post and creates a new revision, permission checks.

### Phase 3: autosave
- [x] Alpine timer in the edit screen (and TinyMCE dirty flag) calls `autosave()` every `autosave_interval`
      only when the form is dirty.
- [x] Notice + "View the autosave" (opens the revisions screen comparing it with the current post).
- [x] Autosave deleted when the user saves the post.
- [x] Tests: one autosave per user, newer-autosave notice, cleared on save.

### Phase 4: browser check
- [x] Edit a post 3 times, compare, restore the first, compare again; console clean.

## Acceptance criteria

- Any earlier version of a post or page can be restored, and the restore itself can be undone.

## Progress log

- 2026-11-01 Phase 1: `cms_post_revisions` (kind `revision` / `autosave`, cascade on permanent delete), `PostRevision`,
  `RevisionKind` enum, `Post::revisions()` (newest first) / `Post::autosaves()`, `CreateRevisionAction` called at the
  end of `SavePostAction` (after sanitizing), config `shopper.cms.revisions` (`enabled`, `limit`, `autosave_interval`).
  Like WordPress the first save also stores a revision, so the original can be restored. Trash/restore and the
  scheduled-publish command use `forceFill()` and never create revisions. **Gotcha:** Eloquent's
  `wasRecentlyCreated` stays true for the model's lifetime, so `SavePostAction` tells the action whether this save
  created the post. The `meta` column (SEO snapshot) stays empty until plan 07. 6 tests; dev DB migrated.
- 2026-11-01 Phase 2: `jfcherng/php-diff` 7.0.1 (BSD-3, PHP ≥ 8.3), `Support\RevisionDiff` (side-by-side, word level,
  HTML source compared as escaped text, one block element per line), `RestoreRevisionAction` (saves through
  `SavePostAction`, so the restore becomes a new revision), `Livewire\Pages\Posts\Revisions` at
  `/cpanel/{posts,pages}/{post}/revisions?to=&from=&compare=` (range slider oldest→newest, "Compare any two
  revisions" with two selects, author + relative date, "Current version" badge, "Restore this revision" with
  confirmation, hidden on the current version; needs edit permission on the post), "Revisions: N · Browse" in the
  editor's publish box. **Additions:** a baseline revision of the previous version is stored when content saved
  before revisions existed is first changed (`Model::getPrevious()`), so that change can be undone; the diff
  library's stylesheet was replaced by own light/dark styles (it was unreadable in dark mode). Browser: slider,
  restore with confirmation, redirect + notification, history grows to 3; console clean. 9 tests.
- 2026-11-01 Phase 3: `AutosavePostAction` (one sanitized autosave per user and post, never touches the post),
  `autosave()` on the editor called by an Alpine timer every `autosave_interval` seconds only after changes since
  the last autosave (TinyMCE edits bubble an `input` event; the timer is also stopped on `livewire:navigating`),
  new content is not autosaved (WordPress creates an auto-draft; not done here). Saving deletes the user's
  autosave. A newer, different autosave shows WordPress's notice with "View the autosave", which opens the
  revisions screen in autosave mode (`?autosave=1`): saved version vs autosave, "Restore this revision" restores it
  and deletes the autosave. For content without revisions the saved post itself is the "from" side. 8 tests.
- 2026-11-01 Phase 4: browser, on two demo posts: two saves with different titles/content, compare in the slider,
  restore the oldest (history grew to 3); real 60-second timer wrote an autosave of unsaved TinyMCE text, reload
  showed the notice, the autosave compared and restored (notice gone; baseline + restored = 2 revisions). Console
  clean throughout. Full suite: 1219 tests pass, Pint and PHPStan clean.
