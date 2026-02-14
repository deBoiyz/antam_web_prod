<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BotSessionResource\Pages;
use App\Models\BotSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class BotSessionResource extends Resource
{
    protected static ?string $model = BotSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Information')
                    ->schema([
                        Forms\Components\TextInput::make('session_id')
                            ->disabled(),
                        
                        Forms\Components\Select::make('website_id')
                            ->relationship('website', 'name')
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options(BotSession::STATUSES)
                            ->disabled(),
                    ])->columns(3),
                
                Forms\Components\Section::make('Statistics')
                    ->schema([
                        Forms\Components\TextInput::make('processed_count')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('success_count')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('failure_count')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session_id')
                    ->label('Session')
                    ->limit(15)
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('website.name')
                    ->placeholder('All'),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'idle' => 'gray',
                        'running' => 'success',
                        'paused' => 'warning',
                        'stopped' => 'gray',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('processed_count')
                    ->label('Processed'),
                
                Tables\Columns\TextColumn::make('success_count')
                    ->label('Success')
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('failure_count')
                    ->label('Failed')
                    ->color('danger'),
                
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Rate')
                    ->formatStateUsing(fn ($record) => $record->success_rate . '%')
                    ->color(fn ($record) => $record->success_rate >= 80 ? 'success' : ($record->success_rate >= 50 ? 'warning' : 'danger')),
                
                Tables\Columns\TextColumn::make('uptime')
                    ->label('Uptime'),
                
                Tables\Columns\TextColumn::make('worker_hostname')
                    ->label('Host')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Last Activity')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(BotSession::STATUSES),
                
                Tables\Filters\SelectFilter::make('website')
                    ->relationship('website', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('stop')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, ['running', 'paused']))
                    ->requiresConfirmation()
                    ->action(function (BotSession $record) {
                        $record->markAsStopped();
                        
                        Notification::make()
                            ->title('Session Stopped')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('stop_all')
                        ->label('Stop All')
                        ->icon('heroicon-o-stop')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->markAsStopped()),
                    
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('5s');
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
            'index' => Pages\ListBotSessions::route('/'),
            'view' => Pages\ViewBotSession::route('/{record}'),
        ];
    }
}
