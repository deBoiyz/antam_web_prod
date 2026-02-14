<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormStepResource\Pages;
use App\Filament\Resources\FormStepResource\RelationManagers;
use App\Models\FormStep;
use App\Models\Website;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FormStepResource extends Resource
{
    protected static ?string $model = FormStep::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Bot Configuration';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Step Configuration')
                    ->schema([
                        Forms\Components\Select::make('website_id')
                            ->relationship('website', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('order')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        
                        Forms\Components\Select::make('action_type')
                            ->options([
                                'fill_form' => 'Fill Form',
                                'click' => 'Click Element',
                                'wait' => 'Wait',
                                'screenshot' => 'Take Screenshot',
                                'extract_data' => 'Extract Data',
                                'navigate' => 'Navigate to URL',
                                'custom_script' => 'Custom Script',
                            ])
                            ->default('fill_form')
                            ->required()
                            ->live(),
                        
                        Forms\Components\TextInput::make('url_pattern')
                            ->label('URL Pattern')
                            ->placeholder('/form/step-1'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Wait Settings')
                    ->schema([
                        Forms\Components\TextInput::make('wait_for_selector')
                            ->label('Wait for Selector')
                            ->placeholder('#form-container'),
                        
                        Forms\Components\TextInput::make('wait_timeout')
                            ->numeric()
                            ->default(10000)
                            ->suffix('ms'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Success/Failure Indicators')
                    ->schema([
                        Forms\Components\TextInput::make('success_indicator')
                            ->placeholder('.success-message'),
                        
                        Forms\Components\TextInput::make('failure_indicator')
                            ->placeholder('.error-message'),
                        
                        Forms\Components\TextInput::make('success_message_selector')
                            ->placeholder('.success-text'),
                        
                        Forms\Components\TextInput::make('failure_message_selector')
                            ->placeholder('.error-text'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Custom Script')
                    ->schema([
                        Forms\Components\Textarea::make('custom_script')
                            ->rows(5)
                            ->placeholder('// JavaScript code to execute'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('action_type') === 'custom_script'),
                
                Forms\Components\Toggle::make('is_final_step')
                    ->label('Final Step'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('website.name')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('order')
                    ->label('#')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('action_type')
                    ->badge(),
                
                Tables\Columns\TextColumn::make('form_fields_count')
                    ->label('Fields')
                    ->counts('formFields'),
                
                Tables\Columns\IconColumn::make('is_final_step')
                    ->label('Final')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('website')
                    ->relationship('website', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('website_id')
            ->defaultSort('order');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FormFieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormSteps::route('/'),
            'create' => Pages\CreateFormStep::route('/create'),
            'edit' => Pages\EditFormStep::route('/{record}/edit'),
        ];
    }
}
