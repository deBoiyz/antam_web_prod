<?php

namespace App\Filament\Resources\BotSessionResource\Pages;

use App\Filament\Resources\BotSessionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewBotSession extends ViewRecord
{
    protected static string $resource = BotSessionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Session Information')
                    ->schema([
                        Components\TextEntry::make('session_id'),
                        Components\TextEntry::make('website.name')
                            ->placeholder('All Websites'),
                        Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'idle' => 'gray',
                                'running' => 'success',
                                'paused' => 'warning',
                                'stopped' => 'gray',
                                'error' => 'danger',
                                default => 'gray',
                            }),
                        Components\TextEntry::make('current_job_id')
                            ->placeholder('None'),
                    ])->columns(4),
                
                Components\Section::make('Statistics')
                    ->schema([
                        Components\TextEntry::make('processed_count')
                            ->label('Total Processed'),
                        Components\TextEntry::make('success_count')
                            ->label('Successful'),
                        Components\TextEntry::make('failure_count')
                            ->label('Failed'),
                        Components\TextEntry::make('success_rate')
                            ->label('Success Rate')
                            ->suffix('%'),
                    ])->columns(4),
                
                Components\Section::make('Timing')
                    ->schema([
                        Components\TextEntry::make('started_at')
                            ->dateTime(),
                        Components\TextEntry::make('last_activity_at')
                            ->dateTime(),
                        Components\TextEntry::make('uptime'),
                    ])->columns(3),
                
                Components\Section::make('Worker Information')
                    ->schema([
                        Components\TextEntry::make('worker_hostname'),
                        Components\TextEntry::make('worker_pid')
                            ->label('Process ID'),
                    ])->columns(2),
                
                Components\Section::make('System Info')
                    ->schema([
                        Components\KeyValueEntry::make('system_info'),
                    ])
                    ->visible(fn ($record) => !empty($record->system_info)),
            ]);
    }
}
