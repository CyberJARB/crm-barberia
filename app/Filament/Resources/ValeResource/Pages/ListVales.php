<?php

namespace App\Filament\Resources\ValeResource\Pages;

use App\Filament\Resources\ValeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVales extends ListRecords
{
    protected static string $resource = ValeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Gutschein verkaufen')->icon('heroicon-m-gift'),
        ];
    }
}
