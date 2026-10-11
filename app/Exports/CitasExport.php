<?php

namespace App\Exports;

use App\Models\Cita;
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
 * Terminbericht — citas del tenant en el rango de fechas.
 *
 * OWASP A01 — filtro explícito por tenant_id (defensa en profundidad).
 */
class CitasExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly int $tenantId,
        private readonly string $fechaInicio,
        private readonly string $fechaHasta,
    ) {}

    public function query(): Builder
    {
        return Cita::query()
            ->with(['cliente', 'personal', 'servicios'])
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('fecha', [$this->fechaInicio, $this->fechaHasta])
            ->orderBy('fecha')
            ->orderBy('hora_inicio');
    }

    public function headings(): array
    {
        return [
            'Datum', 'Uhrzeit', 'Kunde', 'Mitarbeiter',
            'Status', 'Dienstleistungen', 'Betrag (€)', 'Notizen',
        ];
    }

    /**
     * @param  Cita  $row
     */
    public function map($row): array
    {
        return [
            ReporteTexto::fecha($row->fecha),
            substr((string) $row->hora_inicio, 0, 5),
            $row->cliente?->nombre ?? '—',
            $row->personal?->nombre ?? '—',
            ReporteTexto::estado($row->estado),
            $row->servicios->pluck('nombre')->implode(', ') ?: '—',
            ReporteTexto::euro($row->total),
            $row->notas ?? '',
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
