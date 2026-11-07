<?php

declare(strict_types=1);

namespace Shopper\Storefront\Livewire\Pages\Sections;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Storefront\Actions\DuplicateHomeSectionAction;
use Shopper\Storefront\Models\HomeSection;
use Shopper\Storefront\Support\SectionItems;
use Shopper\Storefront\Support\StorefrontCache;
use Shopper\Traits\HandlesAuthorizationExceptions;

class Index extends AbstractPageComponent implements HasActions, HasSchemas, HasTable
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var array<int, int>|null visible item count per section id, for the rows on screen */
    private ?array $shownCounts = null;

    public function mount(): void
    {
        $this->authorize('manage_homepage');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(HomeSection::query()->withCount('items'))
            ->defaultSort('position')
            ->reorderable('position')
            // Filament saves the order with a query, which fires no model events.
            ->afterReordering(function (): void {
                StorefrontCache::flush();
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->width('3rem')
                    ->color('gray'),
                TextColumn::make('title')
                    ->label(__('shopper-storefront::admin.sections.title_field'))
                    ->description(fn (HomeSection $record): ?string => $record->subtitle)
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('shopper-storefront::admin.sections.type'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('style')
                    ->label(__('shopper-storefront::admin.sections.style'))
                    ->badge(),
                TextColumn::make('items_count')
                    ->label(__('shopper-storefront::admin.sections.items'))
                    ->formatStateUsing(fn (HomeSection $record): string => __('shopper-storefront::admin.sections.items_shown', [
                        'shown' => $this->shownCount($record),
                        'picked' => $record->items_count,
                    ]))
                    ->color(fn (HomeSection $record): string => $this->shownCount($record) < $record->items_count ? 'warning' : 'gray'),
                TextColumn::make('schedule')
                    ->label(__('shopper-storefront::admin.sections.state'))
                    ->badge()
                    ->state(fn (HomeSection $record): string => match (true) {
                        ! $record->is_enabled => __('shopper-storefront::admin.sections.states.disabled'),
                        $record->isScheduled() => __('shopper-storefront::admin.sections.states.scheduled'),
                        $record->isExpired() => __('shopper-storefront::admin.sections.states.expired'),
                        default => __('shopper-storefront::admin.sections.states.live'),
                    })
                    ->color(fn (HomeSection $record): string => match (true) {
                        ! $record->is_enabled, $record->isExpired() => 'gray',
                        $record->isScheduled() => 'warning',
                        default => 'success',
                    })
                    ->description(fn (HomeSection $record): ?string => $this->scheduleText($record)),
                ToggleColumn::make('is_enabled')
                    ->label(__('shopper-storefront::admin.sections.enabled'))
                    ->beforeStateUpdated(fn (): mixed => $this->authorize('manage_homepage')),
            ])
            ->recordUrl(fn (HomeSection $record): string => route('shopper.storefront.sections.edit', $record))
            ->recordActions([
                Action::make('edit')
                    ->label(__('shopper::forms.actions.edit'))
                    ->icon(Untitledui::Edit03)
                    ->iconButton()
                    ->url(fn (HomeSection $record): string => route('shopper.storefront.sections.edit', $record)),
                Action::make('duplicate')
                    ->label(__('shopper-storefront::admin.sections.duplicate'))
                    ->icon(Untitledui::Copy06)
                    ->iconButton()
                    ->authorize('manage_homepage')
                    ->action(function (HomeSection $record, DuplicateHomeSectionAction $duplicate): void {
                        $duplicate->execute($record);

                        Notification::make()
                            ->title(__('shopper-storefront::admin.sections.duplicated'))
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->label(__('shopper::forms.actions.delete'))
                    ->icon(Untitledui::Trash03)
                    ->iconButton()
                    ->modalIcon(Untitledui::Trash03)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize('manage_homepage')
                    ->action(function (HomeSection $record): void {
                        $record->delete();

                        Notification::make()
                            ->title(__('shopper::notifications.delete', ['item' => __('shopper-storefront::admin.sections.single')]))
                            ->success()
                            ->send();
                    }),
            ])
            ->groupedBulkActions([
                BulkAction::make('enable')
                    ->label(__('shopper-storefront::admin.sections.enable'))
                    ->icon(Untitledui::Eye)
                    ->authorize('manage_homepage')
                    ->action(fn (Collection $records): mixed => $records->each->update(['is_enabled' => true]))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('disable')
                    ->label(__('shopper-storefront::admin.sections.disable'))
                    ->icon(Untitledui::EyeOff)
                    ->authorize('manage_homepage')
                    ->action(fn (Collection $records): mixed => $records->each->update(['is_enabled' => false]))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('delete')
                    ->label(__('shopper::forms.actions.delete'))
                    ->icon(Untitledui::Trash03)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize('manage_homepage')
                    ->action(fn (Collection $records): mixed => $records->each->delete())
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon(Untitledui::LayoutAlt04)
            ->emptyStateHeading(__('shopper-storefront::admin.sections.empty'))
            ->emptyStateDescription(__('shopper-storefront::admin.sections.empty_description'));
    }

    public function storefrontUrl(): string
    {
        return mb_rtrim((string) (config('shopper.storefront.url') ?: config('app.url')), '/').'/';
    }

    public function render(): View
    {
        return view('shopper-storefront::livewire.pages.sections.index')
            ->title(__('shopper-storefront::admin.sections.menu'));
    }

    /**
     * How many picked items can actually be shown (published, priced, public), for all rows at once.
     */
    private function shownCount(HomeSection $record): int
    {
        if ($this->shownCounts === null) {
            $sections = HomeSection::query()->with('items')->get();
            $resolved = resolve(SectionItems::class)->resolve($sections);
            $this->shownCounts = array_map(fn ($items): int => $items->count(), $resolved);
        }

        return $this->shownCounts[$record->id] ?? 0;
    }

    private function scheduleText(HomeSection $record): ?string
    {
        $format = 'd/m/Y H:i';

        return match (true) {
            $record->starts_at !== null && $record->ends_at !== null => $record->starts_at->format($format).' → '.$record->ends_at->format($format),
            $record->starts_at !== null => __('shopper-storefront::admin.sections.from', ['date' => $record->starts_at->format($format)]),
            $record->ends_at !== null => __('shopper-storefront::admin.sections.until', ['date' => $record->ends_at->format($format)]),
            default => null,
        };
    }
}
