# 01: CMS package foundation and data model

Status: Done
Depends on: none

## Goal

Create the `packages/cms` package and the data model every later plan builds on: posts and pages
in one table (like `wp_posts`), with statuses, visibility, hierarchy, featured image and soft-delete trash.

## WordPress behaviour to match

- One table for posts and pages, told apart by `post_type`. Pages are hierarchical (`post_parent`)
  and ordered by `menu_order`; posts are not hierarchical.
- Statuses: `publish`, `future` (scheduled), `draft`, `pending` (awaiting review), `private`, `trash`.
- Visibility: public, private (only logged-in editors), password protected.
- A scheduled post becomes published automatically when its date passes.
- Slugs are unique per post type (pages: unique among siblings); duplicates get `-2`, `-3`.

## Scope

In: package skeleton, migrations, models, enums, post type registry, factories, actions for
create/update/publish/trash/restore/force-delete, the scheduled-publish command, demo seed data.
Out: admin UI (plan 03), taxonomies (plan 02), editor (04), revisions (05), URLs (06).

## Data model

`cms_posts` (via `shopper_table('cms_posts')`):m

| Column | Type | Notes |
|--------|------|-------|
| id, created_at, updated_at | `addCommonFields()` | |
| deleted_at | soft deletes | trash |
| type | string(32), index | `post`, `page`, later custom types |
| title | string | |
| slug | string, index | unique per `type` (+ `parent_id` for hierarchical types), enforced in an action |
| excerpt | text, nullable | |
| content | longText, nullable | sanitized HTML |
| status | string(20), index | `PostStatus` enum |
| ~~visibility~~ | not stored | derived like WordPress: `private` status → Private, password set → Password, else Public (see progress log) |
| password | string, nullable | only for `password` visibility, stored hashed |
| author_id | FK users, nullable | |
| parent_id | FK cms_posts, nullable | pages only |
| menu_order | integer, default 0 | |
| template | string, nullable | page template key, from config |
| featured_upload_id | FK uploads, nullable, nullOnDelete | Uploads library |
| primary_category_id | FK cms_post_categories, nullable | added in plan 02 |
| published_at | datetime, nullable, index | publish date, or the scheduled date |
| metadata | json, nullable | extension point |

Indexes: `(type, status, published_at)` for listings, `(type, slug)`.

## Code layout

```
packages/cms/
  composer.json                  shopper/cms, requires shopper/core + shopper/framework
  config/cms.php                 post_types, statuses, trash_days, templates, per_page
  database/migrations/
  database/factories/PostFactory.php
  resources/lang/{en,fr}/
  src/CmsServiceProvider.php
  src/CmsAddon.php               admin wiring (filled by plan 03)
  src/Enums/PostStatus.php       Published, Scheduled, Draft, Pending, Private (+ getLabel/getColor/getIcon)
  src/Enums/PostVisibility.php
  src/PostTypes/PostType.php     value object: key, labels, hierarchical, supports[]
  src/PostTypes/PostTypeRegistry.php  register('post'), register('page'), all(), get()
  src/Models/Post.php            SoftDeletes, scopes: ofType(), published(), visibleTo(user)
  src/Models/Contracts/Post.php  swappable via config('shopper.cms.models.post')
  src/Actions/SavePostAction.php        create/update, unique slug, status rules
  src/Actions/PublishPostAction.php     publish now or schedule (future date → Scheduled)
  src/Actions/TrashPostAction.php       soft delete (children of a page re-parent to its parent, like WP)
  src/Actions/RestorePostAction.php     restore; slug conflict → new unique slug
  src/Actions/UniqueSlugAction.php
  src/Console/PublishScheduledPostsCommand.php   shopper:cms:publish-scheduled (every minute)
  src/Console/EmptyTrashCommand.php              shopper:cms:empty-trash (daily, cms.trash_days)
```

Status rules (`SavePostAction`):
- `Published` with `published_at` in the future → stored as `Scheduled`.
- `Scheduled` with a past date → `Published` (the command also does this).
- First publish sets `published_at = now()` if empty.
- `password` visibility requires a password; other visibilities clear it.

## Phases

### Phase 1: package skeleton
- [x] Create `packages/cms` following the `shopper-package` skill (composer, provider, root autoload/replace/providers, split workflow, phpunit source).
- [x] `config/cms.php` merged under `shopper.cms`.
- [x] Empty `CmsAddon` registered with the panel. Confirm it is registered before `bootAddons()`.
- [x] Test: provider loads, config is available, addon is registered.

