<?php

namespace App\Filament\Resources\ListaEsperaResource\Pages;

use App\Filament\Resources\ListaEsperaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListListaEspera extends ListRecords
{
    protected static string $resource = ListaEsperaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Kunde eintragen')->icon('heroicon-m-plus'),
        ];
    }
}
