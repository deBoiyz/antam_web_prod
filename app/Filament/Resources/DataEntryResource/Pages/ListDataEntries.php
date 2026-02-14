<?php

namespace App\Filament\Resources\DataEntryResource\Pages;

use App\Filament\Resources\DataEntryResource;
use App\Models\DataEntry;
use App\Models\Website;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

class ListDataEntries extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DataEntryResource::class;

    protected static string $view = 'filament.resources.data-entry-resource.pages.list-data-entries';

    protected static ?string $title = 'Data Entries';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Website::query()
                    ->has('dataEntries') // Only show websites that have data entries
                    ->withCount([
                        'dataEntries as total_entries_count',
                        'dataEntries as pending_entries_count' => fn (Builder $q) => $q->where('status', 'pending'),
                        'dataEntries as queued_entries_count' => fn (Builder $q) => $q->whereIn('status', ['queued', 'processing']),
                        'dataEntries as success_entries_count' => fn (Builder $q) => $q->where('status', 'success'),
                        'dataEntries as failed_entries_count' => fn (Builder $q) => $q->where('status', 'failed'),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Website')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Website $record) => $record->base_url),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_entries_count')
                    ->label('Total Data Entries')
                    ->sortable()
                    ->alignCenter()
                    ->badge(),

                Tables\Columns\TextColumn::make('pending_entries_count')
                    ->label('Pending')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('queued_entries_count')
                    ->label('In Progress')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('success_entries_count')
                    ->label('Success')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('failed_entries_count')
                    ->label('Failed')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('danger'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\Filter::make('has_pending')
                    ->query(fn (Builder $query) => $query->whereHas('dataEntries', fn ($q) => $q->where('status', 'pending')))
                    ->label('Has Pending Entries'),

                Tables\Filters\Filter::make('has_failed')
                    ->query(fn (Builder $query) => $query->whereHas('dataEntries', fn ($q) => $q->where('status', 'failed')))
                    ->label('Has Failed Entries'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('viewEntries')
                        ->label('View Entries')
                        ->icon('heroicon-o-document-text')
                        ->url(fn (Website $record) => DataEntryResource::getUrl('website-entries', ['websiteId' => $record->id])),

                    Tables\Actions\Action::make('queuePending')
                        ->label('Queue Pending')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Queue All Pending Entries')
                        ->modalDescription(fn (Website $record) => "This will send all {$record->pending_entries_count} pending entries for \"{$record->name}\" to the bot engine.")
                        ->visible(fn (Website $record) => $record->pending_entries_count > 0)
                        ->action(function (Website $record) {
                            $entries = $record->dataEntries()->where('status', 'pending')->get();

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
                                        'websiteSlug' => $record->slug,
                                    ]);

                                if ($response->successful()) {
                                    DataEntry::whereIn('id', $entries->pluck('id'))->update(['status' => 'queued']);

                                    Notification::make()
                                        ->title('Entries Queued')
                                        ->body("{$entries->count()} entries for \"{$record->name}\" sent to bot engine.")
                                        ->success()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Queueing Jobs')
                                    ->body("Failed for \"{$record->name}\": {$e->getMessage()}")
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('deleteEntries')
                        ->label('Delete All Entries')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete All Entries')
                        ->modalDescription(fn (Website $record) => "Are you sure you want to delete all {$record->total_entries_count} data entries for \"{$record->name}\"? This action cannot be undone.")
                        ->modalSubmitActionLabel('Yes, Delete All')
                        ->action(function (Website $record) {
                            $count = $record->dataEntries()->count();
                            $record->dataEntries()->delete();

                            Notification::make()
                                ->title('Entries Deleted')
                                ->body("{$count} entries for \"{$record->name}\" have been deleted.")
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
                        Forms\Components\Select::make('website_id')
                            ->label('Target Website')
                            ->options(Website::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

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
                                $data['website_id'],
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
                
                Tables\Actions\Action::make('create')
                    ->label('New Entry')
                    ->icon('heroicon-o-plus')
                    ->url(DataEntryResource::getUrl('create')),
            ])
            ->recordUrl(fn (Website $record) => DataEntryResource::getUrl('website-entries', ['websiteId' => $record->id]))
            ->emptyStateHeading('No Data Entries')
            ->emptyStateDescription('No data entries found. Create a new entry or import from CSV to get started.')
            ->emptyStateActions([
                Tables\Actions\Action::make('createEntry')
                    ->label('New Entry')
                    ->icon('heroicon-o-plus')
                    ->url(DataEntryResource::getUrl('create')),
            ])
            ->defaultSort('name');
    }
}
