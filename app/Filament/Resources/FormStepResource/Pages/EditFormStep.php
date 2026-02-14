<?php

namespace App\Filament\Resources\FormStepResource\Pages;

use App\Filament\Resources\FormStepResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormStep extends EditRecord
{
    protected static string $resource = FormStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
