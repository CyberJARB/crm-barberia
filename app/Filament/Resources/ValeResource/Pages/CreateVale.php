<?php

namespace App\Filament\Resources\ValeResource\Pages;

use App\Filament\Resources\ValeResource;
use App\Models\Pago;
use App\Models\Vale;
use App\Support\ReporteTexto;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateVale extends CreateRecord
{
    protected static string $resource = ValeResource::class;

    protected ?string $metodoVenta = null;

    /**
     * Separa el método de pago de la venta (no es columna del vale) y
     * fija el saldo server-side = valor inicial (CWE-602: nunca del cliente).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->metodoVenta = $data['metodo_venta'] ?? 'efectivo';
        unset($data['metodo_venta'], $data['saldo'], $data['codigo']);

        return $data;
    }

    /**
     * La venta del vale es una entrada de caja: se registra como pago
     * para que cuadre el Tagesumsatz y los reportes.
     */
    protected function afterCreate(): void
    {
        /** @var Vale $vale */
        $vale = $this->record;

        Pago::create([
            'tenant_id' => $vale->tenant_id,
            'cliente_id' => $vale->cliente_id,
            'vale_id' => $vale->id,
            'monto' => $vale->valor_inicial,
            'metodo' => $this->metodoVenta,
            'referencia' => $vale->codigo,
            'notas' => 'Gutscheinverkauf',
        ]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var Vale $vale */
        $vale = $this->record;

        return Notification::make()
            ->success()
            ->title('Gutschein verkauft')
            ->body("Code: {$vale->codigo} — ".ReporteTexto::euro($vale->valor_inicial))
            ->persistent();
    }
}
