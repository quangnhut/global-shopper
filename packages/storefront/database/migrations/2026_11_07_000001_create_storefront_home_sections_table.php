<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName('storefront_home_sections'), function (Blueprint $table): void {
            $this->addCommonFields($table);

            $table->string('type', 32);
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('style', 32)->default('default');
            $table->string('layout', 32)->default('grid');
            $table->unsignedTinyInteger('columns')->default(5);
            $table->unsignedSmallInteger('limit')->nullable();
            $table->string('view_all_url')->nullable();
            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_enabled')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
        });

        Schema::create($this->getTableName('storefront_home_section_items'), function (Blueprint $table): void {
            $table->id();

            $this->addForeignKey($table, 'home_section_id', $this->getTableName('storefront_home_sections'), false);
            $table->morphs('itemable');
            $table->unsignedInteger('position')->default(0);

            $table->unique(['home_section_id', 'itemable_type', 'itemable_id'], 'storefront_home_section_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName('storefront_home_section_items'));
        Schema::dropIfExists($this->getTableName('storefront_home_sections'));
    }
};
