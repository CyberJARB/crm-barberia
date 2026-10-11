<?php

namespace App\Exports;

use App\Models\Pago;
use App\Support\ReporteTexto;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Umsatzbericht — pagos del tenant en el rango de fechas.
 *
 * OWASP A01 — filtra SIEMPRE por tenant_id explícito (no depende
 * del global scope, mismo patrón que los widgets del dashboard).
 */
class IngresosExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly int $tenantId,
        private readonly string $fechaInicio,
        private readonly string $fechaHasta,
    ) {}

    public function query(): Builder
    {
        return Pago::query()
            ->with('cliente')
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('created_at', [
                $this->fechaInicio.' 00:00:00',
                $this->fechaHasta.' 23:59:59',
            ])
            ->orderBy('created_at');
    }

    public function headings(): array
    {
        return ['Datum', 'Kunde', 'Methode', 'Betrag (€)', 'Referenz'];
    }

    /**
     * @param  Pago  $row
     */
    public function map($row): array
    {
        return [
            $row->created_at?->format('d.m.Y H:i') ?? '—',
            $row->cliente?->nombre ?? '—',
            ReporteTexto::metodo($row->metodo),
            ReporteTexto::euro($row->monto),
            $row->referencia ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
            ],
        ];
    }
}
