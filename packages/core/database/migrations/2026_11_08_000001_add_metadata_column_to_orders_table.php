<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn($this->getTableName('orders'), 'metadata')) {
            return;
        }

        Schema::table($this->getTableName('orders'), function (Blueprint $table): void {
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->getTableName('orders'), function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
    }
};
