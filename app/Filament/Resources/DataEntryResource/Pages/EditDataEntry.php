<?php

namespace App\Filament\Resources\DataEntryResource\Pages;

use App\Filament\Resources\DataEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDataEntry extends EditRecord
{
    protected static string $resource = DataEntryResource::class;

    public function getBreadcrumbs(): array
    {
        $websiteId = $this->record->website_id ?? null;

        if ($websiteId) {
            return [
                DataEntryResource::getUrl() => 'Data Entries',
                DataEntryResource::getUrl('website-entries', ['websiteId' => $websiteId]) => $this->record->website->name ?? 'Website',
                '' => 'Edit Entry',
            ];
        }

        return parent::getBreadcrumbs();
    }

    protected function getHeaderActions(): array
    {
        $websiteId = $this->record->website_id ?? null;
        
        $actions = [];
        
        if ($websiteId) {
            $actions[] = Actions\Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DataEntryResource::getUrl('website-entries', ['websiteId' => $websiteId]));
        }
        
        $actions[] = Actions\ViewAction::make();
        $actions[] = Actions\DeleteAction::make();
        
        return $actions;
    }

    protected function getRedirectUrl(): string
    {
        $websiteId = $this->record->website_id ?? null;

        if ($websiteId) {
            return DataEntryResource::getUrl('website-entries', ['websiteId' => $websiteId]);
        }

        return DataEntryResource::getUrl();
    }
}
