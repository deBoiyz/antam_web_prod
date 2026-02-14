<?php

namespace App\Filament\Resources\CaptchaServiceResource\Pages;

use App\Filament\Resources\CaptchaServiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCaptchaServices extends ListRecords
{
    protected static string $resource = CaptchaServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
