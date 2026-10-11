<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HoneypotLogin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerificarSuscripcion;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Los listeners se registran EXPLÍCITAMENTE en AppServiceProvider.
    // Sin esto, el auto-discovery de Laravel los registraba por segunda
    // vez y cada evento (CitaCreada, Login…) disparaba los jobs DOS veces
    // → emails/mensajes duplicados a clientes reales.
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // Escalabilidad horizontal: detrás de un load balancer (Hetzner LB,
        // nginx, Cloudflare) la app recibe X-Forwarded-* del proxy. Sin esto,
        // ForceHttps redirige en bucle y las IPs del rate-limit serían las
        // del LB. El LB es quien termina TLS y sanea las cabeceras.
        $middleware->trustProxies(at: '*');

        // OWASP A02 / CWE-319 — Forzar HTTPS en producción
        $middleware->append(ForceHttps::class);

        // UA2 T03 / OWASP A05 — Security headers en TODAS las respuestas
        $middleware->append(SecurityHeaders::class);

        // UA2 T02 / OWASP A01 — Tenant isolation para usuarios autenticados
        $middleware->appendToGroup('web', EnsureTenantScope::class);

        // CWE-307 / OWASP A07 — Anti-bot honeypot en login
        $middleware->appendToGroup('web', HoneypotLogin::class);

        // OWASP A07 / CWE-770 — Rate limit en TODO el grupo web (lecturas y mutaciones,
        // incluye POST /livewire/update de Filament). Limiter 'web' en AppServiceProvider.
        // Nota: NO usar throttleWithRedis() — el store de cache es 'database' (CWE-1188).
        $middleware->appendToGroup('web', ThrottleRequests::class.':web');

        // Suscripciones Stripe — trial expirado sin suscripción => /admin/suscripcion.
        // SOLO actúa en producción: el chequeo isProduction() vive DENTRO del
        // middleware porque aquí en bootstrap env() devolvería null con
        // config:cache activo (CWE-1188); config('app.env') sí es cache-safe.
        $middleware->appendToGroup('web', VerificarSuscripcion::class);

        // Invitados en rutas 'auth' (p.ej. /stripe/checkout) → login de Filament
        // (no existe una ruta llamada 'login' fuera del panel)
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        // Alias para throttle de login
        $middleware->alias([
            'throttle.login' => ThrottleRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // OWASP A05 — No exponer stack traces ni detalles internos en producción
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 404 genérico — no revelar estructura interna (CWE-209)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Recurso no encontrado.'], 404);
            }
        });
    })->create();
