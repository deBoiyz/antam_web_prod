<?php

namespace App\Filament\Resources\FormStepResource\Pages;

use App\Filament\Resources\FormStepResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFormStep extends CreateRecord
{
    protected static string $resource = FormStepResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
