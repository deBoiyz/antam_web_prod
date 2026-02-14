<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebsiteResource\Pages;
use App\Filament\Resources\WebsiteResource\RelationManagers;
use App\Models\Website;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WebsiteResource extends Resource
{
    protected static ?string $model = Website::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Bot Configuration';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Website Configuration')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => 
                                        $set('slug', \Str::slug($state))),
                                
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                
                                Forms\Components\TextInput::make('base_url')
                                    ->required()
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://example.com'),
                                
                                Forms\Components\Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ])->columns(2),
                        
                        Forms\Components\Tabs\Tab::make('Request Settings')
                            ->schema([
                                Forms\Components\TextInput::make('timeout')
                                    ->numeric()
                                    ->default(30000)
                                    ->suffix('ms')
                                    ->helperText('Request timeout in milliseconds'),
                                
                                Forms\Components\TextInput::make('retry_attempts')
                                    ->numeric()
                                    ->default(3)
                                    ->minValue(1)
                                    ->maxValue(10),
                                
                                Forms\Components\TextInput::make('retry_delay')
                                    ->numeric()
                                    ->default(5000)
                                    ->suffix('ms')
                                    ->helperText('Delay between retries'),
                                
                                Forms\Components\TextInput::make('concurrency_limit')
                                    ->numeric()
                                    ->default(5)
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->helperText('Maximum concurrent browser instances'),
                                
                                Forms\Components\TextInput::make('max_jobs_per_minute')
                                    ->label('Max Jobs Per Minute')
                                    ->numeric()
                                    ->default(10)
                                    ->minValue(1)
                                    ->maxValue(1000)
                                    ->helperText('Rate limit for job processing'),
                                
                                Forms\Components\TextInput::make('priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher value = processed first'),
                                
                                Forms\Components\TextInput::make('user_agent')
                                    ->maxLength(500)
                                    ->placeholder('Leave empty for random user agent')
                                    ->columnSpanFull(),
                            ])->columns(2),
                        
                        Forms\Components\Tabs\Tab::make('Advanced Settings')
                            ->schema([
                                Forms\Components\Toggle::make('use_stealth')
                                    ->label('Enable Stealth Mode')
                                    ->default(true)
                                    ->helperText('Use stealth plugin to avoid bot detection'),
                                
                                Forms\Components\Toggle::make('use_proxy')
                                    ->label('Enable Proxy Rotation')
                                    ->default(false)
                                    ->helperText('Use proxy rotation for requests'),
                                
                                Forms\Components\KeyValue::make('headers')
                                    ->label('Custom Headers')
                                    ->keyLabel('Header Name')
                                    ->valueLabel('Header Value')
                                    ->columnSpanFull(),
                                
                                Forms\Components\KeyValue::make('cookies')
                                    ->label('Pre-set Cookies')
                                    ->keyLabel('Cookie Name')
                                    ->valueLabel('Cookie Value')
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('base_url')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->base_url)
                    ->searchable(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('form_steps_count')
                    ->label('Steps')
                    ->counts('formSteps'),
                
                Tables\Columns\TextColumn::make('pending_entries_count')
                    ->label('Pending')
                    ->badge()
                    ->color('warning'),
                
                Tables\Columns\TextColumn::make('success_entries_count')
                    ->label('Success')
                    ->badge()
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('failed_entries_count')
                    ->label('Failed')
                    ->badge()
                    ->color('danger'),
                
                Tables\Columns\TextColumn::make('concurrency_limit')
                    ->label('Concurrency')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('priority')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('max_jobs_per_minute')
                    ->label('Rate Limit')
                    ->suffix('/min')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(function (Website $record) {
                            $newWebsite = $record->replicate();
                            $newWebsite->name = $record->name . ' (Copy)';
                            $newWebsite->slug = $record->slug . '-copy';

                            // Remove attributes that are not actual columns
                            $virtuals = [
                                'form_steps_count', 'pending_entries_count', 'success_entries_count', 'failed_entries_count',
                            ];
                            foreach ($virtuals as $attr) {
                                unset($newWebsite->$attr);
                            }

                            $newWebsite->push();

                            // Duplicate form steps and fields if exist
                            if (method_exists($record, 'formSteps') && $record->formSteps && $record->formSteps->count()) {
                                foreach ($record->formSteps as $step) {
                                    $newStep = $step->replicate();
                                    $newStep->website_id = $newWebsite->id;
                                    $newStep->push();

                                    if (method_exists($step, 'formFields') && $step->formFields && $step->formFields->count()) {
                                        foreach ($step->formFields as $field) {
                                            $newField = $field->replicate();
                                            $newField->form_step_id = $newStep->id;
                                            $newField->push();
                                        }
                                    }
                                }
                            }
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FormStepsRelationManager::class,
            RelationManagers\DataEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebsites::route('/'),
            'create' => Pages\CreateWebsite::route('/create'),
            'view' => Pages\ViewWebsite::route('/{record}'),
            'edit' => Pages\EditWebsite::route('/{record}/edit'),
        ];
    }
}
