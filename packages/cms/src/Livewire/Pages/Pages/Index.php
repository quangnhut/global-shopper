<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Pages;

use Shopper\Cms\Livewire\Pages\Posts\AbstractPostIndex;

class Index extends AbstractPostIndex
{
    public function typeKey(): string
    {
        return 'page';
    }
}
