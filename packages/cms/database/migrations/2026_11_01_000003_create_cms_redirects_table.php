<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName('cms_redirects'), function (Blueprint $table): void {
            $this->addCommonFields($table);

            $table->string('from_path')->unique();
            $table->string('to_path')->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('source', 16)->default('manual');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName('cms_redirects'));
    }
};
