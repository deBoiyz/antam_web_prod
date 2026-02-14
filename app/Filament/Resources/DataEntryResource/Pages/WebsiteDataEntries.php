<?php

namespace App\Filament\Resources\DataEntryResource\Pages;

use App\Filament\Resources\DataEntryResource;
use App\Filament\Resources\JobLogResource;
use App\Models\DataEntry;
use App\Models\Website;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Collection;

class WebsiteDataEntries extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DataEntryResource::class;

    protected static string $view = 'filament.resources.data-entry-resource.pages.website-data-entries';

    public Website $website;

    public function mount(int $websiteId): void
    {
        $this->website = Website::findOrFail($websiteId);
    }

    public function getTitle(): string
    {
        return $this->website->name;
    }

    public function getSubheading(): ?string
    {
        return $this->website->base_url;
    }

    public function getBreadcrumbs(): array
    {
        return [
            DataEntryResource::getUrl() => 'Data Entries',
            '' => $this->website->name,
        ];
    }

    public function getStats(): array
    {
        $result = $this->website->dataEntries()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('queued', 'processing') THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            ")
            ->first();

        return [
            'total' => (int) ($result->total ?? 0),
            'pending' => (int) ($result->pending ?? 0),
            'in_progress' => (int) ($result->in_progress ?? 0),
            'success' => (int) ($result->success ?? 0),
            'failed' => (int) ($result->failed ?? 0),
            'cancelled' => (int) ($result->cancelled ?? 0),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            
            Actions\Action::make('back')
                ->label('Back to Groups')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DataEntryResource::getUrl()),
                
            Actions\Action::make('create')
                ->label('New Entry')
                ->icon('heroicon-o-plus')
                ->url(DataEntryResource::getUrl('create') . '?website_id=' . $this->website->id),

            Actions\Action::make('queueAll')
                ->label('Queue All Pending')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->website->dataEntries()->where('status', 'pending')->exists())
                ->requiresConfirmation()
                ->modalHeading('Queue All Pending Entries')
                ->modalDescription(fn () => "This will send all pending entries for \"{$this->website->name}\" to the bot engine.")
                ->action(function () {
                    $entries = $this->website->dataEntries()->where('status', 'pending')->get();

                    if ($entries->isEmpty()) {
                        return;
                    }

                    try {
                        $response = \Illuminate\Support\Facades\Http::timeout(30)
                            ->post(config('services.bot_engine.url') . '/jobs/batch', [
                                'entries' => $entries->map(fn ($entry) => [
                                    'id' => $entry->id,
                                    'identifier' => $entry->identifier,
                                    'data' => $entry->data,
                                ])->values()->toArray(),
                                'websiteSlug' => $this->website->slug,
                            ]);

                        if ($response->successful()) {
                            DataEntry::whereIn('id', $entries->pluck('id'))->update(['status' => 'queued']);

                            Notification::make()
                                ->title('Entries Queued')
                                ->body("{$entries->count()} entries sent to bot engine.")
                                ->success()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error Queueing Jobs')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DataEntry::query()->where('website_id', $this->website->id))
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Identifier (NIK/ID)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'queued' => 'info',
                        'processing' => 'warning',
                        'success' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('attempts')
                    ->label('Attempts')
                    ->formatStateUsing(fn ($record) => "{$record->attempts}/{$record->max_attempts}"),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('result_message')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('error_message')
                    ->limit(30)
                    ->color('danger')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_attempt_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (DataEntry $record) => DataEntryResource::getUrl('view', ['record' => $record]))
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(DataEntry::STATUSES),

                Tables\Filters\Filter::make('has_errors')
                    ->query(fn ($query) => $query->whereNotNull('error_message'))
                    ->label('Has Errors'),

                Tables\Filters\Filter::make('scheduled')
                    ->query(fn ($query) => $query->whereNotNull('scheduled_at')->where('scheduled_at', '>', now()))
                    ->label('Scheduled'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('edit')
                        ->label('Edit')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (DataEntry $record) => DataEntryResource::getUrl('edit', ['record' => $record])),

                    Tables\Actions\Action::make('retry')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (DataEntry $record) => in_array($record->status, ['failed', 'cancelled']))
                        ->action(function (DataEntry $record) {
                            $record->update([
                                'status' => 'pending',
                                'attempts' => 0,
                                'error_message' => null,
                            ]);

                            Notification::make()
                                ->title('Entry Queued for Retry')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('viewLogs')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->url(fn (DataEntry $record) => JobLogResource::getUrl('index', [
                            'tableFilters[data_entry_id][value]' => $record->id,
                        ])),

                    Tables\Actions\Action::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (DataEntry $record) => $record->delete()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('queue')
                        ->label('Queue Selected')
                        ->icon('heroicon-o-play')
                        ->action(function (Collection $records) {
                            try {
                                $response = \Illuminate\Support\Facades\Http::timeout(30)
                                    ->post(config('services.bot_engine.url') . '/jobs/batch', [
                                        'entries' => $records->map(fn ($entry) => [
                                            'id' => $entry->id,
                                            'identifier' => $entry->identifier,
                                            'data' => $entry->data,
                                        ])->values()->toArray(),
                                        'websiteSlug' => $this->website->slug,
                                    ]);

                                if ($response->successful()) {
                                    DataEntry::whereIn('id', $records->pluck('id'))->update([
                                        'status' => 'queued',
                                    ]);

                                    Notification::make()
                                        ->title('Entries Queued')
                                        ->body("{$records->count()} entries sent to bot engine.")
                                        ->success()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Queueing Jobs')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\BulkAction::make('reset')
                        ->label('Reset to Pending')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (Collection $records) {
                            $records->each(fn ($record) => $record->update([
                                'status' => 'pending',
                                'attempts' => 0,
                                'error_message' => null,
                            ]));

                            Notification::make()
                                ->title('Entries Reset')
                                ->body($records->count() . ' entries have been reset.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('cancel')
                        ->label('Cancel Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each(fn ($record) => $record->markAsCancelled());

                            Notification::make()
                                ->title('Entries Cancelled')
                                ->body($records->count() . ' entries have been cancelled.')
                                ->warning()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulkDelete')
                        ->label('Delete Selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            $records->each(fn ($record) => $record->delete());

                            Notification::make()
                                ->title('Entries Deleted')
                                ->body("{$count} entries have been deleted.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('downloadTemplate')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        $templatePath = storage_path('app/templates/data_entries_template.csv');

                        if (!file_exists($templatePath)) {
                            $content = "nama,email,telepon,nik,alamat,tanggal_lahir,kota\n";
                            $content .= "John Doe,john@example.com,081234567890,1234567890123456,Jl. Example No. 1,1990-01-01,Jakarta\n";

                            if (!is_dir(dirname($templatePath))) {
                                mkdir(dirname($templatePath), 0755, true);
                            }
                            file_put_contents($templatePath, $content);
                        }

                        return response()->download($templatePath, 'data_entries_template.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),

                Tables\Actions\Action::make('import')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                            ->required()
                            ->disk('local')
                            ->directory('imports')
                            ->visibility('private'),

                        Forms\Components\TextInput::make('identifier_column')
                            ->label('Identifier Column Name')
                            ->default('nik')
                            ->helperText('Column name in CSV to use as identifier'),

                        Forms\Components\Placeholder::make('template_hint')
                            ->content('Download the template first to see the required format.')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        $possiblePaths = [
                            storage_path('app/private/' . $data['file']),
                            storage_path('app/' . $data['file']),
                            storage_path('app/public/' . $data['file']),
                            storage_path('app/private/imports/' . basename($data['file'])),
                            storage_path('app/imports/' . basename($data['file'])),
                            storage_path('app/public/imports/' . basename($data['file'])),
                        ];

                        $filePath = null;
                        foreach ($possiblePaths as $path) {
                            if (file_exists($path)) {
                                $filePath = $path;
                                break;
                            }
                        }

                        if (!$filePath) {
                            Notification::make()
                                ->title('Import Failed')
                                ->body('Could not find uploaded file: ' . $data['file'])
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            \App\Jobs\ImportDataEntriesJob::dispatchSync(
                                $this->website->id,
                                $filePath,
                                $data['identifier_column']
                            );

                            Notification::make()
                                ->title('Import Completed')
                                ->body('Data entries have been imported successfully.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Import Failed')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
