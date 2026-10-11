<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ISO 27001 A.12.3 / NIST CSF Recover (RC.RP)
 * Restaura un backup cifrado generado por `seguridad:backup`.
 *
 * Modo seguro `--verificar`: restaura en una base de datos DESECHABLE
 * ({database}_restore_check), comprueba integridad (tablas/filas) y la
 * elimina — SIN tocar la base de producción. Es la "prueba de restore"
 * exigida por los Cyber-Versicherer.
 */
class RestaurarBackup extends Command
{
    protected $signature = 'seguridad:restore
        {--archivo= : Ruta del .enc a restaurar (por defecto, el más reciente)}
        {--verificar : Restaura en una BD desechable y valida, sin tocar producción}
        {--sin-cifrar : El backup es un .sql en claro (no cifrado)}
        {--force : Permite restaurar sobre la BD real sin confirmación interactiva}';

    protected $description = 'Restaura (o verifica) un backup cifrado de la base de datos';

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));

        if (($db['driver'] ?? null) !== 'mysql') {
            $this->error("Solo soportado para MySQL (driver actual: {$db['driver']}).");

            return self::FAILURE;
        }

        // 1. Resolver el archivo de backup
        $archivo = $this->option('archivo') ?: $this->backupMasReciente();

        if (! $archivo || ! file_exists($archivo)) {
            $this->error('No se encontró ningún backup para restaurar.');

            return self::FAILURE;
        }

        $this->info('Backup: '.basename($archivo));

        // 2. Descifrar a un .sql temporal
        $sql = storage_path('app/backups/tmp_restore_'.now()->format('His').'.sql');

        if ($this->option('sin-cifrar')) {
            copy($archivo, $sql);
        } else {
            $this->info('Descifrando...');
            if (! $this->descifrar($archivo, $sql)) {
                $this->error('Error al descifrar (¿APP_KEY incorrecta?).');
                @unlink($sql);

                return self::FAILURE;
            }
        }

        // 3. Determinar la BD destino
        $verificar = (bool) $this->option('verificar');
        $destino = $verificar ? $db['database'].'_restore_check' : $db['database'];

        if (! $verificar && ! $this->option('force')) {
            if (! $this->confirm("⚠️  Esto SOBREESCRIBE la base de producción '{$destino}'. ¿Continuar?", false)) {
                @unlink($sql);
                $this->warn('Cancelado.');

                return self::SUCCESS;
            }
        }

        // 4. (verificar) crear BD desechable
        if ($verificar) {
            $this->info("Creando BD desechable '{$destino}'...");
            $this->mysqlEjecutar($db, "DROP DATABASE IF EXISTS `{$destino}`; CREATE DATABASE `{$destino}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        }

        // 5. Restaurar el volcado
        $this->info("Restaurando en '{$destino}'...");
        $code = $this->mysqlImportar($db, $destino, $sql);
        @unlink($sql);

        if ($code !== 0) {
            $this->error('Error al importar el volcado.');
            if ($verificar) {
                $this->mysqlEjecutar($db, "DROP DATABASE IF EXISTS `{$destino}`;");
            }
            Log::channel('security')->error('RESTORE_ERROR', ['codigo' => $code]);

            return self::FAILURE;
        }

        // 6. Comprobar integridad
        $tablas = (int) $this->mysqlConsultar($db, $destino, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$destino}';");
        $this->info("Tablas restauradas: {$tablas}");

        $ok = $tablas > 0;

        // 7. (verificar) eliminar la BD desechable
        if ($verificar) {
            $this->mysqlEjecutar($db, "DROP DATABASE IF EXISTS `{$destino}`;");
            $this->line('BD desechable eliminada.');
        }

        if (! $ok) {
            $this->error('El backup se restauró pero no contiene tablas — backup inválido.');

            return self::FAILURE;
        }

        $this->info($verificar
            ? "✅ Prueba de restore correcta ({$tablas} tablas). El backup es restaurable."
            : "✅ Restore completado en '{$destino}' ({$tablas} tablas).");

        Log::channel('security')->info('RESTORE_OK', [
            'modo' => $verificar ? 'verificar' : 'produccion',
            'tablas' => $tablas,
            'archivo' => basename($archivo),
        ]);

        return self::SUCCESS;
    }

    private function backupMasReciente(): ?string
    {
        $patron = $this->option('sin-cifrar') ? '*.sql' : '*.enc';
        $archivos = glob(storage_path('app/backups/'.$patron)) ?: [];
        // Excluir temporales de restore
        $archivos = array_filter($archivos, fn ($f) => ! str_contains(basename($f), 'tmp_restore_'));

        if (empty($archivos)) {
            return null;
        }

        usort($archivos, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $archivos[0];
    }

    private function descifrar(string $origen, string $destino): bool
    {
        $cmd = sprintf(
            'openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -in %s -out %s -pass pass:%s',
            escapeshellarg($origen),
            escapeshellarg($destino),
            escapeshellarg(config('app.key'))
        );
        exec($cmd, result_code: $code);

        return $code === 0 && file_exists($destino);
    }

    private function mysqlImportar(array $db, string $destino, string $sql): int
    {
        $cmd = sprintf(
            'mysql --user=%s --password=%s --host=%s --port=%s %s < %s',
            escapeshellarg($db['username']),
            escapeshellarg((string) $db['password']),
            escapeshellarg($db['host']),
            escapeshellarg((string) $db['port']),
            escapeshellarg($destino),
            escapeshellarg($sql)
        );
        exec($cmd, result_code: $code);

        return $code;
    }

    private function mysqlEjecutar(array $db, string $sentencia): int
    {
        $cmd = sprintf(
            'mysql --user=%s --password=%s --host=%s --port=%s -e %s',
            escapeshellarg($db['username']),
            escapeshellarg((string) $db['password']),
            escapeshellarg($db['host']),
            escapeshellarg((string) $db['port']),
            escapeshellarg($sentencia)
        );
        exec($cmd, result_code: $code);

        return $code;
    }

    private function mysqlConsultar(array $db, string $destino, string $consulta): string
    {
        $cmd = sprintf(
            'mysql --user=%s --password=%s --host=%s --port=%s %s -N -B -e %s',
            escapeshellarg($db['username']),
            escapeshellarg((string) $db['password']),
            escapeshellarg($db['host']),
            escapeshellarg((string) $db['port']),
            escapeshellarg($destino),
            escapeshellarg($consulta)
        );
        exec($cmd, output: $out, result_code: $code);

        return $code === 0 && ! empty($out) ? trim($out[0]) : '0';
    }
}
