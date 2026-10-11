<?php

namespace App\Providers;

use App\Events\CitaCreada;
use App\Listeners\CitaCreadaListener;
use App\Listeners\LoginFailedListener;
use App\Listeners\LoginSuccessListener;
use App\Listeners\SyncPlanFromStripe;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Pago;
use App\Models\Personal;
use App\Models\Servicio;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\CitaObserver;
use App\Observers\UserObserver;
use App\Policies\CitaPolicy;
use App\Policies\ClientePolicy;
use App\Policies\LeadPolicy;
use App\Policies\PagoPolicy;
use App\Policies\PersonalPolicy;
use App\Policies\ServicioPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // === Cashier — el modelo "billable" es el Tenant (la barbería), no el User ===
        Cashier::useCustomerModel(config('cashier.model', Tenant::class));
    }

    public function boot(): void
    {
        // === NIST CSF Identify — Validar vars críticas en arranque ===
        $this->validarVariablesEntorno();

        // === OWASP A02 / CWE-319 — URLs siempre https en producción (defensa en profundidad con ForceHttps) ===
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // === CWE-693 — Prohibir migrate:fresh / db:wipe contra la BD de producción ===
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // === OWASP A07 / CWE-307 — Rate limiting global ===
        RateLimiter::for('login', function (Request $request) {
            $decay = config('security.rate_limits.login_decay_minutes', 15);

            return [
                Limit::perMinutes($decay, config('security.rate_limits.login_max_attempts', 5))
                    ->by($request->input('email').'|'.$request->ip())
                    ->response(fn () => response()->json(
                        ['message' => "Demasiados intentos de acceso. Intenta en {$decay} minutos."],
                        429
                    )),
            ];
        });

        // OWASP A07 / CWE-770 — Límite global del grupo web: cubre TODAS las rutas,
        // incluidas las mutaciones de Livewire/Filament (POST /livewire/update)
        RateLimiter::for('web', function (Request $request) {
            $key = ($request->user()?->id ?: $request->ip());

            return $request->isMethodSafe()
                ? Limit::perMinute(config('security.rate_limits.web_reads_per_minute', 300))->by('r|'.$key)
                : Limit::perMinute(config('security.rate_limits.web_writes_per_minute', 180))->by('w|'.$key);
        });

        // OWASP A07 — Registro público: máx 5 cuentas por hora por IP (anti-spam)
        RateLimiter::for('registro', function (Request $request) {
            return Limit::perHour(5)->by($request->ip())
                ->response(fn () => response()->view('errors.429', [], 429));
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('security.rate_limits.api_per_minute', 120))
                ->by($request->user()?->id ?: $request->ip());
        });

        // === OWASP A07 / NIST SP 800-63B — Política de contraseñas ===
        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised()
        );

        // === OWASP A01 / UA2 T02 — Registro de Policies (RBAC) ===
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Cita::class, CitaPolicy::class);
        Gate::policy(Servicio::class, ServicioPolicy::class);
        Gate::policy(Personal::class, PersonalPolicy::class);
        Gate::policy(Pago::class, PagoPolicy::class);

        // === OWASP A07 / CWE-307 — Auth event listeners ===
        Event::listen(Login::class, LoginSuccessListener::class);
        Event::listen(Failed::class, LoginFailedListener::class);

        // === Email notifications — Cita lifecycle ===
        Event::listen(CitaCreada::class, CitaCreadaListener::class);

        // === Stripe — sincronizar tenants.plan con la suscripción real ===
        // Cashier 16 solo emite WebhookReceived (no existe SubscriptionUpdated);
        // el listener filtra customer.subscription.created/updated. La firma del
        // webhook ya fue verificada por Cashier antes de este punto (CWE-345).
        Event::listen(
            WebhookReceived::class,
            SyncPlanFromStripe::class
        );

        // === ISO 27001 A.12.4 / NIST CSF Detect — Audit logging ===
        Cliente::observe(AuditObserver::class);
        Cita::observe(AuditObserver::class);
        Cita::observe(CitaObserver::class);
        Servicio::observe(AuditObserver::class);
        Personal::observe(AuditObserver::class);
        Pago::observe(AuditObserver::class);

        // === OWASP A07 / CWE-613 — Invalidar sesiones en cambio de rol/estado ===
        User::observe(UserObserver::class);
    }

    /** NIST CSF Identify — Falla rápida si faltan vars de entorno críticas */
    private function validarVariablesEntorno(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        // CWE-1188 — Se valida vía config() y NO env(): env() devuelve null cuando
        // config:cache está activo en producción, lo que rompería el arranque.
        $requeridas = [
            'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => config('database.default'),
            'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
        ];

        foreach ($requeridas as $var => $valor) {
            if (empty($valor)) {
                abort(500, "Variable de entorno requerida no configurada: {$var}");
            }
        }
    }
}
