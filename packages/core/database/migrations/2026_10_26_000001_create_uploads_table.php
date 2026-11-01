<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName('uploads'), function (Blueprint $table): void {
            $this->addCommonFields($table);

            $table->string('name');
            $table->string('file_name');
            $table->string('path');
            $table->string('disk');
            $table->string('mime_type');
            $table->string('extension', 16)->index();
            $table->unsignedBigInteger('size');

            $this->addForeignKey($table, 'user_id', 'users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName('uploads'));
    }
};
