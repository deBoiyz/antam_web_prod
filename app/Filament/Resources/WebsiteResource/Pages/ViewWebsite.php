<?php

namespace App\Filament\Resources\WebsiteResource\Pages;

use App\Filament\Resources\WebsiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewWebsite extends ViewRecord
{
    protected static string $resource = WebsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Basic Information')
                    ->schema([
                        Components\TextEntry::make('name'),
                        Components\TextEntry::make('slug'),
                        Components\TextEntry::make('base_url')
                            ->url(fn ($record) => $record->base_url)
                            ->openUrlInNewTab(),
                        Components\TextEntry::make('description'),
                        Components\IconEntry::make('is_active')
                            ->boolean(),
                    ])
                    ->columns(2)
                    ->collapsible(true),
                
                Components\Section::make('Statistics')
                    ->schema([
                        Components\TextEntry::make('pending_entries_count')
                            ->label('Pending Entries')
                            ->badge()
                            ->color('warning'),
                        Components\TextEntry::make('processing_entries_count')
                            ->label('Processing')
                            ->badge()
                            ->color('info'),
                        Components\TextEntry::make('success_entries_count')
                            ->label('Successful')
                            ->badge()
                            ->color('success'),
                        Components\TextEntry::make('failed_entries_count')
                            ->label('Failed')
                            ->badge()
                            ->color('danger'),
                    ])->columns(4)
                    ->collapsible(true),
                
                Components\Section::make('Request Settings')
                    ->schema([
                        Components\TextEntry::make('timeout')
                            ->suffix(' ms'),
                        Components\TextEntry::make('retry_attempts'),
                        Components\TextEntry::make('retry_delay')
                            ->suffix(' ms'),
                        Components\TextEntry::make('concurrency_limit'),
                        Components\TextEntry::make('max_jobs_per_minute')
                            ->label('Max Jobs Per Minute')
                            ->suffix('/min'),
                        Components\TextEntry::make('priority'),
                        Components\IconEntry::make('use_stealth')
                            ->label('Stealth Mode')
                            ->boolean(),
                        Components\IconEntry::make('use_proxy')
                            ->label('Proxy Rotation')
                            ->boolean(),
                    ])->columns(3)
                    ->collapsible(true),
            ]);
    }
}
