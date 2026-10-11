<x-filament-widgets::widget>
    <x-filament::section>

        <style>
            .onb-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
            .onb-titulo { font-size: 16px; font-weight: 800; margin: 0; }
            .onb-sub { font-size: 12.5px; opacity: 0.65; margin: 2px 0 0; }
            .onb-progreso { display: flex; align-items: center; gap: 10px; }
            .onb-barra { width: 160px; height: 8px; border-radius: 9999px; background: rgba(128,128,128,0.2); overflow: hidden; }
            .onb-barra > span { display: block; height: 100%; border-radius: 9999px;
                                background: linear-gradient(90deg, #4f46e5, #22c55e); transition: width 0.4s ease; }
            .onb-pasos { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-top: 16px; }
            .onb-paso { display: flex; gap: 10px; align-items: flex-start; border: 1px solid rgba(128,128,128,0.22);
                        border-radius: 12px; padding: 12px 14px; text-decoration: none; color: inherit;
                        transition: border-color 0.15s ease, transform 0.15s ease; }
            .onb-paso:hover { border-color: #6366f1; transform: translateY(-2px); }
            .onb-paso.hecho { opacity: 0.55; border-style: dashed; }
            .onb-paso.hecho:hover { transform: none; border-color: rgba(128,128,128,0.22); }
            .onb-check { flex: none; width: 22px; height: 22px; border-radius: 9999px; display: inline-flex;
                         align-items: center; justify-content: center; font-size: 12px; font-weight: 800;
                         border: 2px solid rgba(128,128,128,0.4); }
            .onb-paso.hecho .onb-check { background: #22c55e; border-color: #22c55e; color: #fff; }
            .onb-paso-titulo { font-size: 13px; font-weight: 700; margin: 0; }
            .onb-paso-desc { font-size: 11.5px; opacity: 0.65; margin: 2px 0 0; line-height: 1.4; }
            .onb-link { margin-top: 14px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
                        border: 1px dashed rgba(99,102,241,0.5); border-radius: 10px; padding: 10px 14px;
                        background: rgba(99,102,241,0.06); font-size: 12.5px; }
            .onb-link code { font-weight: 700; word-break: break-all; }
        </style>

        <div class="text-gray-950 dark:text-white">
            <div class="onb-head">
                <div>
                    <h2 class="onb-titulo">🚀 Schnellstart — in 4 Schritten startklar</h2>
                    <p class="onb-sub">Richten Sie das Wichtigste ein, damit Kunden online buchen können.</p>
                </div>
                <div class="onb-progreso">
                    <span style="font-size: 13px; font-weight: 700;">{{ $hechos }}/{{ $total }}</span>
                    <span class="onb-barra"><span style="width: {{ (int) ($hechos / max(1, $total) * 100) }}%;"></span></span>
                </div>
            </div>

            <div class="onb-pasos">
                @foreach($pasos as $paso)
                    <a href="{{ $paso['url'] }}"
                       @if(str_starts_with($paso['url'], url('/reservar'))) target="_blank" rel="noopener" @endif
                       class="onb-paso {{ $paso['hecho'] ? 'hecho' : '' }}">
                        <span class="onb-check">{{ $paso['hecho'] ? '✓' : '' }}</span>
                        <span>
                            <span class="onb-paso-titulo" style="display:block;">{{ $paso['icono'] }} {{ $paso['titulo'] }}</span>
                            <span class="onb-paso-desc" style="display:block;">{{ $paso['descripcion'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="onb-link">
                <span>🔗 Ihre Online-Buchungsseite:</span>
                <code>{{ $urlReserva }}</code>
                <x-filament::button
                    size="xs"
                    color="gray"
                    x-data
                    x-on:click="navigator.clipboard.writeText('{{ $urlReserva }}'); $tooltip('Kopiert!', { theme: $store.theme })"
                >
                    Kopieren
                </x-filament::button>
            </div>
        </div>

    </x-filament::section>
</x-filament-widgets::widget>
