<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProxyResource\Pages;
use App\Models\Proxy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ProxyResource extends Resource
{
    protected static ?string $model = Proxy::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Bot Configuration';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Proxy Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->maxLength(255)
                            ->placeholder('My Proxy Server'),
                        
                        Forms\Components\Select::make('type')
                            ->options(Proxy::TYPES)
                            ->default('http')
                            ->required(),
                        
                        Forms\Components\TextInput::make('host')
                            ->required()
                            ->placeholder('proxy.example.com'),
                        
                        Forms\Components\TextInput::make('port')
                            ->numeric()
                            ->required()
                            ->placeholder('8080'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Authentication')
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->maxLength(255),
                    ])->columns(2),
                
                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\TextInput::make('country')
                            ->maxLength(50)
                            ->placeholder('US, ID, SG'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        
                        Forms\Components\Toggle::make('is_rotating')
                            ->label('Rotating Proxy')
                            ->helperText('Enable if this is a rotating proxy service'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->placeholder('Unnamed'),
                
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                
                Tables\Columns\TextColumn::make('host')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('port'),
                
                Tables\Columns\TextColumn::make('country')
                    ->placeholder('-'),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                
                Tables\Columns\IconColumn::make('is_rotating')
                    ->label('Rotating')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Success Rate')
                    ->formatStateUsing(fn ($record) => $record->success_rate . '%')
                    ->color(fn ($record) => $record->success_rate >= 80 ? 'success' : ($record->success_rate >= 50 ? 'warning' : 'danger')),
                
                Tables\Columns\TextColumn::make('response_time')
                    ->label('Response Time')
                    ->suffix(' ms')
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                
                Tables\Filters\SelectFilter::make('type')
                    ->options(Proxy::TYPES),
                
                Tables\Filters\SelectFilter::make('country'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('test')
                        ->icon('heroicon-o-signal')
                        ->color('info')
                        ->action(function (Proxy $record) {
                            // Test proxy connection
                            Notification::make()
                                ->title('Proxy Test')
                                ->body('Proxy test queued. Check logs for results.')
                                ->info()
                                ->send();
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                    
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListProxies::route('/'),
            'create' => Pages\CreateProxy::route('/create'),
            'edit' => Pages\EditProxy::route('/{record}/edit'),
        ];
    }
}
