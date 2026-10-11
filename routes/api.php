<?php

use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ReservaPublicaController;
use App\Http\Middleware\AutenticarTenantApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública de reservas (v1)
|--------------------------------------------------------------------------
| Consumida por los sitios web de los tenants (p. ej. puissance-website).
| Autenticación: header X-API-Key (hash por tenant, ver AutenticarTenantApi).
| OWASP A07 / CWE-770 — throttle por IP en todas las rutas; el POST de
| citas es más estricto para frenar spam de reservas.
*/

Route::prefix('v1')->middleware([AutenticarTenantApi::class])->group(function () {
    Route::get('/servicios', [ReservaPublicaController::class, 'servicios'])
        ->middleware('throttle:60,1');

    Route::get('/disponibilidad', [ReservaPublicaController::class, 'disponibilidad'])
        ->middleware('throttle:60,1');

    Route::post('/citas', [ReservaPublicaController::class, 'crearCita'])
        ->middleware('throttle:6,1');

    // Captura de leads desde el formulario web del tenant / n8n.
    Route::post('/leads', [LeadController::class, 'store'])
        ->middleware('throttle:30,1');
});
