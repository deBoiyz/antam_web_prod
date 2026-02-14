<?php

namespace App\Filament\Resources\JobLogResource\Pages;

use App\Filament\Resources\JobLogResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewJobLog extends ViewRecord
{
    protected static string $resource = JobLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Log Information')
                    ->schema([
                        Components\TextEntry::make('website.name'),
                        Components\TextEntry::make('dataEntry.identifier')
                            ->label('Entry Identifier'),
                        Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'started' => 'info',
                                'step_completed' => 'warning',
                                'success' => 'success',
                                'failed' => 'danger',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),
                        Components\TextEntry::make('executed_at')
                            ->dateTime(),
                    ])->columns(4),
                
                Components\Section::make('Step Information')
                    ->schema([
                        Components\TextEntry::make('step_number')
                            ->placeholder('-'),
                        Components\TextEntry::make('step_name')
                            ->placeholder('-'),
                        Components\TextEntry::make('duration_ms')
                            ->label('Duration')
                            ->suffix(' ms')
                            ->placeholder('-'),
                        Components\TextEntry::make('browser_session_id')
                            ->label('Session ID')
                            ->placeholder('-'),
                    ])->columns(4),
                
                Components\Section::make('Message')
                    ->schema([
                        Components\TextEntry::make('message')
                            ->columnSpanFull(),
                    ]),
                
                Components\Section::make('Details')
                    ->schema([
                        Components\KeyValueEntry::make('details')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->details)),
                
                Components\Section::make('Screenshot')
                    ->schema([
                        Components\ImageEntry::make('screenshot_path')
                            ->disk('public'),
                    ])
                    ->visible(fn ($record) => !empty($record->screenshot_path)),
            ]);
    }
}
