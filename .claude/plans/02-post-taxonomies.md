# 02: Post categories and tags

Status: Done
Depends on: 01

## Goal

Categories (hierarchical) and tags (flat) for blog posts, managed like WordPress's
Posts → Categories and Posts → Tags screens. They are separate from the shop's product
categories and `ProductTag`, which stay untouched.

## WordPress behaviour to match

- Category: name, slug, parent, description. Hierarchical. Every post has at least one
  category; if none is chosen it gets the default category ("Uncategorized"), which cannot be deleted.
- Deleting a category moves its posts to the default category and its children to its parent.
- Tag: name, slug, description. Tags can be created inline while editing a post.
- List screens show the post count per term, with bulk delete and search.
- Each term has an archive URL (`/category/<slug>`, `/tag/<slug>`, see plan 06) and SEO meta (plan 07).

## Data model

| Table | Columns |
|-------|---------|
| `cms_post_categories` | common fields, `name`, `slug` unique, `description` text nullable, `parent_id` FK self nullable, `position` smallint default 0, `metadata` json nullable |
| `cms_post_tags` | common fields, `name`, `slug` unique, `description` text nullable |
| `cms_category_post` | `post_id` FK cascade, `category_id` FK cascade, primary (post_id, category_id) |
| `cms_post_tag` | `post_id` FK cascade, `tag_id` FK cascade, primary (post_id, tag_id) |
| `cms_posts.primary_category_id` | FK nullable, nullOnDelete; used for `%category%` permalinks and breadcrumbs |

Setting `cms_default_category_id` (in `Setting`), created by the migration/seeder as "Uncategorized".

## Code layout

```
packages/cms/src/Models/PostCategory.php   HasSlug, parent/children, posts(); uses staudenmeir/laravel-adjacency-list (already a dependency) for tree queries
packages/cms/src/Models/PostTag.php        HasSlug, posts()
packages/cms/src/Actions/SyncPostTermsAction.php     categories + tags (creates new tags by name), applies default category, sets primary category
packages/cms/src/Actions/DeletePostCategoryAction.php  moves posts → default, children → parent; refuses the default category
packages/cms/src/Livewire/Pages/PostCategories/Index.php   table + create/edit modal (name, slug, parent tree select, description)
packages/cms/src/Livewire/Pages/PostTags/Index.php         table + create/edit modal
```

The tree select can reuse `codewithdennis/filament-select-tree` (already required) the way the
product category form does.

## Phases

### Phase 1: models
- [x] Migrations, models, factories, `Post::categories()` / `Post::tags()` relations.
- [x] Default category setting + seeding of "Uncategorized".
- [x] `SyncPostTermsAction`, `DeletePostCategoryAction`.
- [x] Tests: default category applied when none chosen, new tags created from names,
      deleting a category moves posts and children, default category cannot be deleted.

### Phase 2: admin screens
- [x] Category and tag list pages (sidebar group "Blog" or "Content" next to Posts, from the addon).
- [x] Columns: name, slug, description (short), parent (categories), post count (`withCount`).
- [x] Permissions: `post_categories`, `post_tags`.
- [x] Tests: authorization, create/edit/delete through actions, count column.

### Phase 3: demo data
- [x] Seed 5 categories (one nested) and 10 tags; attach to demo posts.

## Acceptance criteria

- Both screens work in the browser with no console errors.
- A post saved without categories ends up in "Uncategorized".

## Progress log

- 2026-10-27 Phase 1: migration (categories, tags, two pivots, `cms_posts.primary_category_id`), `PostCategory`
  (`HasRecursiveRelationships` + `HasSlug`), `PostTag`, factories, `Post::categories()/tags()/primaryCategory()`,
  `ResolveDefaultCategoryAction` (creates "Uncategorized" in the migration and again on demand if it or the
  `cms_default_category_id` setting goes missing), `SyncPostTermsAction` (default category, primary category
  fallback, tags by id or name, skipped for types without `categories`/`tags` support),
  `DeletePostCategoryAction` (refuses the default, orphans → default, children up a level, primary category
  reassigned, in a transaction). **Deviations:** term slugs use Shopper's existing `HasSlug`, which numbers
  duplicates `-1`, `-2` (WordPress uses `-2`), to stay consistent with the shop's categories and tags. No model
  contracts for terms (not swappable yet). Dev DB migrated. 56 CMS tests pass, Pint and PHPStan clean.
- 2026-10-27 Phase 2: `CmsAddon` now registers routes (`routes/admin.php`: `/cpanel/posts/categories`,
  `/cpanel/posts/tags`, names `shopper.cms.post-categories.index` / `shopper.cms.post-tags.index`), the
  `ContentSidebar` group "Content" (weight 4) and the two Livewire pages. Views/translations load from the CMS
  provider (`shopper-cms::`). `SavePostCategoryAction` blocks parent loops; the parent select is an indented
  tree without the category and its descendants (plain `Select` + `tree()->depthFirst()` instead of
  `filament-select-tree`, so the exclusion is easy). The default category shows as a badge and has no delete
  action; bulk delete skips it. Permissions `post_categories` / `post_tags` (group `posts`) via a new
  `Support\CmsPermissions` helper, which the plan 01 permission migration now also uses. **Bug found and fixed:**
  `descendantsAndSelf()->whereKey()` generates invalid SQL (column referenced outside the recursive CTE), so the
  loop check compares ids in PHP. Plan 03 should move Categories/Tags under a "Posts" item. Browser: both
  screens render, create works, console clean. Noted, not fixed (admin, pre-existing): after reseeding, an old
  browser session hits `Route [login] not defined` from Laravel's `AuthenticateSession`, since the admin's login
  route is `shopper.login`. 73 CMS tests pass, Pint and PHPStan clean.
- 2026-10-27 Phase 3: `CmsSeeder` creates Buying Guides → Audio, News, Deals, Company (plus Uncategorized) and
  12 tags, synced to the demo posts through `SyncPostTermsAction` (posts without terms land in Uncategorized).
  `migrate:fresh --seed` verified on MySQL. Full suite: 1121 tests pass.
