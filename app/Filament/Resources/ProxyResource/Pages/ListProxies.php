<?php

namespace App\Filament\Resources\ProxyResource\Pages;

use App\Filament\Resources\ProxyResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListProxies extends ListRecords
{
    protected static string $resource = ProxyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
