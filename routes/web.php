<?php

use App\Http\Controllers\ReporteController;
use App\Http\Controllers\StripeController;
use App\Livewire\Precios;
use App\Livewire\Registro;
use App\Livewire\ReservarPagina;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// OWASP A05 / CWE-200 — La página welcome por defecto de Laravel revela el
// framework y su versión (fingerprinting). Redirigimos al panel directamente.
Route::get('/', fn () => redirect('/admin'));

// Health check PROFUNDO para load balancer / monitoring (el /up de Laravel
// solo confirma que la app bootea; este verifica las dependencias críticas).
// Resiliencia: si un nodo pierde la BD, el LB lo saca de rotación solo.
Route::get('/salud', function () {
    $checks = [
        'db' => fn () => DB::select('SELECT 1') !== [],
        'cache' => function (): bool {
            Cache::put('healthcheck', 'ok', 10);

            return Cache::get('healthcheck') === 'ok';
        },
        'queue' => fn () => DB::table('jobs')->count() >= 0,
    ];

    $resultado = [];
    $sano = true;

    foreach ($checks as $nombre => $check) {
        try {
            $resultado[$nombre] = $check() ? 'ok' : 'fail';
        } catch (Throwable) {
            // CWE-209 — sin detalles internos en la respuesta
            $resultado[$nombre] = 'fail';
        }
        $sano = $sano && $resultado[$nombre] === 'ok';
    }

    return response()->json(['status' => $sano ? 'ok' : 'degraded'] + $resultado, $sano ? 200 : 503);
})->middleware('throttle:60,1');

// OWASP A07 — Registro público con rate limit: 5 intentos por hora por IP
Route::middleware(['guest', 'throttle:registro'])->group(function () {
    Route::get('/registro', Registro::class)->name('registro');
});

// Página pública de precios (alemán)
Route::get('/precios', Precios::class)->name('precios');

// Página de reservas alojada de cada negocio (sin web propia no hay excusa)
// CWE-770 — throttle generoso de lectura; la creación tiene su propio límite
Route::get('/reservar/{slug}', ReservarPagina::class)
    ->middleware('throttle:60,1')
    ->where('slug', '[a-z0-9\-]+')
    ->name('reservar');

// === STRIPE / SUSCRIPCIONES (Laravel Cashier) ===
// El webhook (POST /stripe/webhook) lo registra Cashier automáticamente
// y verifica la firma con STRIPE_WEBHOOK_SECRET (CWE-345).
Route::middleware('auth')->group(function () {
    // OWASP A07 / CWE-770 — máx. 10 intentos de checkout por minuto
    // OWASP A04 / CWE-639 — el plan se valida contra allowlist en el controller
    Route::post('/stripe/checkout', [StripeController::class, 'checkout'])
        ->middleware('throttle:10,1')
        ->name('stripe.checkout');

    // Retorno tras Stripe Checkout exitoso
    Route::get('/stripe/retorno', [StripeController::class, 'success'])
        ->name('stripe.retorno');

    // === REPORTES (PDF / Excel) ===
    // OWASP A01 — el controller verifica esAdmin() + filtra por tenant
    // CWE-770 — máx. 6 descargas por minuto (generar PDF/Excel es costoso)
    Route::get('/reportes/descargar', [ReporteController::class, 'descargar'])
        ->middleware('throttle:6,1')
        ->name('reportes.descargar');
});
