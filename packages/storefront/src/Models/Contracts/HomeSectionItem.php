<?php

declare(strict_types=1);

namespace Shopper\Storefront\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface HomeSectionItem
{
    public function section(): BelongsTo;

    public function itemable(): MorphTo;
}
