<div class="min-h-screen py-16 px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="max-w-3xl mx-auto text-center mb-12">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-600 mb-4">
            <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold text-gray-900">Einfache, transparente Preise</h1>
        <p class="mt-3 text-lg text-gray-500">
            14 Tage kostenlos testen — keine Kreditkarte erforderlich.
            Jederzeit kündbar.
        </p>
    </div>

    {{-- Planes --}}
    <div class="max-w-5xl mx-auto grid gap-8 lg:grid-cols-3">
        @foreach($planes as $clave => $plan)
            <div @class([
                'relative flex flex-col rounded-2xl bg-white p-8 shadow-sm',
                'border-2 border-indigo-600 ring-1 ring-indigo-600' => $plan['destacado'],
                'border border-gray-200' => ! $plan['destacado'],
            ])>
                @if($plan['destacado'])
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-3 py-1 text-xs font-semibold text-white">
                        Beliebt
                    </span>
                @endif

                <h2 class="text-lg font-semibold text-gray-900">{{ $plan['nombre'] }}</h2>

                <p class="mt-4 flex items-baseline gap-1">
                    <span class="text-4xl font-bold tracking-tight text-gray-900">{{ $plan['precio'] }} €</span>
                    <span class="text-sm font-medium text-gray-500">/Monat</span>
                </p>

                <ul class="mt-6 space-y-3 text-sm text-gray-600 flex-1">
                    @foreach($plan['features'] as $feature)
                        <li class="flex gap-2">
                            <svg class="w-5 h-5 flex-none text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-8 space-y-3">
                    {{-- Checkout de Stripe: POST con CSRF; el plan se valida
                         servidor-side contra la allowlist (OWASP A04) --}}
                    <form method="POST" action="{{ route('stripe.checkout') }}">
                        @csrf
                        <input type="hidden" name="plan" value="{{ $clave }}">
                        <button type="submit" @class([
                            'w-full rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                            'bg-indigo-600 text-white hover:bg-indigo-500' => $plan['destacado'],
                            'bg-gray-900 text-white hover:bg-gray-700' => ! $plan['destacado'],
                        ])>
                            Jetzt abonnieren
                        </button>
                    </form>

                    <a href="{{ route('registro') }}"
                       class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                        14 Tage kostenlos testen
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Footer info --}}
    <div class="max-w-3xl mx-auto mt-12 text-center text-sm text-gray-500 space-y-1">
        <p>Alle Preise zzgl. MwSt. Monatlich kündbar, keine Mindestlaufzeit.</p>
        <p>Sichere Zahlung per Kreditkarte oder SEPA-Lastschrift über Stripe.</p>
    </div>
</div>
