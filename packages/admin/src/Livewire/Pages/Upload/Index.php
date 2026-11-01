<?php

declare(strict_types=1);

namespace Shopper\Livewire\Pages\Upload;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use InvalidArgumentException;
use Mckenziearts\Icons\Untitledui\Enums\Untitledui;
use Shopper\Core\Actions\StoreUploadAction;
use Shopper\Core\Models\Upload;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Traits\HandlesAuthorizationExceptions;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Index extends AbstractPageComponent implements HasActions, HasSchemas, HasTable
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        $this->authorize('browse_uploads');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Upload::query()->with('user')->latest())
            ->columns([
                ImageColumn::make('preview')
                    ->label(__('shopper::pages/uploads.preview'))
                    ->state(fn (Upload $record): ?string => $record->isImage() ? $record->url : null)
                    ->imageSize(48)
                    ->square(),
                TextColumn::make('name')
                    ->label(__('shopper::forms.label.name'))
                    ->description(fn (Upload $record): string => $record->path)
                    ->searchable(['name', 'file_name'])
                    ->sortable(),
                TextColumn::make('extension')
                    ->label(__('shopper::forms.label.type'))
                    ->formatStateUsing(fn (string $state): string => mb_strtoupper($state))
                    ->badge()
                    ->color(fn (Upload $record): string => $record->isImage() ? 'info' : 'gray'),
                TextColumn::make('size')
                    ->label(__('shopper::pages/uploads.size'))
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 1))
                    ->sortable(),
                TextColumn::make('url')
                    ->label(__('shopper::forms.label.url'))
                    ->limit(40)
                    ->copyable()
                    ->copyMessage(__('shopper::pages/uploads.url_copied'))
                    ->toggleable(),
                TextColumn::make('user.full_name')
                    ->label(__('shopper::pages/uploads.uploaded_by'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('shopper::forms.label.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('shopper::forms.label.type'))
                    ->options([
                        'image' => __('shopper::pages/uploads.types.image'),
                        'archive' => __('shopper::pages/uploads.types.archive'),
                        'text' => __('shopper::pages/uploads.types.text'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'image' => $query->where('mime_type', 'like', 'image/%'),
                        'archive' => $query->where('extension', 'zip'),
                        'text' => $query->where('extension', 'txt'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('shopper::pages/uploads.open'))
                    ->icon(Untitledui::LinkExternal01)
                    ->iconButton()
                    ->url(fn (Upload $record): string => $record->url, shouldOpenInNewTab: true),
                Action::make('download')
                    ->label(__('shopper::pages/uploads.download'))
                    ->icon(Untitledui::Download02)
                    ->iconButton()
                    ->action(fn (Upload $record): StreamedResponse => Storage::disk($record->disk)->download($record->path, $record->name)),
                Action::make('delete')
                    ->label(__('shopper::forms.actions.delete'))
                    ->icon(Untitledui::Trash03)
                    ->iconButton()
                    ->modalIcon(Untitledui::Trash03)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Upload $record): void {
                        $record->delete();

                        Notification::make()
                            ->title(__('shopper::notifications.delete', ['item' => __('shopper::pages/uploads.single')]))
                            ->success()
                            ->send();
                    })
                    ->authorize('delete_uploads')
                    ->visible(shopper()->auth()->user()->can('delete_uploads')),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make()
                    ->authorize('delete_uploads')
                    ->visible(shopper()->auth()->user()->can('delete_uploads'))
                    ->label(__('shopper::forms.actions.delete'))
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        // Delete one by one so each model event removes its file from disk.
                        $records->each->delete();

                        Notification::make()
                            ->title(__('shopper::notifications.delete', ['item' => __('shopper::pages/uploads.single')]))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public function uploadAction(): Action
    {
        $extensions = config('shopper.media.uploads.extensions', []);
        $maxSize = (int) config('shopper.media.uploads.max_size');

        return Action::make('upload')
            ->authorize('add_uploads')
            ->label(__('shopper::pages/uploads.upload'))
            ->icon(Untitledui::UploadCloud02)
            ->modalHeading(__('shopper::pages/uploads.upload'))
            ->modalWidth(Width::Large)
            ->schema([
                FileUpload::make('files')
                    ->label(__('shopper::pages/uploads.files'))
                    ->helperText(__('shopper::pages/uploads.helper', [
                        'types' => mb_strtoupper(implode(', ', $extensions)),
                        'size' => Number::fileSize($maxSize * 1024),
                    ]))
                    ->multiple()
                    ->required()
                    ->storeFiles(false)
                    ->acceptedFileTypes(config('shopper.media.uploads.mime_types', []))
                    ->maxSize($maxSize),
            ])
            ->modalSubmitActionLabel(__('shopper::pages/uploads.upload'))
            ->action(function (array $data, StoreUploadAction $storeUpload): void {
                $stored = 0;

                /** @var UploadedFile $file */
                foreach ($data['files'] as $file) {
                    try {
                        $storeUpload->execute($file, shopper()->auth()->id());
                        $stored++;
                    } catch (InvalidArgumentException) {
                        Notification::make()
                            ->title(__('shopper::pages/uploads.rejected', ['name' => $file->getClientOriginalName()]))
                            ->danger()
                            ->send();
                    }
                }

                if ($stored > 0) {
                    Notification::make()
                        ->title(trans_choice('shopper::pages/uploads.uploaded', $stored, ['count' => $stored]))
                        ->success()
                        ->send();
                }
            });
    }

    public function render(): View
    {
        return view('shopper::livewire.pages.uploads.index')
            ->title(__('shopper::pages/uploads.menu'));
    }
}
