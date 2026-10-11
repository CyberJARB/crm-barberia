<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * NIST CSF Identify / ISO 27001 A.18.2.2 — Verificación del cumplimiento técnico.
 * Comprueba la configuración de seguridad de la aplicación en tiempo de ejecución.
 * Uso: php artisan seguridad:auditar
 */
class AuditarSeguridad extends Command
{
    protected $signature = 'seguridad:auditar {--json : Salida en formato JSON}';

    protected $description = 'Audita la configuración de seguridad de la aplicación';

    private array $resultados = [];

    private int $errores = 0;

    private int $avisos = 0;

    public function handle(): int
    {
        $this->verificarEntorno();
        $this->verificarCriptografia();
        $this->verificarSesion();
        $this->verificarBaseDatos();
        $this->verificarArchivos();
        $this->verificarDependencias();

        if ($this->option('json')) {
            $this->line(json_encode($this->resultados, JSON_PRETTY_PRINT));
        } else {
            $this->mostrarResumen();
        }

        return $this->errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function verificarEntorno(): void
    {
        $this->grupo('Entorno');

        $this->check('APP_DEBUG desactivado en producción',
            fn () => ! (app()->isProduction() && config('app.debug') === true),
            'CRÍTICO: APP_DEBUG=true en producción expone stack traces (CWE-209)'
        );

        $this->check('APP_KEY configurada',
            fn () => ! empty(config('app.key')),
            'CRÍTICO: APP_KEY vacía — sin cifrado ni CSRF protection'
        );

        $this->check('APP_ENV no es local en producción',
            fn () => ! (app()->isProduction() && config('app.env') === 'local'),
            'APP_ENV=local en producción'
        );

        $this->check('Clave bcrypt suficientemente fuerte (≥12)',
            fn () => (int) config('hashing.bcrypt.rounds', 10) >= 12,
            'BCRYPT_ROUNDS < 12 — contraseñas vulnerables a ataques de fuerza bruta'
        );
    }

    private function verificarCriptografia(): void
    {
        $this->grupo('Criptografía');

        $this->check('Sesiones cifradas',
            fn () => (bool) config('session.encrypt'),
            'SESSION_ENCRYPT=false — tokens de sesión en texto claro (CWE-311)'
        );

        $this->check('Cookie segura en producción',
            fn () => ! (app()->isProduction() && ! config('session.secure')),
            'SESSION_SECURE_COOKIE=false en producción — cookie transmisible por HTTP (CWE-614)'
        );

        $this->check('Cookie HttpOnly activada',
            fn () => (bool) config('session.http_only'),
            'SESSION_HTTP_ONLY=false — cookie accesible por JavaScript (CWE-1004)'
        );

        $this->check('SameSite configurado',
            fn () => in_array(strtolower(config('session.same_site', '')), ['lax', 'strict']),
            'SESSION_SAME_SITE no configurado — vulnerable a CSRF cross-site'
        );
    }

    private function verificarSesion(): void
    {
        $this->grupo('Sesión y Autenticación');

        $this->check('Driver de sesión es database o redis',
            fn () => in_array(config('session.driver'), ['database', 'redis', 'memcached']),
            'SESSION_DRIVER=file — sesiones en sistema de archivos, no escalable'
        );

        $this->check('Tiempo de sesión razonable (≤960 min)',
            fn () => (int) config('session.lifetime', 120) <= 960,
            'SESSION_LIFETIME muy alto — sesiones de muy larga duración'
        );

        $this->check('Tabla de sesiones existe',
            fn () => Schema::hasTable('sessions'),
            'Tabla sessions no existe — ejecutar: php artisan session:table && migrate'
        );

        $this->check('Tabla de audit_logs existe',
            fn () => Schema::hasTable('audit_logs'),
            'Tabla audit_logs no existe — ejecutar: php artisan migrate'
        );
    }

    private function verificarBaseDatos(): void
    {
        $this->grupo('Base de Datos');

        $this->check('No usar root en producción',
            fn () => ! (app()->isProduction() && config('database.connections.mysql.username') === 'root'),
            'CRÍTICO: DB_USERNAME=root en producción — principio de mínimo privilegio'
        );

        $this->check('Contraseña de DB configurada en producción',
            fn () => ! (app()->isProduction() && empty(config('database.connections.mysql.password'))),
            'DB_PASSWORD vacía en producción'
        );

        $this->check('Tabla password_histories existe',
            fn () => Schema::hasTable('password_histories'),
            'Historial de contraseñas no migrado — ejecutar: php artisan migrate'
        );
    }

    private function verificarArchivos(): void
    {
        $this->grupo('Archivos y Almacenamiento');

        $this->check('.env no está en public/',
            fn () => ! file_exists(public_path('.env')),
            'CRÍTICO: .env en public/ — credenciales expuestas'
        );

        $this->check('storage/ tiene permisos correctos',
            fn () => is_writable(storage_path()),
            'storage/ no tiene permisos de escritura'
        );

        $this->check('security.txt existe',
            fn () => file_exists(public_path('.well-known/security.txt')),
            'RFC 9116: falta .well-known/security.txt'
        );
    }

    private function verificarDependencias(): void
    {
        $this->grupo('Dependencias');

        $lockFile = base_path('composer.lock');

        $this->check('composer.lock existe',
            fn () => file_exists($lockFile),
            'composer.lock no encontrado — versiones de dependencias no fijadas'
        );

        $this->check('Directorio vendor existe',
            fn () => is_dir(base_path('vendor')),
            'Directorio vendor/ no encontrado'
        );
    }

    // =====================================================================

    private function check(string $descripcion, \Closure $test, string $mensaje): void
    {
        try {
            $pasa = $test();
        } catch (\Throwable) {
            $pasa = false;
        }

        $estado = $pasa ? 'OK' : (str_starts_with($mensaje, 'CRÍTICO') ? 'ERROR' : 'AVISO');

        $this->resultados[] = compact('descripcion', 'estado', 'mensaje');

        if (! $pasa) {
            str_starts_with($mensaje, 'CRÍTICO') ? $this->errores++ : $this->avisos++;
        }
    }

    private function grupo(string $nombre): void
    {
        if (! $this->option('json')) {
            $this->newLine();
            $this->line("<fg=cyan;options=bold>── {$nombre} ──</>");
        }
    }

    private function mostrarResumen(): void
    {
        foreach ($this->resultados as $r) {
            match ($r['estado']) {
                'OK' => $this->line("  <fg=green>✓</> {$r['descripcion']}"),
                'AVISO' => $this->line("  <fg=yellow>⚠</> {$r['descripcion']}: {$r['mensaje']}"),
                'ERROR' => $this->line("  <fg=red>✗</> {$r['descripcion']}: {$r['mensaje']}"),
                default => null,
            };
        }

        $total = count($this->resultados);
        $ok = $total - $this->errores - $this->avisos;

        $this->newLine();
        $this->line("Resultado: <fg=green>{$ok} OK</> | <fg=yellow>{$this->avisos} avisos</> | <fg=red>{$this->errores} errores</>");

        if ($this->errores > 0) {
            $this->error('Hay problemas críticos de seguridad que deben corregirse.');
        } elseif ($this->avisos > 0) {
            $this->warn('La configuración tiene mejoras pendientes.');
        } else {
            $this->info('La configuración de seguridad pasa todas las verificaciones.');
        }
    }
}
