<?php

namespace App\Filament\Resources\ProxyResource\Pages;

use App\Filament\Resources\ProxyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProxy extends EditRecord
{
    protected static string $resource = ProxyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
