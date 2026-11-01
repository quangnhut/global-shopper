<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Shopper\Cms\Support\CmsPermissions;

return new class extends Migration
{
    public function up(): void
    {
        CmsPermissions::install([], ['manage_permalinks' => 'posts']);
    }

    public function down(): void
    {
        CmsPermissions::uninstall([], ['manage_permalinks' => 'posts']);
    }
};
