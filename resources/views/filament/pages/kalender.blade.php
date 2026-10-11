<x-filament-panels::page>

    <style>
        .kal-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; }
        .kal-nav { display: flex; align-items: center; gap: 0.5rem; }
        .kal-grid { display: grid; grid-template-columns: 56px repeat(7, minmax(110px, 1fr)); border: 1px solid rgba(128,128,128,0.25); border-radius: 12px; overflow: hidden; }
        .kal-head { padding: 8px 6px; text-align: center; font-size: 12px; font-weight: 600; border-bottom: 1px solid rgba(128,128,128,0.25); border-left: 1px solid rgba(128,128,128,0.15); }
        .kal-head.hoy { background: rgba(99,102,241,0.12); }
        .kal-head .fecha { display: block; font-size: 11px; font-weight: 400; opacity: 0.65; }
        .kal-horas { position: relative; }
        .kal-hora-label { position: absolute; right: 6px; transform: translateY(-50%); font-size: 10px; opacity: 0.55; }
        .kal-dia { position: relative; border-left: 1px solid rgba(128,128,128,0.15);
                   background-image: repeating-linear-gradient(to bottom, rgba(128,128,128,0.12) 0, rgba(128,128,128,0.12) 1px, transparent 1px, transparent calc(100% / var(--kal-horas))); }
        .kal-dia.hoy { background-color: rgba(99,102,241,0.05); }
        .kal-cita { position: absolute; left: 3px; right: 3px; border-radius: 8px; padding: 3px 6px; overflow: hidden;
                    font-size: 10.5px; line-height: 1.25; color: #fff; text-decoration: none; display: block;
                    box-shadow: 0 1px 4px rgba(0,0,0,0.25); border-left: 3px solid rgba(255,255,255,0.65); }
        .kal-cita:hover { filter: brightness(1.12); z-index: 5; }
        .kal-cita.cancelada, .kal-cita.no_show { opacity: 0.45; text-decoration: line-through; }
        .kal-cita .cliente { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .kal-cita .detalle { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0.9; }
        .kal-leyenda { display: flex; flex-wrap: wrap; gap: 1rem; font-size: 11px; opacity: 0.75; }
        .kal-leyenda span { display: flex; align-items: center; gap: 4px; }
        .kal-punto { width: 9px; height: 9px; border-radius: 9999px; display: inline-block; }
        .kal-scroll { overflow-x: auto; }
    </style>

    @php
        $horas = range(\App\Filament\Pages\KalenderPage::HORA_DESDE, \App\Filament\Pages\KalenderPage::HORA_HASTA - 1);
        $altoGrid = count($horas) * 56; // 56px por hora
        $hoy = now()->toDateString();
        $diasSemana = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $estadoColor = [
            'pendiente' => '#f59e0b', 'confirmada' => '#22c55e', 'en_progreso' => '#06b6d4',
            'completada' => '#3b82f6', 'cancelada' => '#ef4444', 'no_show' => '#6b7280',
        ];
    @endphp

    {{-- Barra de herramientas --}}
    <div class="kal-toolbar">
        <div class="kal-nav">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="semanaAnterior" />
            <x-filament::button color="gray" size="sm" wire:click="semanaActual">Heute</x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="semanaSiguiente" />

            <span class="text-sm font-semibold text-gray-950 dark:text-white" style="margin-left: 0.5rem;">
                {{ $this->lunes()->format('d.m.') }} – {{ $this->lunes()->addDays(6)->format('d.m.Y') }}
                <span style="opacity: 0.6; font-weight: 400;">(KW {{ $this->lunes()->isoWeek() }})</span>
            </span>
        </div>

        <div class="kal-nav">
            {{-- Filtro por empleado (validado server-side contra el tenant) --}}
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="personalId">
                    <option value="">Alle Mitarbeiter</option>
                    @foreach($this->empleados as $empleado)
                        <option value="{{ $empleado->id }}">{{ $empleado->nombre }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\CitaResource::getUrl('create') }}"
                icon="heroicon-m-plus"
            >
                Neuer Termin
            </x-filament::button>
        </div>
    </div>

    {{-- Grid semanal --}}
    <div class="kal-scroll">
        <div class="kal-grid" style="--kal-horas: {{ count($horas) }}; min-width: 900px;">

            {{-- Cabecera --}}
            <div class="kal-head" style="border-left: none;"></div>
            @foreach($this->dias as $i => $dia)
                <div class="kal-head {{ $dia->toDateString() === $hoy ? 'hoy' : '' }} text-gray-950 dark:text-white">
                    {{ $diasSemana[$i] }}
                    <span class="fecha">{{ $dia->format('d.m.') }}</span>
                </div>
            @endforeach

            {{-- Columna de horas --}}
            <div class="kal-horas" style="height: {{ $altoGrid }}px;">
                @foreach($horas as $j => $hora)
                    <span class="kal-hora-label text-gray-950 dark:text-white" style="top: {{ $j * 56 }}px;">
                        {{ sprintf('%02d:00', $hora) }}
                    </span>
                @endforeach
            </div>

            {{-- Columnas de días --}}
            @foreach($this->dias as $dia)
                @php $clave = $dia->toDateString(); @endphp
                <div class="kal-dia {{ $clave === $hoy ? 'hoy' : '' }}" style="height: {{ $altoGrid }}px;">
                    @foreach($this->citasSemana[$clave] ?? [] as $cita)
                        <a href="{{ $cita['url'] }}"
                           class="kal-cita {{ $cita['estado'] }}"
                           style="top: {{ $cita['top'] }}%; height: {{ $cita['height'] }}%; background: {{ $cita['color'] }}; border-left-color: {{ $estadoColor[$cita['estado']] ?? '#6b7280' }};"
                           title="{{ $cita['hora'] }} · {{ $cita['cliente'] }} · {{ $cita['personal'] }}{{ $cita['servicios'] ? ' · ' . $cita['servicios'] : '' }}">
                            <span class="cliente">{{ $cita['cliente'] }}</span>
                            <span class="detalle">{{ $cita['hora'] }}</span>
                            @if($cita['servicios'])
                                <span class="detalle">{{ $cita['servicios'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    {{-- Leyenda de estados --}}
    <div class="kal-leyenda text-gray-950 dark:text-white">
        @foreach(\App\Support\ReporteTexto::estados() as $clave => $etiqueta)
            <span><i class="kal-punto" style="background: {{ $estadoColor[$clave] ?? '#6b7280' }};"></i>{{ $etiqueta }}</span>
        @endforeach
        <span style="margin-left: auto;">Blockfarbe = Mitarbeiter · Randfarbe = Status · Klick öffnet den Termin</span>
    </div>

</x-filament-panels::page>
