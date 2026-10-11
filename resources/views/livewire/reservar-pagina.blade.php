<div class="min-h-full py-6 px-4 sm:py-12"
     style="--marca: {{ $this->tenant->color_primario ?? '#6366f1' }};">

    <div class="mx-auto w-full max-w-lg">

        {{-- Cabecera del negocio --}}
        <div class="rounded-2xl p-6 sm:p-8 text-center text-white shadow-lg"
             style="background: linear-gradient(135deg, var(--marca), color-mix(in srgb, var(--marca) 60%, #1e1b4b));">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ $this->tenant->nombre }}</h1>
            <p class="mt-1 text-sm opacity-90">
                Online-Termin buchen
                @if($this->tenant->ciudad) · {{ $this->tenant->ciudad }} @endif
            </p>
            @if($this->tenant->telefono)
                <p class="mt-2 text-xs opacity-75">Oder rufen Sie an: {{ $this->tenant->telefono }}</p>
            @endif
        </div>

        @if($this->exito)
            {{-- ===== Pantalla de éxito ===== --}}
            <div class="mt-4 rounded-2xl bg-white p-8 text-center shadow-lg">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-9 w-9 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                </div>

                <h2 class="mt-4 text-xl font-bold text-gray-900">Anfrage gesendet!</h2>

                <div class="mt-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-700">
                    <p class="font-semibold">{{ $this->exito['servicio'] }}</p>
                    <p class="mt-1">{{ $this->exito['fecha'] }} · {{ $this->exito['hora'] }} Uhr</p>
                </div>

                <p class="mt-4 text-sm text-gray-500">
                    {{ $this->tenant->nombre }} bestätigt Ihren Termin in Kürze.
                    @if($this->exito['email'])
                        Sie erhalten die Bestätigung per E-Mail.
                    @endif
                </p>

                <button wire:click="otraReserva"
                        class="mt-6 w-full rounded-xl px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90"
                        style="background: var(--marca);">
                    Weiteren Termin buchen
                </button>
            </div>
        @else
            {{-- ===== Paso 1: Dienstleistung ===== --}}
            <div class="mt-4 rounded-2xl bg-white p-5 sm:p-6 shadow-lg">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-400">1 · Dienstleistung</h2>

                <div class="mt-3 space-y-2">
                    @forelse($this->servicios as $servicio)
                        <button type="button"
                                wire:click="elegirServicio({{ $servicio->id }})"
                                @class([
                                    'flex w-full items-center justify-between rounded-xl border-2 px-4 py-3 text-left transition',
                                    'border-gray-200 hover:border-gray-300' => $servicioId !== $servicio->id,
                                ])
                                @if($servicioId === $servicio->id)
                                    style="border-color: var(--marca); background: color-mix(in srgb, var(--marca) 8%, #ffffff);"
                                @endif>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ $servicio->nombre }}</span>
                                <span class="block text-xs text-gray-500">{{ $servicio->duracion_minutos }} Min.</span>
                            </span>
                            <span class="text-sm font-bold text-gray-900">
                                {{ \App\Support\ReporteTexto::euro($servicio->precio) }}
                            </span>
                        </button>
                    @empty
                        <p class="text-sm text-gray-500">Derzeit sind keine Online-Buchungen möglich.</p>
                    @endforelse
                </div>
            </div>

            @if($servicioId)
                {{-- ===== Paso 2: Datum ===== --}}
                <div class="mt-4 rounded-2xl bg-white p-5 sm:p-6 shadow-lg">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-400">2 · Datum</h2>

                    <div class="mt-3 flex gap-2 overflow-x-auto pb-2">
                        @foreach($this->dias as $dia)
                            <button type="button"
                                    wire:click="elegirFecha('{{ $dia['valor'] }}')"
                                    @class([
                                        'flex-none rounded-xl border-2 px-3 py-2 text-center transition w-16',
                                        'border-gray-200 hover:border-gray-300' => $fecha !== $dia['valor'],
                                    ])
                                    @if($fecha === $dia['valor'])
                                        style="border-color: var(--marca); background: color-mix(in srgb, var(--marca) 8%, #ffffff);"
                                    @endif>
                                <span class="block text-xs font-medium text-gray-500">{{ $dia['hoy'] ? 'Heute' : $dia['dia'] }}</span>
                                <span class="block text-sm font-bold text-gray-900">{{ $dia['num'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- ===== Paso 3: Uhrzeit ===== --}}
                    <h2 class="mt-5 text-sm font-bold uppercase tracking-wide text-gray-400">3 · Uhrzeit</h2>

                    @php $slots = $this->slots; @endphp

                    @if($slots === [])
                        <p class="mt-3 rounded-xl bg-gray-50 p-4 text-center text-sm text-gray-500">
                            An diesem Tag geschlossen — bitte wählen Sie ein anderes Datum.
                        </p>
                    @else
                        <div class="mt-3 grid grid-cols-4 gap-2">
                            @foreach($slots as $slot)
                                <button type="button"
                                        wire:click="elegirHora('{{ $slot['hora'] }}')"
                                        @disabled(! $slot['disponible'])
                                        @class([
                                            'rounded-lg border-2 py-2 text-center text-sm font-semibold transition',
                                            'border-gray-200 text-gray-900 hover:border-gray-300' => $slot['disponible'] && $hora !== $slot['hora'],
                                            'border-gray-100 bg-gray-50 text-gray-300 line-through cursor-not-allowed' => ! $slot['disponible'],
                                        ])
                                        @if($hora === $slot['hora'])
                                            style="border-color: var(--marca); background: var(--marca); color: #ffffff;"
                                        @endif>
                                    {{ $slot['hora'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @error('hora')
                        <p class="mt-3 rounded-xl bg-red-50 p-3 text-center text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            @if($servicioId && $hora)
                {{-- ===== Paso 4: Daten ===== --}}
                <form wire:submit="reservar" class="mt-4 rounded-2xl bg-white p-5 sm:p-6 shadow-lg">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-400">4 · Ihre Daten</h2>

                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div>
                            <input type="text" wire:model="nombre" placeholder="Vorname *" autocomplete="given-name"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:ring-2" style="--tw-ring-color: var(--marca);">
                            @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <input type="text" wire:model="apellido" placeholder="Nachname *" autocomplete="family-name"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:ring-2" style="--tw-ring-color: var(--marca);">
                            @error('apellido') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="col-span-2">
                            <input type="tel" wire:model="telefono" placeholder="Telefon *" autocomplete="tel"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:ring-2" style="--tw-ring-color: var(--marca);">
                            @error('telefono') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="col-span-2">
                            <input type="email" wire:model="email" placeholder="E-Mail (für die Bestätigung)" autocomplete="email"
                                   class="w-full rounded-xl border-gray-300 text-sm focus:ring-2" style="--tw-ring-color: var(--marca);">
                            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="col-span-2">
                            <textarea wire:model="notas" rows="2" placeholder="Anmerkungen (optional)"
                                      class="w-full rounded-xl border-gray-300 text-sm focus:ring-2" style="--tw-ring-color: var(--marca);"></textarea>
                            @error('notas') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <button type="submit"
                            class="mt-4 w-full rounded-xl px-4 py-3.5 text-sm font-bold text-white transition hover:opacity-90 disabled:opacity-50"
                            style="background: var(--marca);"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="reservar">Jetzt verbindlich anfragen</span>
                        <span wire:loading wire:target="reservar">Wird gesendet…</span>
                    </button>

                    <p class="mt-3 text-center text-xs text-gray-400">
                        Mit der Buchung stimmen Sie der Verarbeitung Ihrer Daten zur Terminabwicklung zu (DSGVO Art. 6 Abs. 1 lit. b).
                    </p>
                </form>
            @endif
        @endif

        <p class="mt-6 text-center text-xs text-gray-400">
            Powered by <a href="{{ config('saas.web') }}" target="_blank" rel="noopener" class="hover:text-gray-600">{{ config('saas.producto') }} · {{ config('saas.empresa') }}</a>
        </p>
    </div>
</div>
