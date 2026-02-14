<?php

namespace App\Filament\Resources\DataEntryResource\Pages;

use App\Filament\Resources\DataEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDataEntry extends CreateRecord
{
    protected static string $resource = DataEntryResource::class;

    protected function getRedirectUrl(): string
    {
        $websiteId = $this->record->website_id ?? null;

        if ($websiteId) {
            return DataEntryResource::getUrl('website-entries', ['websiteId' => $websiteId]);
        }

        return DataEntryResource::getUrl();
    }
}
