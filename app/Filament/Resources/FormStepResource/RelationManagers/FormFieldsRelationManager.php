<?php

namespace App\Filament\Resources\FormStepResource\RelationManagers;

use App\Models\FormField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FormFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'formFields';

    protected static ?string $title = 'Form Fields';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Field Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Internal field identifier'),
                        
                        Forms\Components\TextInput::make('label')
                            ->maxLength(255)
                            ->helperText('Display label (optional)'),
                        
                        Forms\Components\TextInput::make('selector')
                            ->required()
                            ->placeholder('#field-id, input[name="field"]')
                            ->helperText('CSS selector for this field'),
                        
                        Forms\Components\Select::make('type')
                            ->options(FormField::TYPES)
                            ->required()
                            ->default('text')
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set, $state) => 
                                $set('is_captcha', str_starts_with($state, 'captcha_'))),
                        
                        Forms\Components\TextInput::make('order')
                            ->numeric()
                            ->default(0),
                        
                        Forms\Components\Toggle::make('is_required')
                            ->label('Required Field'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Data Mapping')
                    ->schema([
                        Forms\Components\TextInput::make('data_source_field')
                            ->label('Data Source Field')
                            ->placeholder('nik, email, phone')
                            ->helperText('Maps to field in data_entries JSON'),
                        
                        Forms\Components\TextInput::make('default_value')
                            ->helperText('Default value if data source is empty'),
                        
                        Forms\Components\KeyValue::make('options')
                            ->label('Options (for Select/Radio)')
                            ->keyLabel('Value')
                            ->valueLabel('Label')
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['select', 'radio'])),
                    ])->columns(2),
                
                Forms\Components\Section::make('CAPTCHA Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('captcha_label_selector')
                            ->label('CAPTCHA Label Selector')
                            ->placeholder('label[for="aritmetika"]')
                            ->helperText('Selector for the CAPTCHA question text')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'captcha_arithmetic'),
                        
                        Forms\Components\KeyValue::make('captcha_config')
                            ->label('CAPTCHA Settings')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->helperText('Additional CAPTCHA configuration (site_key, etc.)')
                            ->visible(fn (Forms\Get $get) => str_starts_with($get('type') ?? '', 'captcha_')),
                    ])
                    ->visible(fn (Forms\Get $get) => str_starts_with($get('type') ?? '', 'captcha_')),
                
                Forms\Components\Section::make('Timing')
                    ->schema([
                        Forms\Components\TextInput::make('delay_before')
                            ->numeric()
                            ->default(0)
                            ->suffix('ms')
                            ->helperText('Wait before filling this field'),
                        
                        Forms\Components\TextInput::make('delay_after')
                            ->numeric()
                            ->default(0)
                            ->suffix('ms')
                            ->helperText('Wait after filling this field'),
                        
                        Forms\Components\Toggle::make('clear_before_fill')
                            ->label('Clear Before Fill')
                            ->default(true)
                            ->helperText('Clear existing value before typing'),
                    ])->columns(3),
                
                Forms\Components\Section::make('Validation')
                    ->schema([
                        Forms\Components\TextInput::make('validation_regex')
                            ->label('Validation Pattern')
                            ->placeholder('^[0-9]{16}$')
                            ->helperText('Regex pattern to validate input'),
                    ]),
                
                Forms\Components\Section::make('Custom Handler')
                    ->schema([
                        Forms\Components\Textarea::make('custom_handler')
                            ->rows(5)
                            ->placeholder('// async function(page, element, value) { ... }')
                            ->helperText('Custom JavaScript for handling this field'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'custom'),
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
                
                Tables\Columns\TextColumn::make('selector')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->selector),
                
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'captcha_') => 'danger',
                        $state === 'click_button' => 'warning',
                        $state === 'custom' => 'info',
                        default => 'primary',
                    }),
                
                Tables\Columns\TextColumn::make('data_source_field')
                    ->label('Data Source')
                    ->placeholder('-'),
                
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Req.')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(FormField::TYPES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
