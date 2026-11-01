<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName('seo_meta'), function (Blueprint $table): void {
            $this->addCommonFields($table);

            $table->morphs('seoable');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('focus_keyword')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->boolean('robots_index')->nullable();
            $table->boolean('robots_follow')->nullable();
            $table->json('robots_advanced')->nullable();
            $table->boolean('exclude_from_sitemap')->default(false);
            $table->string('breadcrumb_title')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('schema_type', 64)->nullable();
            $table->json('schema_custom')->nullable();

            $this->addForeignKey($table, 'og_image_upload_id', $this->getTableName('uploads'));
            $this->addForeignKey($table, 'twitter_image_upload_id', $this->getTableName('uploads'));

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName('seo_meta'));
    }
};
