<?php

namespace App\Filament\Resources\JobLogResource\Pages;

use App\Filament\Resources\JobLogResource;
use Filament\Resources\Pages\ListRecords;

class ListJobLogs extends ListRecords
{
    protected static string $resource = JobLogResource::class;
}
