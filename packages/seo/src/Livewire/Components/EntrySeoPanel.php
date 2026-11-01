<?php

declare(strict_types=1);

namespace Shopper\Seo\Livewire\Components;

use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Shopper\Seo\Filament\SeoPanel;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\Seo\Support\SeoFields;

/**
 * Standalone SEO panel with its own save button, for edit screens SEO cannot change directly (the
 * product edit page gets it as an "Advanced SEO" tab through a render hook).
 *
 * @property-read Schema $form
 */
class EntrySeoPanel extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    #[Locked]
    public string $type;

    #[Locked]
    public int|string $recordId;

    #[Locked]
    public bool $withBasics = true;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(string $type, int|string $recordId, bool $withBasics = true): void
    {
        abort_unless((bool) shopper()->auth()->user()?->can('manage_seo'), 403);

        $this->type = $type;
        $this->recordId = $recordId;
        $this->withBasics = $withBasics;

        $this->form->fill(['seo' => SeoFields::state($this->record())]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([SeoPanel::make($this->withBasics, collapsed: false)]);
    }

    public function save(): void
    {
        abort_unless((bool) shopper()->auth()->user()?->can('manage_seo'), 403);

        $state = $this->form->getState()['seo'] ?? [];

        if (! $this->withBasics) {
            unset($state['title'], $state['description']);
        }

        SeoFields::save($this->record(), $state, (bool) shopper()->auth()->user()->can('manage_seo_scripts'));

        Notification::make()->title(__('shopper-seo::panel.saved'))->success()->send();
    }

    public function render(): View
    {
        return view('shopper-seo::livewire.components.entry-seo-panel');
    }

    private function record(): Model
    {
        $model = resolve(SeoableRegistry::class)->model($this->type);

        return $model::query()->findOrFail($this->recordId);
    }
}
