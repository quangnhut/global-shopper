<?php

declare(strict_types=1);

namespace Shopper\Seo\Filament;

use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Seo\Support\SeoFields;

/**
 * "Set noindex / Set index / Remove from sitemap" for any table of SEO-able entries.
 */
final class SeoBulkActions
{
    /**
     * @return list<BulkAction>
     */
    public static function make(): array
    {
        $action = fn (string $name, string $label, Untitledui $icon, array $state): BulkAction => BulkAction::make("seo_{$name}")
            ->label(__("shopper-seo::panel.content.{$label}"))
            ->icon($icon)
            ->authorize('manage_seo')
            ->visible(fn (): bool => (bool) shopper()->auth()->user()?->can('manage_seo'))
            ->action(function (Collection $records) use ($state): void {
                $records->each(fn (Model $record) => SeoFields::save($record, $state));

                Notification::make()
                    ->title(trans_choice('shopper-seo::panel.content.updated', $records->count(), ['count' => $records->count()]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();

        return [
            $action('noindex', 'set_noindex', Untitledui::EyeOff, ['robots_index' => 'no']),
            $action('index', 'set_index', Untitledui::Eye, ['robots_index' => 'yes']),
            $action('default', 'set_default', Untitledui::ReverseLeft, ['robots_index' => 'default']),
            $action('exclude_sitemap', 'exclude_sitemap', Untitledui::MinusCircle, ['exclude_from_sitemap' => true]),
            $action('include_sitemap', 'include_sitemap', Untitledui::PlusCircle, ['exclude_from_sitemap' => false]),
        ];
    }
}
