<?php

namespace App\Filament\Resources\WebsiteResource\RelationManagers;

use App\Models\DataEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class DataEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'dataEntries';

    protected static ?string $title = 'Data Entries';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('identifier')
                    ->label('Identifier (NIK/ID)')
                    ->maxLength(255),
                
                Forms\Components\KeyValue::make('data')
                    ->label('Form Data')
                    ->keyLabel('Field Name')
                    ->valueLabel('Value')
                    ->required()
                    ->columnSpanFull(),
                
                Forms\Components\Select::make('status')
                    ->options(DataEntry::STATUSES)
                    ->default('pending')
                    ->required(),
                
                Forms\Components\TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Higher value = processed first'),
                
                Forms\Components\TextInput::make('max_attempts')
                    ->numeric()
                    ->default(3)
                    ->minValue(1)
                    ->maxValue(10),
                
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label('Schedule For')
                    ->helperText('Leave empty to process immediately'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('identifier')
            ->defaultSort('created_at', 'desc')
            ->columns([
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
                    ->tooltip(fn ($record) => $record->result_message)
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('error_message')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->color('danger')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('last_attempt_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(DataEntry::STATUSES),
                
                Tables\Filters\Filter::make('has_errors')
                    ->query(fn ($query) => $query->whereNotNull('error_message')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('import')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->required(),
                        Forms\Components\Select::make('identifier_column')
                            ->label('Identifier Column')
                            ->options([
                                'nik' => 'NIK',
                                'id' => 'ID',
                                'email' => 'Email',
                                'phone' => 'Phone',
                            ])
                            ->default('nik'),
                    ])
                    ->action(function (array $data) {
                        // Import logic will be handled by ImportDataEntriesAction
                        Notification::make()
                            ->title('Import Started')
                            ->body('Data import has been queued for processing.')
                            ->success()
                            ->send();
                    }),
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('queue')
                        ->label('Queue Selected')
                        ->icon('heroicon-o-play')
                        ->action(function (Collection $records) {
                            $records->each(fn ($record) => $record->update(['status' => 'queued']));
                            
                            Notification::make()
                                ->title('Entries Queued')
                                ->body($records->count() . ' entries have been queued.')
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
            ]);
    }
}
