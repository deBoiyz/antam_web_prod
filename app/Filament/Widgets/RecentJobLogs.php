<?php

namespace App\Filament\Widgets;

use App\Models\JobLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentJobLogs extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobLog::query()
                    ->with(['website', 'dataEntry'])
                    ->latest('executed_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('executed_at')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('website.name')
                    ->limit(15),
                
                Tables\Columns\TextColumn::make('dataEntry.identifier')
                    ->label('Entry')
                    ->limit(15),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'started' => 'info',
                        'step_completed' => 'warning',
                        'success' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('step_name')
                    ->placeholder('-')
                    ->limit(20),
                
                Tables\Columns\TextColumn::make('message')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->message),
                
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->suffix(' ms')
                    ->placeholder('-'),
            ])
            ->paginated(false)
            ->poll('5s');
    }
}
