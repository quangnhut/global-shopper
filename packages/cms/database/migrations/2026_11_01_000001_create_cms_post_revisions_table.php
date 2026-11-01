<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName('cms_post_revisions'), function (Blueprint $table): void {
            $this->addCommonFields($table);

            $table->foreignId('post_id')->constrained($this->getTableName('cms_posts'))->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('title');
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->json('meta')->nullable();

            $this->addForeignKey($table, 'user_id', 'users');

            $table->index(['post_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName('cms_post_revisions'));
    }
};
