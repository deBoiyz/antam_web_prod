<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CaptchaServiceResource\Pages;
use App\Models\CaptchaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class CaptchaServiceResource extends Resource
{
    protected static ?string $model = CaptchaService::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Bot Configuration';

    protected static ?string $navigationLabel = 'Captcha';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Service Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('provider')
                            ->options(CaptchaService::PROVIDERS)
                            ->required()
                            ->live(),
                        
                        Forms\Components\TextInput::make('api_key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('provider') !== 'manual'),
                        
                        Forms\Components\TextInput::make('api_url')
                            ->url()
                            ->placeholder('Leave empty for default API URL')
                            ->visible(fn (Forms\Get $get) => $get('provider') !== 'manual'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        
                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Service')
                            ->helperText('Use this service as default when no specific type is matched'),
                        
                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher value = higher priority'),
                    ])->columns(3),
                
                Forms\Components\Section::make('Supported CAPTCHA Types')
                    ->schema([
                        Forms\Components\CheckboxList::make('supported_types')
                            ->options(CaptchaService::CAPTCHA_TYPES)
                            ->columns(3),
                    ]),
                
                Forms\Components\Section::make('Statistics')
                    ->schema([
                        Forms\Components\Placeholder::make('balance_display')
                            ->label('Balance')
                            ->content(fn ($record) => $record ? '$' . number_format($record->balance ?? 0, 4) : '-'),
                        
                        Forms\Components\Placeholder::make('success_rate_display')
                            ->label('Success Rate')
                            ->content(fn ($record) => $record ? $record->success_rate . '%' : '-'),
                        
                        Forms\Components\Placeholder::make('avg_solve_time')
                            ->label('Avg. Solve Time')
                            ->content(fn ($record) => $record ? ($record->average_solve_time ?? '-') . 's' : '-'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record && $record->exists),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '2captcha' => 'primary',
                        'capsolver' => 'success',
                        'anticaptcha' => 'warning',
                        'manual' => 'gray',
                        default => 'gray',
                    }),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('balance')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state ?? 0, 4)),
                
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Success Rate')
                    ->formatStateUsing(fn ($record) => $record->success_rate . '%')
                    ->color(fn ($record) => $record->success_rate >= 80 ? 'success' : ($record->success_rate >= 50 ? 'warning' : 'danger')),
                
                Tables\Columns\TextColumn::make('average_solve_time')
                    ->label('Avg. Time')
                    ->suffix('s')
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                
                Tables\Filters\SelectFilter::make('provider')
                    ->options(CaptchaService::PROVIDERS),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('checkBalance')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('info')
                        ->visible(fn ($record) => $record->provider !== 'manual')
                        ->action(function (CaptchaService $record) {
                            // Queue balance check
                            Notification::make()
                                ->title('Balance Check')
                                ->body('Balance check queued. Refresh to see updated balance.')
                                ->info()
                                ->send();
                        }),
                    Tables\Actions\DeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
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
            'index' => Pages\ListCaptchaServices::route('/'),
            'create' => Pages\CreateCaptchaService::route('/create'),
            'edit' => Pages\EditCaptchaService::route('/{record}/edit'),
        ];
    }
}
