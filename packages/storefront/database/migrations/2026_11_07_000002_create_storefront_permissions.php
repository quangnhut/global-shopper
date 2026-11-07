<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Shopper\Models\Permission;
use Shopper\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `manage_homepage` covers the Homepage menu: sections and the header & footer settings.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $permissions = [
        'manage_homepage' => 'Manage the home page',
    ];

    public function up(): void
    {
        if (! Schema::hasTable(config('permission.table_names.permissions'))) {
            return;
        }

        foreach ($this->permissions as $name => $displayName) {
            Permission::query()->firstOrCreate(['name' => $name], [
                'group_name' => 'storefront',
                'display_name' => $displayName,
                'can_be_removed' => false,
            ]);
        }

        Role::query()->where('name', config('shopper.admin.roles.admin'))->first()?->givePermissionTo(array_keys($this->permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable(config('permission.table_names.permissions'))) {
            Permission::query()->whereIn('name', array_keys($this->permissions))->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
