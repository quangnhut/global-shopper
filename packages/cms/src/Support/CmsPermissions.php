<?php

declare(strict_types=1);

namespace Shopper\Cms\Support;

use Illuminate\Support\Facades\Schema;
use Shopper\Models\Permission;
use Shopper\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The admin collects addon permissions but never stores them, so CMS migrations
 * create their own and grant them to the administrator role on existing installs.
 */
final class CmsPermissions
{
    private const array ABILITIES = ['browse', 'read', 'edit', 'add', 'delete'];

    /**
     * @param  list<string>  $items  each gets browse/read/edit/add/delete permissions
     * @param  array<string, string>  $capabilities  extra permission name => group
     * @param  ?string  $group  permission group of the items (defaults to each item's name)
     */
    public static function install(array $items, array $capabilities = [], ?string $group = null): void
    {
        if (! self::tablesExist()) {
            return;
        }

        foreach ($items as $item) {
            Permission::generate($item, $group);
        }

        foreach ($capabilities as $name => $group) {
            Permission::query()->firstOrCreate(['name' => $name], [
                'group_name' => $group,
                'display_name' => str($name)->replace('_', ' ')->ucfirst()->toString(),
                'can_be_removed' => false,
            ]);
        }

        Role::query()
            ->where('name', config('shopper.admin.roles.admin'))
            ->first()
            ?->givePermissionTo(self::names($items, $capabilities));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $items
     * @param  array<string, string>  $capabilities
     */
    public static function uninstall(array $items, array $capabilities = []): void
    {
        if (! self::tablesExist()) {
            return;
        }

        Permission::query()->whereIn('name', self::names($items, $capabilities))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $items
     * @param  array<string, string>  $capabilities
     * @return list<string>
     */
    private static function names(array $items, array $capabilities): array
    {
        $names = array_keys($capabilities);

        foreach ($items as $item) {
            foreach (self::ABILITIES as $ability) {
                $names[] = "{$ability}_{$item}";
            }
        }

        return $names;
    }

    private static function tablesExist(): bool
    {
        return Schema::hasTable(config('permission.table_names.permissions'));
    }
}
