<?php

namespace App\Exports;

use App\Models\Personal;
use App\Support\ReporteTexto;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Mitarbeiter-Leistung — citas completadas/canceladas e ingresos
 * generados por empleado en el período, ordenado por Umsatz desc.
 *
 * OWASP A01 — filtro explícito por tenant_id.
 */
class PersonalExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly int $tenantId,
        private readonly string $fechaInicio,
        private readonly string $fechaHasta,
    ) {}

    /**
     * Datos agregados por empleado. Reutilizado también por el PDF
     * (ReporteController) para no duplicar la lógica de agregación.
     *
     * @return Collection<int, array{mitarbeiter: string, completadas: int, canceladas: int, umsatz: float, promedio: float}>
     */
    public function collection(): Collection
    {
        $rango = fn ($query) => $query->whereBetween('fecha', [$this->fechaInicio, $this->fechaHasta]);

        return Personal::query()
            ->where('tenant_id', $this->tenantId)
            ->withCount([
                'citas as completadas' => fn ($q) => $rango($q)->where('estado', 'completada'),
                'citas as canceladas' => fn ($q) => $rango($q)->where('estado', 'cancelada'),
            ])
            ->withSum(
                ['citas as umsatz' => fn ($q) => $rango($q)->where('estado', 'completada')],
                'total'
            )
            ->get()
            ->map(fn (Personal $personal) => [
                'mitarbeiter' => $personal->nombre,
                'completadas' => (int) $personal->completadas,
                'canceladas' => (int) $personal->canceladas,
                'umsatz' => (float) ($personal->umsatz ?? 0),
                'promedio' => $personal->completadas > 0
                    ? round((float) ($personal->umsatz ?? 0) / $personal->completadas, 2)
                    : 0.0,
            ])
            ->sortByDesc('umsatz')
            ->values();
    }

    public function headings(): array
    {
        return [
            'Mitarbeiter', 'Abgeschlossene Termine', 'Abgesagte Termine',
            'Umsatz (€)', 'Durchschnitt (€/Termin)',
        ];
    }

    /**
     * @param  array{mitarbeiter: string, completadas: int, canceladas: int, umsatz: float, promedio: float}  $row
     */
    public function map($row): array
    {
        return [
            $row['mitarbeiter'],
            $row['completadas'],
            $row['canceladas'],
            ReporteTexto::euro($row['umsatz']),
            ReporteTexto::euro($row['promedio']),
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
