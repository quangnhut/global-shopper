<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Posts;

class Index extends AbstractPostIndex
{
    public function typeKey(): string
    {
        return 'post';
    }
}
