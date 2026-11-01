<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Shopper\Cms\Support\CmsPermissions;

return new class extends Migration
{
    /**
     * WordPress capabilities on top of browse/read/edit/add/delete: contributors may write
     * but not publish, and only editors may change other people's content.
     *
     * @var array<string, string>
     */
    private array $capabilities = [
        'publish_posts' => 'posts',
        'edit_others_posts' => 'posts',
        'publish_pages' => 'pages',
        'edit_others_pages' => 'pages',
    ];

    public function up(): void
    {
        CmsPermissions::install(['posts', 'pages'], $this->capabilities);
    }

    public function down(): void
    {
        CmsPermissions::uninstall(['posts', 'pages'], $this->capabilities);
    }
};
