<?php

namespace App\Filament\Pages;

use App\Models\Lead;
use App\Services\Leads;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Pipeline (/admin/pipeline) — vista Kanban del embudo de leads.
 *
 * OWASP A01 — todos los queries acotados explícitamente por tenant_id además
 * del global scope (defensa en profundidad, igual que KalenderPage). Cada
 * movimiento se valida contra la Policy y la máquina de estados (CWE-841).
 */
class PipelinePage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-view-columns';

    protected string $view = 'filament.pages.pipeline';

    protected static ?string $title = 'Pipeline';

    protected static ?string $slug = 'pipeline';

    protected static \UnitEnum|string|null $navigationGroup = 'Vertrieb';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Lead::class) ?? false;
    }

    /**
     * Leads abiertos + cerrados recientes, agrupados por columna del embudo.
     *
     * @return array<string, array{label: string, leads: Collection<int, Lead>, total: int, valor: float}>
     */
    public function columnas(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $leads = Lead::query()
            ->where('tenant_id', $tenantId)
            ->with('responsable')
            ->where(fn ($q) => $q->whereNotIn('estado', ['ganado', 'perdido'])
                ->orWhere('cerrado_en', '>=', now()->subDays(14)))
            ->orderByDesc('created_at')
            ->get();

        $resultado = [];
        foreach (Lead::ESTADOS as $estado => $label) {
            $delEstado = $leads->where('estado', $estado)->values();
            $resultado[$estado] = [
                'label' => $label,
                'leads' => $delEstado,
                'total' => $delEstado->count(),
                'valor' => (float) $delEstado->sum('valor_estimado'),
            ];
        }

        return $resultado;
    }

    /** Mueve un lead a otra columna respetando Policy + transiciones válidas. */
    public function mover(int $leadId, string $destino): void
    {
        $lead = Lead::where('tenant_id', auth()->user()->tenant_id)->findOrFail($leadId);

        $this->authorize('update', $lead);

        try {
            $service = app(Leads::class);
            $service->transicionar($lead, $destino);

            if ($destino === 'ganado') {
                $service->convertir($lead);
                Notification::make()->title('Lead gewonnen — Kunde wurde angelegt')->success()->send();
            } else {
                Notification::make()->title('Lead verschoben: '.(Lead::ESTADOS[$destino] ?? $destino))->success()->send();
            }
        } catch (\DomainException $e) {
            Notification::make()->title('Übergang nicht erlaubt')->danger()->send();
        }
    }
}
