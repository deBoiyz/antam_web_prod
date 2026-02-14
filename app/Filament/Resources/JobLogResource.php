<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobLogResource\Pages;
use App\Models\JobLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JobLogResource extends Resource
{
    protected static ?string $model = JobLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Log Details')
                    ->schema([
                        Forms\Components\Select::make('data_entry_id')
                            ->relationship('dataEntry', 'identifier')
                            ->disabled(),
                        
                        Forms\Components\Select::make('website_id')
                            ->relationship('website', 'name')
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options(JobLog::STATUSES)
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('step_number')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('step_name')
                            ->disabled(),
                        
                        Forms\Components\Textarea::make('message')
                            ->disabled()
                            ->columnSpanFull(),
                        
                        Forms\Components\KeyValue::make('details')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('executed_at')
                    ->dateTime()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('website.name')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('dataEntry.identifier')
                    ->label('Entry ID')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'started' => 'info',
                        'step_completed' => 'warning',
                        'success' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('step_number')
                    ->label('Step')
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('step_name')
                    ->limit(20)
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('message')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->message),
                
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->suffix(' ms')
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('browser_session_id')
                    ->label('Session')
                    ->limit(10)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('website')
                    ->relationship('website', 'name'),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options(JobLog::STATUSES),
                
                Tables\Filters\Filter::make('data_entry_id')
                    ->form([
                        Forms\Components\TextInput::make('data_entry_id')
                            ->label('Data Entry ID')
                            ->numeric(),
                    ])
                    ->query(fn ($query, array $data) => 
                        $data['data_entry_id'] 
                            ? $query->where('data_entry_id', $data['data_entry_id']) 
                            : $query
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('executed_at', 'desc')
            ->poll('10s');
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
            'index' => Pages\ListJobLogs::route('/'),
            'view' => Pages\ViewJobLog::route('/{record}'),
        ];
    }
}
