<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Cms\Actions\ResolveDefaultCategoryAction;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName('cms_post_categories'), function (Blueprint $table): void {
            $this->addCommonFields($table);

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('metadata')->nullable();

            $this->addForeignKey($table, 'parent_id', $this->getTableName('cms_post_categories'));
        });

        Schema::create($this->getTableName('cms_post_tags'), function (Blueprint $table): void {
            $this->addCommonFields($table);

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
        });

        Schema::create($this->getTableName('cms_category_post'), function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained($this->getTableName('cms_posts'))->cascadeOnDelete();
            $table->foreignId('category_id')->constrained($this->getTableName('cms_post_categories'))->cascadeOnDelete();

            $table->primary(['post_id', 'category_id']);
        });

        Schema::create($this->getTableName('cms_post_tag'), function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained($this->getTableName('cms_posts'))->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained($this->getTableName('cms_post_tags'))->cascadeOnDelete();

            $table->primary(['post_id', 'tag_id']);
        });

        Schema::table($this->getTableName('cms_posts'), function (Blueprint $table): void {
            $this->addForeignKey($table, 'primary_category_id', $this->getTableName('cms_post_categories'));
        });

        // WordPress always has an "Uncategorized" category for posts saved without one.
        resolve(ResolveDefaultCategoryAction::class)->execute();
    }

    public function down(): void
    {
        Schema::table($this->getTableName('cms_posts'), function (Blueprint $table): void {
            $table->dropForeign(['primary_category_id']);
            $table->dropColumn('primary_category_id');
        });

        Schema::dropIfExists($this->getTableName('cms_post_tag'));
        Schema::dropIfExists($this->getTableName('cms_category_post'));
        Schema::dropIfExists($this->getTableName('cms_post_tags'));
        Schema::dropIfExists($this->getTableName('cms_post_categories'));
    }
};
