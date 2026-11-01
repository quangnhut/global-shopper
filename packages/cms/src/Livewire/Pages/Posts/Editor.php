<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Posts;

class Editor extends AbstractPostEditor
{
    public function typeKey(): string
    {
        return 'post';
    }
}
