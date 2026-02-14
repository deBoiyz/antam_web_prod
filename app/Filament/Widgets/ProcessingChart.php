<?php

namespace App\Filament\Widgets;

use App\Models\DataEntry;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class ProcessingChart extends ChartWidget
{
    protected static ?string $heading = 'Processing Statistics';

    protected static ?int $sort = 2;

    protected static string $color = 'primary';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'today';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Today',
            'week' => 'Last 7 days',
            'month' => 'Last 30 days',
        ];
    }

    protected function getData(): array
    {
        $filter = $this->filter;
        
        $startDate = match ($filter) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7),
            'month' => now()->subDays(30),
            default => now()->startOfDay(),
        };

        $successData = DataEntry::query()
            ->where('status', 'success')
            ->where('updated_at', '>=', $startDate)
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $failedData = DataEntry::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', $startDate)
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $labels = [];
        $successValues = [];
        $failedValues = [];
        
        $current = $startDate->copy();
        while ($current <= now()) {
            $date = $current->format('Y-m-d');
            $labels[] = $current->format('M d');
            $successValues[] = $successData[$date] ?? 0;
            $failedValues[] = $failedData[$date] ?? 0;
            $current->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Success',
                    'data' => $successValues,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
                [
                    'label' => 'Failed',
                    'data' => $failedValues,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.5)',
                    'borderColor' => 'rgb(239, 68, 68)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
