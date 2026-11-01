<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName('cms_posts'), function (Blueprint $table): void {
            $this->addCommonFields($table, true);

            $table->string('type', 32);
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('status', 20);
            $table->string('password')->nullable();
            $table->integer('menu_order')->default(0);
            $table->string('template')->nullable();
            $table->dateTime('published_at')->nullable()->index();
            $table->json('metadata')->nullable();

            $this->addForeignKey($table, 'author_id', 'users');
            $this->addForeignKey($table, 'parent_id', $this->getTableName('cms_posts'));
            $this->addForeignKey($table, 'featured_upload_id', $this->getTableName('uploads'));

            $table->index(['type', 'status', 'published_at']);
            $table->index(['type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName('cms_posts'));
    }
};
