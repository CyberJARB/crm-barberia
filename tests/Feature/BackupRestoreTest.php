<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ISO 27001 A.12.3 / NIST CSF Recover (RC.RP)
 * Verifica que un backup cifrado se puede RESTAURAR (no solo crear).
 * La prueba de restore real corre en una BD desechable y se omite si las
 * herramientas (mysqldump/mysql/openssl) no están disponibles (p. ej. CI).
 */
class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_falla_de_forma_segura_sin_backup(): void
    {
        $this->artisan('seguridad:restore', ['--archivo' => storage_path('app/backups/no_existe.enc')])
            ->assertExitCode(1);
    }

    public function test_ciclo_backup_restore_verificar(): void
    {
        if (config('database.default') !== 'mysql' || ! $this->herramientasDisponibles()) {
            $this->markTestSkipped('Requiere MySQL + mysqldump/mysql/openssl en PATH.');
        }

        $dir = storage_path('app/backups');
        $previos = glob($dir.'/*.enc') ?: [];

        // 1. Crear backup cifrado de la BD de test
        $this->artisan('seguridad:backup')->assertExitCode(0);

        $nuevos = array_diff(glob($dir.'/*.enc') ?: [], $previos);
        $this->assertNotEmpty($nuevos, 'No se generó archivo de backup.');
        $archivo = reset($nuevos);

        // 2. Verificar restore en BD desechable (no toca la BD de test)
        $this->artisan('seguridad:restore', ['--verificar' => true, '--archivo' => $archivo])
            ->assertExitCode(0);

        // 3. Limpieza del artefacto generado
        @unlink($archivo);
    }

    private function herramientasDisponibles(): bool
    {
        foreach (['mysqldump --version', 'mysql --version', 'openssl version'] as $cmd) {
            exec($cmd.' 2>&1', $out, $code);
            if ($code !== 0) {
                return false;
            }
        }

        return true;
    }
}
