<?php

declare(strict_types=1);

namespace Shopper\Cms\Livewire\Pages\Settings;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Cms\Actions\SaveRedirectAction;
use Shopper\Cms\Models\Redirect;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Traits\HandlesAuthorizationExceptions;

#[Layout('shopper::components.layouts.setting')]
class Redirects extends AbstractPageComponent implements HasActions, HasSchemas, HasTable
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        $this->authorize('manage_permalinks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Redirect::query())
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('from_path')
                    ->label(__('shopper-cms::permalinks.redirects.from'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('to_path')
                    ->label(__('shopper-cms::permalinks.redirects.to'))
                    ->fontFamily('mono')
                    ->placeholder(__('shopper-cms::permalinks.redirects.gone'))
                    ->searchable(),
                TextColumn::make('status_code')
                    ->label(__('shopper-cms::permalinks.redirects.status'))
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        301 => 'success',
                        302 => 'info',
                        default => 'danger',
                    }),
                TextColumn::make('source')
                    ->label(__('shopper-cms::permalinks.redirects.source'))
                    ->formatStateUsing(fn (string $state): string => __("shopper-cms::permalinks.redirects.{$state}"))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('hits')
                    ->label(__('shopper-cms::permalinks.redirects.hits'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_hit_at')
                    ->label(__('shopper-cms::permalinks.redirects.last_hit'))
                    ->since()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label(__('shopper-cms::permalinks.redirects.source'))
                    ->options([
                        Redirect::SOURCE_AUTO => __('shopper-cms::permalinks.redirects.auto'),
                        Redirect::SOURCE_MANUAL => __('shopper-cms::permalinks.redirects.manual'),
                    ]),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('shopper::forms.actions.edit'))
                    ->icon(Untitledui::Edit03)
                    ->iconButton()
                    ->fillForm(fn (Redirect $record): array => $record->only(['from_path', 'to_path', 'status_code']))
                    ->schema($this->redirectForm(...))
                    ->modalWidth(Width::Large)
                    ->action(fn (Redirect $record, array $data) => $this->persist($data, $record)),
                Action::make('delete')
                    ->label(__('shopper::forms.actions.delete'))
                    ->icon(Untitledui::Trash03)
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Redirect $record) => $record->delete()),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make()
                    ->label(__('shopper::forms.actions.delete'))
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public function redirectForm(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')
                ->label(__('shopper-cms::permalinks.redirects.from'))
                ->placeholder('/old-page')
                ->required()
                ->maxLength(255),
            Select::make('status_code')
                ->label(__('shopper-cms::permalinks.redirects.status'))
                ->options([
                    301 => '301 · Moved permanently',
                    302 => '302 · Found (temporary)',
                    410 => '410 · '.__('shopper-cms::permalinks.redirects.gone'),
                ])
                ->default(301)
                ->selectablePlaceholder(false)
                ->required()
                ->live(),
            TextInput::make('to_path')
                ->label(__('shopper-cms::permalinks.redirects.to'))
                ->placeholder('/new-page')
                ->required(fn (Get $get): bool => (int) $get('status_code') !== 410)
                ->visible(fn (Get $get): bool => (int) $get('status_code') !== 410)
                ->maxLength(255),
        ]);
    }

    public function createAction(): Action
    {
        return Action::make('create')
            ->label(__('shopper-cms::permalinks.redirects.create'))
            ->schema($this->redirectForm(...))
            ->modalWidth(Width::Large)
            ->action(fn (array $data) => $this->persist($data));
    }

    public function render(): View
    {
        return view('shopper-cms::livewire.pages.settings.redirects')
            ->title(__('shopper-cms::permalinks.redirects.title'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data, ?Redirect $redirect = null): void
    {
        $this->authorize('manage_permalinks');

        try {
            resolve(SaveRedirectAction::class)->execute(
                (string) $data['from_path'],
                $data['to_path'] ?? null,
                (int) $data['status_code'],
                redirect: $redirect,
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["mountedActions.0.data.{$key}" => $messages])->all()
            );
        }

        Notification::make()
            ->title(__('shopper::notifications.update', ['item' => __('shopper-cms::permalinks.redirects.single')]))
            ->success()
            ->send();
    }
}
