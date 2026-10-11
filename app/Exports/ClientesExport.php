<?php

namespace App\Exports;

use App\Models\Cliente;
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
 * Kundenliste — todos los clientes activos del tenant (sin filtro de fechas).
 *
 * OWASP A01 — filtro explícito por tenant_id.
 * GDPR: solo exportable por administradores (verificado en el controller).
 */
class ClientesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly int $tenantId,
    ) {}

    public function query(): Builder
    {
        return Cliente::query()
            ->where('tenant_id', $this->tenantId)
            ->where('activo', true)
            ->orderBy('nombre');
    }

    public function headings(): array
    {
        return [
            'Name', 'E-Mail', 'Telefon', 'Geburtsdatum',
            'Gesamtbesuche', 'Letzter Besuch', 'Registriert am',
        ];
    }

    /**
     * @param  Cliente  $row
     */
    public function map($row): array
    {
        return [
            $row->nombre,
            $row->email ?? '—',
            $row->telefono ?? '—',
            ReporteTexto::fecha($row->fecha_nacimiento),
            (int) $row->total_visitas,
            ReporteTexto::fecha($row->ultima_visita),
            ReporteTexto::fecha($row->created_at),
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
