<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Shopper\Cms\Support\CmsPermissions;

return new class extends Migration
{
    public function up(): void
    {
        CmsPermissions::install(['post_categories', 'post_tags'], group: 'posts');
    }

    public function down(): void
    {
        CmsPermissions::uninstall(['post_categories', 'post_tags']);
    }
};