### Phase 2: model and enums
- [x] Migration for `cms_posts`.
- [x] `PostStatus`, `PostVisibility` enums (with `ArrayableEnum`, `HasEnumStaticMethods`, Filament interfaces).
- [x] `PostTypeRegistry` with `post` (non-hierarchical) and `page` (hierarchical).
- [x] `Post` model, contract, factory (states: draft, published, scheduled, page, trashed).
- [x] Tests: casts, scopes (`published()` excludes scheduled/private/trashed), registry.

### Phase 3: actions and commands
- [x] `UniqueSlugAction`, `SavePostAction`, `PublishPostAction`, `TrashPostAction`, `RestorePostAction`.
- [x] Scheduled-publish and empty-trash commands, registered in the scheduler.
- [x] Permissions: `posts`, `pages` via `Permission::generate`, plus `publish_posts`, `publish_pages`,
      `edit_others_posts`, `edit_others_pages` (WordPress roles: contributors cannot publish).
- [x] Tests: slug collisions (`hello-world-2`), schedule → publish, trash keeps status, restore,
      page trash re-parents children, empty-trash only deletes items older than `trash_days`.

### Phase 4: demo data
- [x] Workbench seeder `CmsSeeder`: "Hello world!" post, "Sample Page", "Privacy Policy" (draft),
      a few posts per status, one scheduled post, one trashed post. Call it from `DatabaseSeeder`.

## Acceptance criteria

- `migrate:fresh --seed` creates the table and demo content.
- All action rules above are covered by passing tests.

## Progress log

- 2026-10-27 Phase 1: `packages/cms` created and wired (root autoload/replace/providers, split workflow, phpunit sources).
  The addon is attached with `callAfterResolving('shopper', ...)` in `register()`, so it exists before the admin's
  `bootAddons()` regardless of provider order. Added `testbench package:discover` to the Docker entrypoint
  (the dev app did not see the new provider until the discovery cache was rebuilt). 3 tests pass.
- 2026-10-27 Phase 2: migration, `PostStatus` (values match WordPress: publish/future/draft/pending/private),
  `PostVisibility`, `PostType` + `PostTypeRegistry` (singleton built from `shopper.cms.post_types`), `Post` model
  with contract + `HasModelContract`, factory states. **Deviation:** no `visibility` column. WordPress stores
  private as a status and password protection as a non-empty password, so a separate column could contradict
  the status; `Post::visibility()` derives it. Passwords are stored hashed (WordPress keeps them in plain text),
  so editors can replace but not read them. `primary_category_id` is left to plan 02 as planned. 15 tests pass,
  Pint and PHPStan clean.
- 2026-10-27 Phase 3: `UniqueSlugAction`, `SavePostAction` (status/schedule/visibility/parent-loop rules, throws
  `ValidationException` keyed by field so forms can show the errors), `PublishPostAction`, `TrashPostAction`,
  `RestorePostAction`, `ForceDeletePostAction`, `shopper:cms:publish-scheduled` (every minute) and
  `shopper:cms:empty-trash` (daily). **Deviations:** (1) trashing renames the slug to `slug__trashed` and remembers
  the original, like WordPress, so new content can reuse the URL; restore gets it back or `-2`. (2) Child pages are
  re-parented on *permanent* delete (WordPress's `wp_delete_post`), not on trash. (3) Restore keeps the original
  status as the README decided; note WordPress 5.6+ restores to draft instead. (4) The admin never stores addon
  permissions, so a migration creates the 14 permissions and grants them to the administrator role (checked on
  the existing dev DB). For plan 03: `Shopper\Models\Permission::groups()` has no "posts"/"pages" labels yet
  (only used by the custom-permission dropdown on the roles screen). 46 tests pass, Pint and PHPStan clean.
- 2026-10-27 Phase 4: `workbench/database/seeders/CmsSeeder.php` (called from `DatabaseSeeder`) creates WordPress's
  default "Hello world!" and "Sample Page", a draft Privacy Policy, About Us → Our Team (nested, full-width
  template), Contact, posts in every status, a password-protected post and a trashed post, all through the
  actions. `migrate:fresh --seed` verified on MySQL. Full suite: 1094 tests pass.
