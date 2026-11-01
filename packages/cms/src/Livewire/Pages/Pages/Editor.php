<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Pages;

use Shopper\Cms\Livewire\Pages\Posts\AbstractPostEditor;

class Editor extends AbstractPostEditor
{
    public function typeKey(): string
    {
        return 'page';
    }
}
