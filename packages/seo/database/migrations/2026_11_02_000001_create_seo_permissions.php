<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Shopper\Models\Permission;
use Shopper\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `manage_seo` covers every SEO screen; `manage_seo_scripts` additionally allows raw head/body code
 * and custom JSON-LD, which can run scripts on the storefront.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $permissions = [
        'manage_seo' => 'Manage SEO',
        'manage_seo_scripts' => 'Manage SEO scripts and custom code',
    ];

    public function up(): void
    {
        if (! Schema::hasTable(config('permission.table_names.permissions'))) {
            return;
        }

        foreach ($this->permissions as $name => $displayName) {
            Permission::query()->firstOrCreate(['name' => $name], [
                'group_name' => 'seo',
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
