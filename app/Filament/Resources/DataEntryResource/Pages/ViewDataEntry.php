<?php

namespace App\Filament\Resources\DataEntryResource\Pages;

use App\Filament\Resources\DataEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewDataEntry extends ViewRecord
{
    protected static string $resource = DataEntryResource::class;

    public function getBreadcrumbs(): array
    {
        $websiteId = $this->record->website_id ?? null;

        if ($websiteId) {
            return [
                DataEntryResource::getUrl() => 'Data Entries',
                DataEntryResource::getUrl('website-entries', ['websiteId' => $websiteId]) => $this->record->website->name ?? 'Website',
                '' => 'View Entry',
            ];
        }

        return parent::getBreadcrumbs();
    }

    protected function getHeaderActions(): array
    {
        $websiteId = $this->record->website_id ?? null;
        
        $actions = [];
        
        if ($websiteId) {
            $actions[] = Actions\Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DataEntryResource::getUrl('website-entries', ['websiteId' => $websiteId]));
        }
        
        $actions[] = Actions\EditAction::make();
        
        return $actions;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Entry Information')
                    ->schema([
                        Components\TextEntry::make('website.name'),
                        Components\TextEntry::make('identifier'),
                        Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'gray',
                                'queued' => 'info',
                                'processing' => 'warning',
                                'success' => 'success',
                                'failed' => 'danger',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),
                        Components\TextEntry::make('priority'),
                    ])->columns(4),
                
                Components\Section::make('Form Data')
                    ->schema([
                        Components\KeyValueEntry::make('data'),
                    ]),
                
                Components\Section::make('Processing Info')
                    ->schema([
                        Components\TextEntry::make('attempts')
                            ->formatStateUsing(fn ($record) => "{$record->attempts}/{$record->max_attempts}"),
                        Components\TextEntry::make('last_attempt_at')
                            ->dateTime(),
                        Components\TextEntry::make('scheduled_at')
                            ->dateTime(),
                        Components\TextEntry::make('proxy.name')
                            ->placeholder('None'),
                    ])->columns(4),
                
                Components\Section::make('Results')
                    ->schema([
                        Components\TextEntry::make('result_message')
                            ->columnSpanFull(),
                        Components\KeyValueEntry::make('result_data')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->status === 'success'),
                
                Components\Section::make('Error Information')
                    ->schema([
                        Components\TextEntry::make('error_message')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->error_message)),
                
                Components\Section::make('Screenshot')
                    ->schema([
                        Components\ImageEntry::make('screenshot_path')
                            ->disk('public'),
                    ])
                    ->visible(fn ($record) => !empty($record->screenshot_path)),
            ]);
    }
}
