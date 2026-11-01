---
name: shopper-admin-page
description: Step-by-step checklist for adding a new page (list/CRUD screen) to the Shopper admin panel, wired into feature flags, routes, sidebar, permissions, translations and tests. Use when building any new admin screen such as Posts, Pages, Post categories, SEO settings.
---

# Add a Shopper admin page

Follow the order below. The Uploads page (`packages/admin/src/Livewire/Pages/Upload/Index.php`)
and the Tags page (`packages/admin/src/Livewire/Pages/Tag/Index.php`) are working references.

For a feature that lives in its own package (cms, seo), register the same pieces through
an addon instead of editing `packages/admin` (see "Addon variant" at the end).

## 1. Model and migration (core package)

- Migration in `packages/core/database/migrations/`, extend `Shopper\Core\Helpers\Migration`,
  table name via `$this->getTableName('things')`, use `addCommonFields()` and `addForeignKey()`.
- Model in `packages/core/src/Models/` with `protected $guarded = []`, `casts()` method,
  `getTable()` returning `shopper_table('things')`, `@property-read` docblock.
- Business logic goes in a `final` action class in `packages/core/src/Actions/` with one `execute()` method.

## 2. Feature flag

Add `'thing' => FeatureState::Enabled,` to `packages/admin/config/features.php`.
Guard routes and sidebar items with `Feature::enabled('thing')`.

## 3. Livewire page

- `packages/admin/src/Livewire/Pages/Thing/Index.php` extends `AbstractPageComponent`,
  implements `HasActions, HasSchemas, HasTable`, uses `HandlesAuthorizationExceptions`,
  `InteractsWithActions`, `InteractsWithSchemas`, `InteractsWithTable`.
- `mount()` calls `$this->authorize('browse_things')`.
- Every action: `->authorize('add_things')` / `edit_` / `delete_` plus
  `->visible(shopper()->auth()->user()->can(...))`.
- Bulk deletes iterate `$records->each->delete()` so model events fire.
- `render()` returns `view('shopper::livewire.pages.things.index')->title(__('shopper::pages/things.menu'))`.

## 4. Register the component

Create `packages/admin/config/components/thing.php` with
`'pages' => ['thing-index' => Livewire\Pages\Thing\Index::class]` and add `'thing'` to
`$componentsConfig` in `packages/admin/src/Providers/ComponentsServiceProvider.php`.

## 5. Route

In `packages/admin/routes/cpanel.php`:

```php
if (Feature::enabled('thing')) {
    Route::get('/things', config('shopper.components.thing.pages.thing-index'))
        ->name('things.index');
}
```

## 6. View and render hooks

- `packages/admin/resources/views/livewire/pages/things/index.blade.php` using
  `<x-shopper::container>`, `<x-shopper::heading>` and `{{ $this->table }}`.
- Add `THINGS_TABLE_BEFORE/AFTER` constants to the right `packages/admin/src/View/*RenderHook.php`
  and render them around the table.

## 7. Sidebar

Add an item in the matching `packages/admin/src/Events/*Sidebar.php` with
`setAuthorized($this->user->hasPermissionTo('browse_things'))`, `useSpa()`, `route(...)`
and a `phosphor-*` icon (check it exists in `vendor/codeat3/blade-phosphor-icons/resources/svg`).

## 8. Permissions

Add `Permission::generate('things');` to
`packages/admin/database/seeders/PermissionsTableSeeder.php` (creates browse/read/edit/add/delete).
The admin role receives every permission through `PermissionRoleTableSeeder`.

## 9. Translations

`packages/admin/resources/lang/en/pages/things.php` (and `vi`). Keys used by convention:
`menu`, `single`, `create`. Reuse `shopper::forms.label.*` and `shopper::forms.actions.*`.

## 10. Tests

`tests/Admin/Livewire/Pages/Thing/IndexTest.php` with `uses(Tests\Admin\TestCase::class);`,
grant permissions with `$this->user->givePermissionTo(...)`, and cover: action hidden without
permission, happy path, and any security rule. Run them inside Docker (see the docker-dev skill).

## Addon variant (for packages/cms, packages/seo)

Implement `Shopper\Contracts\ShopperAddon` (extend `Shopper\Addon\BaseAddon`) and in `register()` call
`$panel->addonRoutes(...)`, `addonSidebar(...)`, `addonLivewireComponents([...])`,
`addonViews('shopper-cms', ...)`, `addonSettingItems([...])`; permissions through
`addonManager()->addPermissions([...])`. Confirm the addon is registered before
`ShopperServiceProvider::bootAddons()` runs.
