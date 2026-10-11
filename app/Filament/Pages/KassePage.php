<?php

namespace App\Filament\Pages;

use App\Models\Cita;
use App\Models\Pago;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tagesabschluss (/admin/kasse) — cierre de caja diario.
 *
 * Desglose de ingresos por método de pago + actividad de citas del día.
 * OWASP A01 — todos los queries acotados explícitamente por tenant_id
 * además del global scope (defensa en profundidad).
 *
 * @property-read Collection<int, Pago> $pagos Computed property de Livewire (getPagosProperty)
 */
class KassePage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';

    protected string $view = 'filament.pages.kasse';

    protected static ?string $title = 'Tagesabschluss';

    protected static ?string $slug = 'kasse';

    protected static ?int $navigationSort = 7;

    /** Día mostrado (Y-m-d). */
    public string $fecha = '';

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
    }

    public function diaAnterior(): void
    {
        $this->fecha = $this->dia()->subDay()->toDateString();
    }

    public function diaSiguiente(): void
    {
        $siguiente = $this->dia()->addDay();

        // No tiene sentido cerrar caja de días futuros
        if (! $siguiente->isFuture()) {
            $this->fecha = $siguiente->toDateString();
        }
    }

    public function hoy(): void
    {
        $this->fecha = now()->toDateString();
    }

    public function dia(): Carbon
    {
        // CWE-20 — la propiedad viaja por Livewire: re-validar siempre
        try {
            $dia = Carbon::createFromFormat('Y-m-d', $this->fecha)->startOfDay();

            return $dia->isFuture() ? now()->startOfDay() : $dia;
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    /** @return Collection<int, Pago> */
    public function getPagosProperty(): Collection
    {
        return Pago::query()
            ->where('tenant_id', (int) auth()->user()->tenant_id)
            ->whereDate('created_at', $this->dia())
            ->with(['cliente', 'cita'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Desglose por método: ['efectivo' => ['total' => 120.0, 'cantidad' => 4], ...]
     *
     * @return array<string, array{total: float, cantidad: int}>
     */
    public function getResumenProperty(): array
    {
        return $this->pagos
            ->groupBy('metodo')
            ->map(fn (Collection $grupo): array => [
                'total' => (float) $grupo->sum('monto'),
                'cantidad' => $grupo->count(),
            ])
            ->all();
    }

    public function getTotalDiaProperty(): float
    {
        return (float) $this->pagos->sum('monto');
    }

    /**
     * Actividad de citas del día por estado.
     *
     * @return array<string, int>
     */
    public function getCitasStatsProperty(): array
    {
        return Cita::query()
            ->where('tenant_id', (int) auth()->user()->tenant_id)
            ->whereDate('fecha', $this->dia())
            ->selectRaw('estado, COUNT(*) as n')
            ->groupBy('estado')
            ->pluck('n', 'estado')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    public function getGutscheineVendidosProperty(): int
    {
        return $this->pagos->where('notas', 'Gutscheinverkauf')->count();
    }

    public function getProductosVendidosProperty(): float
    {
        return (float) $this->pagos->where('notas', 'Produktverkauf')->sum('monto');
    }
}
