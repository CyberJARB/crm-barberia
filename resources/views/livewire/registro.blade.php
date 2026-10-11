<div class="min-h-screen flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-600 mb-4">
            <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">CRM Barberías</h1>
        <p class="mt-1 text-sm text-gray-500">14 Tage kostenlos testen — keine Kreditkarte erforderlich</p>
    </div>

    {{-- Progress --}}
    <div class="sm:mx-auto sm:w-full sm:max-w-md mb-6">
        <div class="flex items-center justify-center gap-3">
            @foreach([1 => 'Ihr Geschäft', 2 => 'Ihr Konto'] as $n => $label)
                <div class="flex items-center gap-2">
                    <div @class([
                        'w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold',
                        'bg-indigo-600 text-white' => $paso >= $n,
                        'bg-gray-200 text-gray-500' => $paso < $n,
                    ])>
                        @if($paso > $n)
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        @else
                            {{ $n }}
                        @endif
                    </div>
                    <span @class(['text-sm font-medium', 'text-indigo-600' => $paso >= $n, 'text-gray-400' => $paso < $n])>{{ $label }}</span>
                </div>
                @if($n < 2)
                    <div @class(['h-px w-8', 'bg-indigo-600' => $paso > $n, 'bg-gray-200' => $paso <= $n])></div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Card --}}
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 shadow-sm rounded-2xl border border-gray-100">

            {{-- PASO 1: Datos de la tienda --}}
            @if($paso === 1)
            <form wire:submit="siguientePaso" novalidate>
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Ihr Geschäft</h2>
                <p class="text-sm text-gray-500 mb-6">Erzählen Sie uns von Ihrem Geschäft.</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name des Geschäfts <span class="text-red-500">*</span></label>
                        <input wire:model="nombre_tienda" type="text" placeholder="z.B. Barber Kings Berlin"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('nombre_tienda') border-red-400 @enderror">
                        @error('nombre_tienda') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Typ <span class="text-red-500">*</span></label>
                        <select wire:model="tipo"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            <option value="barberia">Barbershop</option>
                            <option value="salon">Friseursalon</option>
                            <option value="unas">Nagelstudio</option>
                            <option value="spa">Kosmetik / Spa</option>
                            <option value="otro">Sonstiges</option>
                        </select>
                        @error('tipo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stadt <span class="text-red-500">*</span></label>
                        <input wire:model="ciudad" type="text" placeholder="Berlin"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('ciudad') border-red-400 @enderror">
                        @error('ciudad') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                            <input wire:model="telefono_tienda" type="tel" placeholder="+49 30 12345678"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('telefono_tienda') border-red-400 @enderror">
                            @error('telefono_tienda') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail Geschäft</label>
                            <input wire:model="email_tienda" type="email" placeholder="info@salon.de"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('email_tienda') border-red-400 @enderror">
                            @error('email_tienda') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit"
                        class="w-full flex justify-center py-2.5 px-4 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                        Weiter →
                    </button>
                </div>
            </form>
            @endif

            {{-- PASO 2: Cuenta del propietario --}}
            @if($paso === 2)
            <form wire:submit="registrar" novalidate>
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Ihr Konto</h2>
                <p class="text-sm text-gray-500 mb-6">Diese Zugangsdaten verwenden Sie zum Einloggen.</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vollständiger Name <span class="text-red-500">*</span></label>
                        <input wire:model="nombre_usuario" type="text" placeholder="Max Mustermann"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('nombre_usuario') border-red-400 @enderror">
                        @error('nombre_usuario') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail-Adresse <span class="text-red-500">*</span></label>
                        <input wire:model="email_usuario" type="email" placeholder="max@salon.de"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('email_usuario') border-red-400 @enderror">
                        @error('email_usuario') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Passwort <span class="text-red-500">*</span></label>
                        <input wire:model="password" type="password" placeholder="Mindestens 12 Zeichen"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                        @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Passwort bestätigen <span class="text-red-500">*</span></label>
                        <input wire:model="password_confirmation" type="password" placeholder="Passwort wiederholen"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @error('password_confirmation') border-red-400 @enderror">
                        @error('password_confirmation') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-start gap-2 pt-1">
                        <input wire:model="acepta_terminos" id="terminos" type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="terminos" class="text-sm text-gray-600">
                            Ich akzeptiere die <a href="#" class="text-indigo-600 hover:underline">Nutzungsbedingungen</a>
                            und die <a href="#" class="text-indigo-600 hover:underline">Datenschutzerklärung</a> (DSGVO).
                        </label>
                    </div>
                    @error('acepta_terminos') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex gap-3">
                    <button type="button" wire:click="pasoAnterior"
                        class="flex-1 py-2.5 px-4 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition-colors">
                        ← Zurück
                    </button>
                    <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-75 cursor-wait"
                        class="flex-1 flex justify-center items-center gap-2 py-2.5 px-4 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                        <span wire:loading.remove>Kostenlos starten</span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            Wird erstellt…
                        </span>
                    </button>
                </div>
            </form>
            @endif

        </div>

        {{-- Footer --}}
        <p class="mt-6 text-center text-xs text-gray-400">
            Bereits registriert?
            <a href="/admin/login" class="text-indigo-600 hover:underline font-medium">Anmelden</a>
            &nbsp;·&nbsp;
            DSGVO-konform &nbsp;·&nbsp; Server in Deutschland
        </p>
    </div>
</div>
