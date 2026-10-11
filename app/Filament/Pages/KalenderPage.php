<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CitaResource;
use App\Models\Cita;
use App\Models\Personal;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Wochenkalender (/admin/kalender) — vista operativa de la agenda.
 *
 * OWASP A01 — todos los queries acotados explícitamente por tenant_id
 * además del global scope (defensa en profundidad). El filtro de
 * Mitarbeiter se valida contra los empleados del propio tenant.
 */
class KalenderPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected string $view = 'filament.pages.kalender';

    protected static ?string $title = 'Kalender';

    protected static ?string $slug = 'kalender';

    protected static ?int $navigationSort = 0;

    /** Lunes de la semana mostrada (Y-m-d). */
    public string $semana = '';

    /** Filtro de empleado: '' = todos. */
    public string $personalId = '';

    /** Horario visible del grid. */
    public const HORA_DESDE = 8;

    public const HORA_HASTA = 21;

    public function mount(): void
    {
        $this->semana = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function semanaAnterior(): void
    {
        $this->semana = $this->lunes()->subWeek()->toDateString();
    }

    public function semanaSiguiente(): void
    {
        $this->semana = $this->lunes()->addWeek()->toDateString();
    }

    public function semanaActual(): void
    {
        $this->semana = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function lunes(): Carbon
    {
        // CWE-20 — la propiedad viaja por Livewire: re-validar siempre
        try {
            return Carbon::createFromFormat('Y-m-d', $this->semana)->startOfWeek(Carbon::MONDAY);
        } catch (\Throwable) {
            return now()->startOfWeek(Carbon::MONDAY);
        }
    }

    /** @return Collection<int, Personal> */
    public function getEmpleadosProperty(): Collection
    {
        return Personal::query()
            ->where('tenant_id', (int) auth()->user()->tenant_id)
            ->activos()
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Citas de la semana agrupadas por fecha (Y-m-d), con metadatos de
     * posición para el grid (top/height en minutos desde HORA_DESDE).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getCitasSemanaProperty(): array
    {
        $lunes = $this->lunes();
        $tenantId = (int) auth()->user()->tenant_id;

        // OWASP A01 — filtro de empleado validado contra el tenant propio
        $personalIdValido = null;
        if ($this->personalId !== '' && ctype_digit($this->personalId)) {
            $personalIdValido = $this->getEmpleadosProperty()->firstWhere('id', (int) $this->personalId)?->id;
        }

        $citas = Cita::query()
            ->with(['cliente', 'personal', 'servicios'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('fecha', [$lunes->toDateString(), $lunes->copy()->addDays(6)->toDateString()])
            ->when($personalIdValido !== null, fn ($q) => $q->where('personal_id', $personalIdValido))
            ->orderBy('hora_inicio')
            ->get();

        $minutosVisibles = (self::HORA_HASTA - self::HORA_DESDE) * 60;
        $porDia = [];

        foreach ($citas as $cita) {
            $inicio = Carbon::parse($cita->hora_inicio);
            $fin = $cita->hora_fin
                ? Carbon::parse($cita->hora_fin)
                : $inicio->copy()->addMinutes(30);

            $desdeApertura = ($inicio->hour - self::HORA_DESDE) * 60 + $inicio->minute;
            $duracion = max(20, $inicio->diffInMinutes($fin)); // mínimo visible

            // Recortar al rango visible del grid
            $desdeApertura = max(0, min($desdeApertura, $minutosVisibles - 20));
            $duracion = min($duracion, $minutosVisibles - $desdeApertura);

            $porDia[$cita->fecha->toDateString()][] = [
                'id' => $cita->id,
                'cliente' => $cita->cliente?->nombre ?? '—',
                'personal' => $cita->personal?->nombre ?? '—',
                'servicios' => $cita->servicios->pluck('nombre')->implode(', '),
                'hora' => $inicio->format('H:i').' – '.$fin->format('H:i'),
                'estado' => $cita->estado,
                'color' => $cita->personal?->color ?: '#6366f1',
                'top' => round($desdeApertura / $minutosVisibles * 100, 3),
                'height' => round($duracion / $minutosVisibles * 100, 3),
                'url' => CitaResource::getUrl('edit', ['record' => $cita]),
            ];
        }

        return $porDia;
    }

    /** @return array<int, Carbon> Los 7 días de la semana mostrada. */
    public function getDiasProperty(): array
    {
        $lunes = $this->lunes();

        return array_map(fn (int $i) => $lunes->copy()->addDays($i), range(0, 6));
    }
}
