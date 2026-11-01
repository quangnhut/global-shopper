<?php

declare(strict_types=1);

namespace Shopper\Cms\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shopper\Cms\Enums\PostVisibility;
use Shopper\Cms\PostTypes\PostType;

interface Post
{
    public function postType(): PostType;

    public function visibility(): PostVisibility;

    public function isPublished(): bool;

    public function isScheduled(): bool;

    public function isPasswordProtected(): bool;

    public function author(): BelongsTo;

    public function parent(): BelongsTo;

    public function children(): HasMany;

    public function featuredImage(): BelongsTo;
}
