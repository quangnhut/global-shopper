<?php

declare(strict_types=1);

namespace Shopper\Storefront\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface HomeSection
{
    public function items(): HasMany;

    public function isLive(): bool;
}
