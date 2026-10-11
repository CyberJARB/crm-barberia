<x-filament-panels::page>

    {{-- Sin tema Filament custom: todo el estilo vive aquí (las clases
         Tailwind arbitrarias no están compiladas en el CSS del panel). --}}
    <style>
        /* ===== Hero ===== */
        .abo-hero { position: relative; overflow: hidden; border-radius: 16px; padding: 36px 32px;
                    background: linear-gradient(135deg, #1e1b4b 0%, #4f46e5 55%, #7c3aed 100%);
                    box-shadow: 0 18px 40px -20px rgba(79, 70, 229, 0.55); }
        .abo-hero-fila { position: relative; display: flex; flex-wrap: wrap; align-items: flex-end;
                         justify-content: space-between; gap: 24px; z-index: 1; }
        .abo-hero-sub { font-size: 13px; font-weight: 600; color: #c7d2fe; letter-spacing: 0.06em;
                        text-transform: uppercase; margin: 0; }
        .abo-hero-titulo { display: flex; align-items: center; gap: 12px; margin-top: 8px; flex-wrap: wrap; }
        .abo-hero-plan { font-size: 32px; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; margin: 0; }
        .abo-hero-texto { margin: 14px 0 0; font-size: 14px; line-height: 1.6; color: #e0e7ff; max-width: 560px; }
        .abo-hero-texto strong { color: #ffffff; }
        .abo-contador { border-radius: 14px; padding: 18px 28px; text-align: center;
                        background: rgba(255,255,255,0.14); backdrop-filter: blur(6px);
                        border: 1px solid rgba(255,255,255,0.22); }
        .abo-contador .num { font-size: 40px; font-weight: 800; color: #ffffff; line-height: 1; }
        .abo-contador .txt { display: block; margin-top: 6px; font-size: 11px; font-weight: 600;
                             letter-spacing: 0.08em; text-transform: uppercase; color: #c7d2fe; }
        .abo-hero-deco { position: absolute; right: -40px; top: -60px; width: 280px; height: 280px;
                         border-radius: 9999px; background: radial-gradient(circle,
                         rgba(255,255,255,0.14) 0%, rgba(255,255,255,0) 70%); }
        .abo-hero-deco2 { position: absolute; left: 30%; bottom: -120px; width: 240px; height: 240px;
                          border-radius: 9999px; background: radial-gradient(circle,
                          rgba(245,197,24,0.18) 0%, rgba(245,197,24,0) 70%); }

        /* ===== Grid de planes ===== */
        .abo-planes { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                      align-items: stretch; padding-top: 14px; }
        .abo-card { position: relative; display: flex; flex-direction: column; border-radius: 16px;
                    padding: 26px 24px; border: 1px solid rgba(128,128,128,0.25);
                    background: var(--abo-card-bg, #ffffff);
                    transition: transform 0.18s ease, box-shadow 0.18s ease; }
        .abo-card:hover { transform: translateY(-4px); box-shadow: 0 16px 32px -18px rgba(0,0,0,0.35); }
        .abo-card { --abo-card-bg: #ffffff; --abo-texto: #111827; --abo-texto-suave: #6b7280; }
        .dark .abo-card { --abo-card-bg: #18181b; --abo-texto: #f9fafb; --abo-texto-suave: #9ca3af;
                          border-color: rgba(255,255,255,0.12); }

        .abo-card.destacado { border: 2px solid transparent;
                              background: linear-gradient(var(--abo-card-bg), var(--abo-card-bg)) padding-box,
                                          linear-gradient(135deg, #4f46e5, #7c3aed, #f59e0b) border-box;
                              box-shadow: 0 14px 36px -16px rgba(79, 70, 229, 0.5); }
        .abo-card.actual { border: 2px solid #16a34a; box-shadow: 0 10px 28px -16px rgba(22, 163, 74, 0.45); }

        .abo-cinta { position: absolute; top: -13px; left: 50%; transform: translateX(-50%);
                     border-radius: 9999px; padding: 4px 14px; font-size: 11px; font-weight: 700;
                     letter-spacing: 0.04em; color: #ffffff; white-space: nowrap;
                     background: linear-gradient(90deg, #4f46e5, #7c3aed);
                     box-shadow: 0 4px 12px -4px rgba(79, 70, 229, 0.7); }
        .abo-cinta.verde { background: linear-gradient(90deg, #16a34a, #22c55e);
                           box-shadow: 0 4px 12px -4px rgba(22, 163, 74, 0.7); }

        .abo-card-nombre { font-size: 15px; font-weight: 700; color: var(--abo-texto); margin: 0; }
        .abo-precio { display: flex; align-items: baseline; gap: 6px; margin-top: 16px; }
        .abo-precio .euros { font-size: 38px; font-weight: 800; letter-spacing: -0.02em; color: var(--abo-texto); }
        .abo-precio .mes { font-size: 13px; color: var(--abo-texto-suave); }
        .abo-sep { border: none; border-top: 1px solid rgba(128,128,128,0.2); margin: 18px 0; }

        .abo-features { list-style: none; margin: 0; padding: 0; flex: 1; display: flex;
                        flex-direction: column; gap: 11px; }
        .abo-features li { display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px;
                           line-height: 1.45; color: var(--abo-texto-suave); }
        .abo-check { flex: none; margin-top: 1px; width: 18px; height: 18px; border-radius: 9999px;
                     display: inline-flex; align-items: center; justify-content: center;
                     background: rgba(34, 197, 94, 0.15); color: #16a34a; font-size: 11px; font-weight: 800; }
        .abo-boton { margin-top: 22px; }
        .abo-boton form { margin: 0; }

        /* ===== Sellos de confianza ===== */
        .abo-sellos { display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
                      gap: 10px 28px; margin-top: 24px; font-size: 12px; color: var(--abo-texto-suave, #6b7280); }
        .abo-sellos span { display: inline-flex; align-items: center; gap: 6px; }
        .dark .abo-sellos { color: #9ca3af; }

        /* ===== Método de pago ===== */
        .abo-pago { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
        .abo-pago-izq { display: flex; align-items: center; gap: 16px; }
        .abo-pago-icono { flex: none; width: 52px; height: 52px; border-radius: 14px; display: inline-flex;
                          align-items: center; justify-content: center; font-size: 24px;
                          background: linear-gradient(135deg, rgba(79,70,229,0.14), rgba(124,58,237,0.14)); }
        .abo-pago-titulo { font-size: 14px; font-weight: 700; margin: 0; color: var(--abo-texto, #111827); }
        .dark .abo-pago-titulo { color: #f9fafb; }
        .abo-pago-sub { font-size: 12px; margin: 2px 0 0; color: #6b7280; }
        .dark .abo-pago-sub { color: #9ca3af; }
    </style>

    {{-- ===== a) Estado actual — hero ===== --}}
    <div class="abo-hero">
        <div class="abo-hero-deco"></div>
        <div class="abo-hero-deco2"></div>

        <div class="abo-hero-fila">
            <div>
                <p class="abo-hero-sub">Ihr Abonnement</p>

                <div class="abo-hero-titulo">
                    <h2 class="abo-hero-plan">{{ $planNombre }}</h2>
                    @if($this->estado === 'activa')
                        <x-filament::badge color="success" size="lg">Aktiv</x-filament::badge>
                    @elseif($this->estado === 'trial')
                        <x-filament::badge color="info" size="lg">Testphase</x-filament::badge>
                    @elseif($this->estado === 'cancelada')
                        <x-filament::badge color="warning" size="lg">Gekündigt</x-filament::badge>
                    @else
                        <x-filament::badge color="danger" size="lg">Abgelaufen</x-filament::badge>
                    @endif
                </div>

                <p class="abo-hero-texto">
                    @if($this->estado === 'trial')
                        Ihre kostenlose Testphase endet in
                        <strong>{{ $this->diasTrial }} {{ $this->diasTrial === 1 ? 'Tag' : 'Tagen' }}</strong>.
                        Wählen Sie jetzt einen Plan, um ohne Unterbrechung weiterzuarbeiten.
                    @elseif($this->estado === 'cancelada' && $this->finGracia)
                        Ihr Zugang bleibt bis zum <strong>{{ $this->finGracia }}</strong> bestehen.
                        Sie können Ihr Abonnement jederzeit reaktivieren.
                    @elseif($this->estado === 'activa')
                        Vielen Dank für Ihr Vertrauen — alle Funktionen Ihres Plans sind freigeschaltet.
                    @else
                        Ihr Abonnement ist abgelaufen. Wählen Sie einen Plan, um alle Funktionen wieder freizuschalten.
                    @endif
                </p>
            </div>

            @if($this->estado === 'trial' && $this->diasTrial !== null)
                <div class="abo-contador">
                    <span class="num">{{ $this->diasTrial }}</span>
                    <span class="txt">{{ $this->diasTrial === 1 ? 'Tag übrig' : 'Tage übrig' }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== b) Planes ===== --}}
    <x-filament::section>
        <x-slot name="heading">Plan wählen</x-slot>
        <x-slot name="description">
            Sichere Zahlung per Kreditkarte oder SEPA-Lastschrift über Stripe. Alle Preise zzgl. MwSt. — monatlich kündbar.
        </x-slot>

        <div class="abo-planes">
            @foreach($planes as $clave => $plan)
                @php $esActual = $planActual === $clave && $this->estado === 'activa'; @endphp

                <div class="abo-card {{ $esActual ? 'actual' : ($plan['destacado'] ? 'destacado' : '') }}">

                    @if($esActual)
                        <span class="abo-cinta verde">✓ Aktueller Plan</span>
                    @elseif($plan['destacado'])
                        <span class="abo-cinta">★ Beliebt</span>
                    @endif

                    <h3 class="abo-card-nombre">{{ $plan['nombre'] }}</h3>

                    <div class="abo-precio">
                        <span class="euros">{{ $plan['precio'] }}&nbsp;€</span>
                        <span class="mes">/ Monat</span>
                    </div>

                    <hr class="abo-sep">

                    <ul class="abo-features">
                        @foreach($plan['features'] as $feature)
                            <li><span class="abo-check">✓</span><span>{{ $feature }}</span></li>
                        @endforeach
                    </ul>

                    <div class="abo-boton">
                        @if($esActual)
                            <x-filament::button style="width: 100%;" color="gray" disabled icon="heroicon-o-check-circle">
                                Ihr aktueller Plan
                            </x-filament::button>
                        @else
                            {{-- POST con CSRF a Stripe Checkout; plan validado
                                 servidor-side contra allowlist (OWASP A04) --}}
                            <form method="POST" action="{{ route('stripe.checkout') }}">
                                @csrf
                                <input type="hidden" name="plan" value="{{ $clave }}">
                                <x-filament::button
                                    type="submit"
                                    style="width: 100%;"
                                    :color="$plan['destacado'] ? 'primary' : 'gray'"
                                    icon="heroicon-o-arrow-right-circle"
                                >
                                    Jetzt wählen
                                </x-filament::button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Sellos de confianza --}}
        <div class="abo-sellos">
            <span><x-filament::icon icon="heroicon-m-lock-closed" style="width:16px;height:16px;" /> SSL-verschlüsselte Zahlung</span>
            <span><x-filament::icon icon="heroicon-m-shield-check" style="width:16px;height:16px;" /> DSGVO-konform</span>
            <span><x-filament::icon icon="heroicon-m-arrow-path" style="width:16px;height:16px;" /> Monatlich kündbar</span>
        </div>
    </x-filament::section>

    {{-- ===== c) Método de pago ===== --}}
    <x-filament::section>
        <x-slot name="heading">Zahlungsmethode</x-slot>
        <x-slot name="description">
            Zahlungsdaten werden ausschließlich bei Stripe gespeichert — niemals auf unseren Servern.
        </x-slot>

        <div class="abo-pago">
            <div class="abo-pago-izq">
                <span class="abo-pago-icono">💳</span>
                <div>
                    @if($tenant->pm_last_four)
                        <p class="abo-pago-titulo">
                            {{ $tenant->pm_type === 'sepa_debit' ? 'SEPA-Lastschrift' : ucfirst($tenant->pm_type ?? 'Karte') }}
                            •••• {{ $tenant->pm_last_four }}
                        </p>
                        <p class="abo-pago-sub">Standard-Zahlungsmethode</p>
                    @else
                        <p class="abo-pago-titulo">Keine Zahlungsmethode hinterlegt</p>
                        <p class="abo-pago-sub">Wird beim ersten Checkout automatisch gespeichert.</p>
                    @endif
                </div>
            </div>

            @if($tenant->hasStripeId())
                {{-- Stripe Customer Portal: gestionar tarjetas/IBAN, facturas y cancelación --}}
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-arrow-top-right-on-square"
                    wire:click="mountAction('stripePortal')"
                >
                    Im Stripe-Portal verwalten
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>

</x-filament-panels::page>
