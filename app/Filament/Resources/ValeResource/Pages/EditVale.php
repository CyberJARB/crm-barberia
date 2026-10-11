<?php

namespace App\Filament\Resources\ValeResource\Pages;

use App\Filament\Resources\ValeResource;
use Filament\Resources\Pages\EditRecord;

class EditVale extends EditRecord
{
    protected static string $resource = ValeResource::class;

    /**
     * Defensa en profundidad: aunque el form deshabilita estos campos,
     * un request manipulado tampoco debe poder tocarlos (CWE-602).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['codigo'], $data['saldo'], $data['valor_inicial']);

        return $data;
    }
}
