<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DataEntryResource\Pages;
use App\Models\DataEntry;
use App\Models\Website;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

class DataEntryResource extends Resource
{
    protected static ?string $model = DataEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Data Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Entry Information')
                    ->schema([
                        Forms\Components\Select::make('website_id')
                            ->relationship('website', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => request()->query('website_id')),
                        
                        Forms\Components\TextInput::make('identifier')
                            ->label('Identifier (NIK/ID)')
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('status')
                            ->options(DataEntry::STATUSES)
                            ->default('pending')
                            ->required(),
                        
                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher value = processed first'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Form Data')
                    ->schema([
                        Forms\Components\KeyValue::make('data')
                            ->label('Form Data')
                            ->keyLabel('Field Name')
                            ->valueLabel('Value')
                            ->required()
                            ->addActionLabel('Add Field')
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('Processing Settings')
                    ->schema([
                        Forms\Components\TextInput::make('max_attempts')
                            ->numeric()
                            ->default(3)
                            ->minValue(1)
                            ->maxValue(10),
                        
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Schedule For')
                            ->helperText('Leave empty to process immediately'),
                        
                        Forms\Components\Select::make('proxy_id')
                            ->relationship('proxy', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Specific proxy to use (optional)'),
                    ])->columns(3),
                
                Forms\Components\Section::make('Results')
                    ->schema([
                        Forms\Components\Textarea::make('result_message')
                            ->disabled()
                            ->rows(2),
                        
                        Forms\Components\KeyValue::make('result_data')
                            ->disabled(),
                        
                        Forms\Components\Textarea::make('error_message')
                            ->disabled()
                            ->rows(2),
                    ])
                    ->visible(fn ($record) => $record && $record->exists)
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('website.name')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('identifier')
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
            ->filters([
                Tables\Filters\SelectFilter::make('website')
                    ->relationship('website', 'name'),
                
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->status, ['failed', 'cancelled']))
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
                    ->url(fn ($record) => JobLogResource::getUrl('index', [
                        'tableFilters[data_entry_id][value]' => $record->id,
                    ])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('queue')
                        ->label('Queue Selected')
                        ->icon('heroicon-o-play')
                        ->action(function (Collection $records) {
                            // Group by website
                            $grouped = $records->load('website')->groupBy('website_id');
                            $totalQueued = 0;
                            
                            foreach ($grouped as $websiteId => $websiteEntries) {
                                $website = $websiteEntries->first()->website;
                                
                                if (!$website) {
                                    continue;
                                }
                                
                                try {
                                    // Send batch to bot engine
                                    $response = \Illuminate\Support\Facades\Http::timeout(30)
                                        ->post(config('services.bot_engine.url') . '/jobs/batch', [
                                            'entries' => $websiteEntries->map(fn ($entry) => [
                                                'id' => $entry->id,
                                                'identifier' => $entry->identifier,
                                                'data' => $entry->data,
                                            ])->values()->toArray(),
                                            'websiteSlug' => $website->slug,
                                        ]);
                                    
                                    if ($response->successful()) {
                                        // Mark entries as queued
                                        DataEntry::whereIn('id', $websiteEntries->pluck('id'))->update([
                                            'status' => 'queued',
                                        ]);
                                        $totalQueued += $websiteEntries->count();
                                    }
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('Error Queueing Jobs')
                                        ->body("Failed to queue jobs for {$website->name}: {$e->getMessage()}")
                                        ->danger()
                                        ->send();
                                }
                            }
                            
                            if ($totalQueued > 0) {
                                Notification::make()
                                    ->title('Entries Queued')
                                    ->body("{$totalQueued} entries have been sent to bot engine.")
                                    ->success()
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
                    
                    Tables\Actions\DeleteBulkAction::make(),
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
                            // Create default template if not exists
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
                    ->form([
                        Forms\Components\Select::make('website_id')
                            ->label('Target Website')
                            ->options(Website::pluck('name', 'id'))
                            ->required(),
                        
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
                        // FileUpload with disk('local') and visibility('private') saves to storage/app/private/
                        // Try multiple possible locations
                        $possiblePaths = [
                            storage_path('app/private/' . $data['file']),      // private visibility
                            storage_path('app/' . $data['file']),               // default local
                            storage_path('app/public/' . $data['file']),        // public disk
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
                        
                        // Run import synchronously (not queued)
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
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDataEntries::route('/'),
            'website-entries' => Pages\WebsiteDataEntries::route('/website/{websiteId}'),
            'create' => Pages\CreateDataEntry::route('/create'),
            'view' => Pages\ViewDataEntry::route('/{record}'),
            'edit' => Pages\EditDataEntry::route('/{record}/edit'),
        ];
    }
}
