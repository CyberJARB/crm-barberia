<x-filament-panels::page>

    <style>
        .pl-board { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(240px, 1fr); gap: 0.75rem; overflow-x: auto; padding-bottom: 0.5rem; }
        .pl-col { background: rgba(128,128,128,0.06); border: 1px solid rgba(128,128,128,0.18); border-radius: 12px; display: flex; flex-direction: column; min-height: 120px; }
        .pl-col-head { padding: 10px 12px; border-bottom: 1px solid rgba(128,128,128,0.18); }
        .pl-col-title { display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 700; }
        .pl-col-count { font-size: 11px; font-weight: 600; background: rgba(99,102,241,0.15); color: #6366f1; border-radius: 9999px; padding: 1px 8px; }
        .pl-col-sum { font-size: 11px; opacity: 0.7; margin-top: 2px; }
        .pl-cards { padding: 8px; display: flex; flex-direction: column; gap: 8px; }
        .pl-card { background: var(--pl-card-bg, #fff); border: 1px solid rgba(128,128,128,0.22); border-radius: 10px; padding: 8px 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.07); }
        .pl-card .nombre { font-weight: 700; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pl-card .meta { font-size: 11px; opacity: 0.7; margin-top: 2px; display: flex; flex-wrap: wrap; gap: 6px; }
        .pl-card .valor { font-weight: 700; color: #22c55e; }
        .pl-actions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .pl-btn { font-size: 11px; border: 1px solid rgba(128,128,128,0.3); border-radius: 7px; padding: 2px 7px; cursor: pointer; background: transparent; }
        .pl-btn:hover { background: rgba(99,102,241,0.12); }
        .pl-btn.win { border-color: #22c55e; color: #16a34a; }
        .pl-btn.lose { border-color: #ef4444; color: #dc2626; }
        .pl-empty { font-size: 11px; opacity: 0.5; padding: 8px 10px; }
        .dark .pl-card { --pl-card-bg: rgba(255,255,255,0.04); }
    </style>

    @php
        $columnas = $this->columnas();
        $etiquetas = \App\Models\Lead::ESTADOS;
        $fuentes = \App\Models\Lead::FUENTES;
    @endphp

    <div class="pl-board">
        @foreach ($columnas as $estado => $col)
            <div class="pl-col">
                <div class="pl-col-head">
                    <div class="pl-col-title">
                        <span>{{ $col['label'] }}</span>
                        <span class="pl-col-count">{{ $col['total'] }}</span>
                    </div>
                    @if ($col['valor'] > 0)
                        <div class="pl-col-sum">{{ number_format($col['valor'], 2, ',', '.') }} €</div>
                    @endif
                </div>

                <div class="pl-cards">
                    @forelse ($col['leads'] as $lead)
                        <div class="pl-card" wire:key="lead-{{ $lead->id }}">
                            <div class="nombre">{{ $lead->nombre }}</div>
                            <div class="meta">
                                <span>{{ $fuentes[$lead->fuente] ?? $lead->fuente }}</span>
                                @if ($lead->valor_estimado)
                                    <span class="valor">{{ number_format((float) $lead->valor_estimado, 2, ',', '.') }} €</span>
                                @endif
                                @if ($lead->responsable)
                                    <span>· {{ $lead->responsable->name }}</span>
                                @endif
                            </div>

                            <div class="pl-actions">
                                @foreach (\App\Models\Lead::TRANSICIONES[$estado] ?? [] as $destino)
                                    <button
                                        type="button"
                                        class="pl-btn {{ $destino === 'ganado' ? 'win' : ($destino === 'perdido' ? 'lose' : '') }}"
                                        wire:click="mover({{ $lead->id }}, '{{ $destino }}')"
                                        wire:confirm="{{ $destino === 'ganado' ? 'Lead als gewonnen markieren und Kunde anlegen?' : '' }}"
                                    >
                                        {{ $etiquetas[$destino] ?? $destino }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="pl-empty">—</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

</x-filament-panels::page>
