<?php

namespace App\Filament\Super\Resources\TenantResource\Pages;

use App\Filament\Super\Resources\TenantResource;
use App\Filament\Super\Widgets\TenantUsoWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TenantUsoWidget::make(['record' => $this->getRecord()]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
