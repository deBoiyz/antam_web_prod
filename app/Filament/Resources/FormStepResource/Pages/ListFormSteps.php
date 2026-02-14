<?php

namespace App\Filament\Resources\FormStepResource\Pages;

use App\Filament\Resources\FormStepResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListFormSteps extends ListRecords
{
    protected static string $resource = FormStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
