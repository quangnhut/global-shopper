<?php

declare(strict_types=1);

namespace Shopper\Seo\Livewire\Pages;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Seo\Filament\SeoBulkActions;
use Shopper\Seo\Filament\SeoPanel;
use Shopper\Seo\Registry\SeoableRegistry;
use Shopper\Seo\Support\SeoFields;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * SEO → Content: every entry of every SEO-able type with its SEO state, an "Edit SEO" dialog and bulk
 * indexing/sitemap actions (Yoast's bulk editor). One tab per type.
 */
class ContentSeo extends AbstractPageComponent implements HasActions, HasSchemas, HasTable
{
    use HandlesAuthorizationExceptions;
    use HasTabs;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        $this->authorize('manage_seo');

        $this->loadDefaultActiveTab();
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $registry = resolve(SeoableRegistry::class);

        return collect($registry->all())
            ->mapWithKeys(fn (array $type, string $key): array => [$key => Tab::make($registry->label($key))])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->query())
            ->columns([
                TextColumn::make('seo_entry_name')
                    ->label(__('shopper-seo::panel.content.entry'))
                    ->state(fn (Model $record): string => $this->entryName($record))
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('seo_title_state')
                    ->label(__('shopper-seo::panel.content.seo_title'))
                    ->state(fn (Model $record): ?string => SeoFields::state($record)['title'] ?? null)
                    ->placeholder(__('shopper-seo::panel.uses_template'))
                    ->wrap(),
                IconColumn::make('seo_description_state')
                    ->label(__('shopper-seo::panel.content.description'))
                    ->state(fn (Model $record): bool => filled(SeoFields::state($record)['description'] ?? null))
                    ->boolean(),
                TextColumn::make('seo_robots_state')
                    ->label(__('shopper-seo::panel.content.robots'))
                    ->state(fn (Model $record): string => match (SeoFields::meta($record)?->robots_index) {
                        true => 'index',
                        false => 'noindex',
                        null => __('shopper-seo::panel.robots.default'),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'noindex' => 'danger',
                        'index' => 'success',
                        default => 'gray',
                    }),
                IconColumn::make('seo_sitemap_state')
                    ->label(__('shopper-seo::panel.content.sitemap'))
                    ->state(fn (Model $record): bool => ! (SeoFields::meta($record)->exclude_from_sitemap ?? false))
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('editSeo')
                    ->label(__('shopper-seo::panel.content.edit'))
                    ->icon(Untitledui::Edit03)
                    ->iconButton()
                    ->modalHeading(fn (Model $record): string => $this->entryName($record))
                    ->modalWidth(Width::FourExtraLarge)
                    ->fillForm(fn (Model $record): array => ['seo' => SeoFields::state($record)])
                    ->schema(fn (Schema $schema): Schema => $schema->components([SeoPanel::make(collapsed: false)]))
                    ->action(function (Model $record, array $data): void {
                        SeoFields::save($record, $data['seo'] ?? [], (bool) shopper()->auth()->user()?->can('manage_seo_scripts'));

                        Notification::make()->title(__('shopper-seo::panel.saved'))->success()->send();
                    }),
            ])
            ->groupedBulkActions(SeoBulkActions::make());
    }

    public function render(): View
    {
        return view('shopper-seo::livewire.pages.content')->title(__('shopper-seo::panel.content.menu'));
    }

    /**
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        $registry = resolve(SeoableRegistry::class);
        $type = $this->activeTab ?? array_key_first($registry->all());
        $model = $registry->model($type);

        $query = $model::query();

        // Posts and pages share a model: keep the rows of this type only.
        if ($query->getModel()->getConnection()->getSchemaBuilder()->hasColumn($query->getModel()->getTable(), 'type')
            && collect($registry->all())->where('model', $model)->count() > 1) {
            $query->where('type', $type);
        }

        return $query->latest($query->getModel()->getKeyName());
    }

    protected function entryName(Model $record): string
    {
        return (string) ($record->getAttribute('title') ?? $record->getAttribute('name') ?? '#'.$record->getKey());
    }
}
