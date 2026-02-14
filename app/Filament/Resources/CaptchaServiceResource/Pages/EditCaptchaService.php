<?php

namespace App\Filament\Resources\CaptchaServiceResource\Pages;

use App\Filament\Resources\CaptchaServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCaptchaService extends EditRecord
{
    protected static string $resource = CaptchaServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
