<?php

namespace App\Filament\Widgets;

use App\Models\DataEntry;
use App\Models\Website;
use App\Models\BotSession;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalEntries = DataEntry::count();
        $pendingEntries = DataEntry::pending()->count();
        $processingEntries = DataEntry::whereIn('status', ['queued', 'processing'])->count();
        $successEntries = DataEntry::success()->count();
        $failedEntries = DataEntry::failed()->count();
        
        $activeBots = BotSession::running()->count();
        $totalWebsites = Website::where('is_active', true)->count();
        
        $successRate = $totalEntries > 0 
            ? round(($successEntries / $totalEntries) * 100, 1) 
            : 0;

        return [
            Stat::make('Active Websites', $totalWebsites)
                ->description('Configured targets')
                ->icon('heroicon-o-globe-alt')
                ->color('primary'),
            
            Stat::make('Active Bots', $activeBots)
                ->description('Running workers')
                ->icon('heroicon-o-cpu-chip')
                ->color($activeBots > 0 ? 'success' : 'gray'),
            
            Stat::make('Pending', $pendingEntries)
                ->description('Waiting to process')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            
            Stat::make('Processing', $processingEntries)
                ->description('In queue/running')
                ->icon('heroicon-o-arrow-path')
                ->color('info'),
            
            Stat::make('Success', $successEntries)
                ->description("{$successRate}% success rate")
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            
            Stat::make('Failed', $failedEntries)
                ->description('Need attention')
                ->icon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}
