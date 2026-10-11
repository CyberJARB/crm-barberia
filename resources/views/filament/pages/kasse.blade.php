<x-filament-panels::page>

    <style>
        .kas-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; }
        .kas-nav { display: flex; align-items: center; gap: 0.5rem; }
        .kas-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; }
        .kas-card { border: 1px solid rgba(128,128,128,0.25); border-radius: 12px; padding: 16px; }
        .kas-card .titulo { font-size: 12px; font-weight: 600; opacity: 0.65; display: flex; align-items: center; gap: 6px; }
        .kas-card .valor { font-size: 24px; font-weight: 800; margin-top: 4px; }
        .kas-card .detalle { font-size: 11px; opacity: 0.6; margin-top: 2px; }
        .kas-card.destacada { background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(34,197,94,0.10)); border-color: rgba(99,102,241,0.45); }
        .kas-tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
        .kas-tabla th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; opacity: 0.6; padding: 8px 12px; border-bottom: 1px solid rgba(128,128,128,0.25); }
        .kas-tabla td { padding: 8px 12px; border-bottom: 1px solid rgba(128,128,128,0.12); }
        .kas-tabla tr:last-child td { border-bottom: none; }
        .kas-tabla .monto { text-align: right; font-weight: 700; white-space: nowrap; }
        .kas-badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: rgba(128,128,128,0.15); }
        .kas-vacio { text-align: center; padding: 40px 16px; opacity: 0.6; font-size: 14px; }
        @media print {
            .fi-sidebar, .fi-topbar, .kas-nav, .fi-header { display: none !important; }
        }
    </style>

    @php
        $metodoIcono = [
            'efectivo'      => '💶',
            'tarjeta'       => '💳',
            'transferencia' => '🏦',
            'bizum'         => '📲',
            'vale'          => '🎁',
        ];
        $stats = $this->citasStats;
    @endphp

    {{-- Barra de navegación de día --}}
    <div class="kas-toolbar">
        <div class="kas-nav">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="diaAnterior" />
            <x-filament::button color="gray" size="sm" wire:click="hoy">Heute</x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="diaSiguiente" />

            <span class="text-sm font-semibold text-gray-950 dark:text-white" style="margin-left: 0.5rem;">
                {{ $this->dia()->locale('de')->isoFormat('dddd, DD.MM.YYYY') }}
                @if($this->dia()->isToday())
                    <span style="opacity: 0.6; font-weight: 400;">(heute)</span>
                @endif
            </span>
        </div>

        <div class="kas-nav">
            <x-filament::button color="gray" icon="heroicon-m-printer" onclick="window.print()">
                Drucken
            </x-filament::button>
        </div>
    </div>

    {{-- Tarjetas de resumen --}}
    <div class="kas-cards text-gray-950 dark:text-white">
        <div class="kas-card destacada">
            <span class="titulo">Σ Tagesumsatz</span>
            <span class="valor" style="display:block;">{{ \App\Support\ReporteTexto::euro($this->totalDia) }}</span>
            <span class="detalle" style="display:block;">{{ $this->pagos->count() }} Zahlung(en)</span>
        </div>

        @foreach($this->resumen as $metodo => $datos)
            <div class="kas-card">
                <span class="titulo">{{ $metodoIcono[$metodo] ?? '' }} {{ \App\Support\ReporteTexto::metodo($metodo) }}</span>
                <span class="valor" style="display:block;">{{ \App\Support\ReporteTexto::euro($datos['total']) }}</span>
                <span class="detalle" style="display:block;">{{ $datos['cantidad'] }} Zahlung(en)</span>
            </div>
        @endforeach

        <div class="kas-card">
            <span class="titulo">📅 Termine</span>
            <span class="valor" style="display:block;">{{ $stats['completada'] ?? 0 }}</span>
            <span class="detalle" style="display:block;">
                abgeschlossen
                @if(($stats['no_show'] ?? 0) > 0) · {{ $stats['no_show'] }} nicht erschienen @endif
                @if(($stats['cancelada'] ?? 0) > 0) · {{ $stats['cancelada'] }} storniert @endif
            </span>
        </div>

        @if($this->gutscheineVendidos > 0)
            <div class="kas-card">
                <span class="titulo">🎁 Gutscheine verkauft</span>
                <span class="valor" style="display:block;">{{ $this->gutscheineVendidos }}</span>
            </div>
        @endif

        @if($this->productosVendidos > 0)
            <div class="kas-card">
                <span class="titulo">🛍️ Produktverkäufe</span>
                <span class="valor" style="display:block;">{{ \App\Support\ReporteTexto::euro($this->productosVendidos) }}</span>
            </div>
        @endif
    </div>

    {{-- Detalle de pagos del día --}}
    <x-filament::section>
        <x-slot name="heading">Zahlungen im Detail</x-slot>

        @if($this->pagos->isEmpty())
            <div class="kas-vacio text-gray-950 dark:text-white">
                Keine Zahlungen an diesem Tag.
            </div>
        @else
            <div style="overflow-x: auto;">
                <table class="kas-tabla text-gray-950 dark:text-white">
                    <thead>
                        <tr>
                            <th>Uhrzeit</th>
                            <th>Kunde</th>
                            <th>Methode</th>
                            <th>Referenz</th>
                            <th style="text-align:right;">Betrag</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->pagos as $pago)
                            <tr>
                                <td>{{ $pago->created_at?->format('H:i') }}</td>
                                <td>{{ $pago->cliente?->nombre ?? '—' }}</td>
                                <td>
                                    <span class="kas-badge">
                                        {{ $metodoIcono[$pago->metodo] ?? '' }}
                                        {{ \App\Support\ReporteTexto::metodo($pago->metodo) }}
                                    </span>
                                    @if($pago->notas === 'Gutscheinverkauf')
                                        <span class="kas-badge" style="background: rgba(245,158,11,0.2);">Gutscheinverkauf</span>
                                    @elseif($pago->notas === 'Produktverkauf')
                                        <span class="kas-badge" style="background: rgba(99,102,241,0.2);">Produktverkauf</span>
                                    @endif
                                </td>
                                <td>{{ $pago->referencia ?? '—' }}</td>
                                <td class="monto">{{ \App\Support\ReporteTexto::euro($pago->monto) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="font-weight: 700; padding-top: 12px;">Gesamt</td>
                            <td class="monto" style="font-size: 15px; padding-top: 12px;">{{ \App\Support\ReporteTexto::euro($this->totalDia) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>
