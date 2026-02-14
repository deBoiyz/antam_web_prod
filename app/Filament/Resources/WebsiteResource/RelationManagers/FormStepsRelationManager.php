<?php

namespace App\Filament\Resources\WebsiteResource\RelationManagers;

use App\Models\FormField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FormStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'formSteps';

    protected static ?string $title = 'Form Steps';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Step Configuration')
                    ->schema([
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
                            ->placeholder('/form/step-1')
                            ->helperText('URL pattern to match for this step (optional)'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Wait Settings')
                    ->schema([
                        Forms\Components\TextInput::make('wait_for_selector')
                            ->label('Wait for Selector')
                            ->placeholder('#form-container')
                            ->helperText('CSS selector to wait for before proceeding'),
                        
                        Forms\Components\TextInput::make('wait_timeout')
                            ->numeric()
                            ->default(10000)
                            ->suffix('ms'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Success/Failure Indicators')
                    ->schema([
                        Forms\Components\TextInput::make('success_indicator')
                            ->placeholder('.success-message')
                            ->helperText('CSS selector that indicates success'),
                        
                        Forms\Components\TextInput::make('failure_indicator')
                            ->placeholder('.error-message')
                            ->helperText('CSS selector that indicates failure'),
                        
                        Forms\Components\TextInput::make('success_message_selector')
                            ->placeholder('.success-text')
                            ->helperText('Selector to extract success message'),
                        
                        Forms\Components\TextInput::make('failure_message_selector')
                            ->placeholder('.error-text')
                            ->helperText('Selector to extract failure message'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Custom Script')
                    ->schema([
                        Forms\Components\Textarea::make('custom_script')
                            ->rows(5)
                            ->placeholder('// JavaScript code to execute')
                            ->helperText('Custom JavaScript to run during this step'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('action_type') === 'custom_script'),
                
                Forms\Components\Toggle::make('is_final_step')
                    ->label('Final Step')
                    ->helperText('Mark this as the final step of the form'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('#')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('action_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fill_form' => 'primary',
                        'click' => 'warning',
                        'wait' => 'gray',
                        'screenshot' => 'info',
                        'extract_data' => 'success',
                        'navigate' => 'secondary',
                        'custom_script' => 'danger',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('form_fields_count')
                    ->label('Fields')
                    ->counts('formFields'),
                
                Tables\Columns\TextColumn::make('wait_for_selector')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\IconColumn::make('is_final_step')
                    ->label('Final')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('manageFields')
                    ->label('Fields')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn ($record) => route('filament.admin.resources.form-steps.edit', $record)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
