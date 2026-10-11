<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Pages\PipelinePage;
use App\Filament\Resources\LeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pipeline')
                ->label('Pipeline-Ansicht')
                ->icon('heroicon-m-view-columns')
                ->color('gray')
                ->url(fn (): string => PipelinePage::getUrl()),
            Actions\CreateAction::make()->label('Lead erfassen')->icon('heroicon-m-plus'),
        ];
    }
}
