<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * ISO 27001 A.12.3 / NIST CSF Recover
 * Genera un volcado cifrado de la base de datos.
 * Cifrado AES-256-CBC via openssl.
 * Retención: 30 días de backups diarios.
 */
class BackupEncriptado extends Command
{
    protected $signature = 'seguridad:backup {--sin-cifrar : Solo volcar, sin cifrar (solo dev)}';

    protected $description = 'Backup cifrado de la base de datos (AES-256-CBC)';

    private const RETENER_DIAS = 30;

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        $tipo = $db['driver'] ?? 'mysql';

        if (! in_array($tipo, ['mysql', 'pgsql'], true)) {
            $this->error("Driver '{$tipo}' no soportado para backup automático.");

            return self::FAILURE;
        }

        $nombre = 'backup_'.now()->format('Y-m-d_H-i-s').'.sql';
        $temporal = storage_path("app/backups/tmp_{$nombre}");
        $destino = storage_path("app/backups/{$nombre}.enc");

        // El volcado y el cifrado escriben en storage/app/backups via rutas
        // absolutas; hay que crear ESE directorio (no el del disco 'private').
        File::ensureDirectoryExists(storage_path('app/backups'));

        // 1. Volcar la base de datos
        $this->info('Volcando base de datos...');
        $exitCode = $this->volcarDB($db, $temporal);

        if ($exitCode !== 0 || ! file_exists($temporal)) {
            $this->error('Error al volcar la base de datos.');

            return self::FAILURE;
        }

        if ($this->option('sin-cifrar')) {
            rename($temporal, storage_path("app/backups/{$nombre}"));
            $this->info("Backup sin cifrar: backups/{$nombre}");

            return self::SUCCESS;
        }

        // 2. Cifrar con AES-256-CBC (ISO 27001 A.12.3)
        $this->info('Cifrando backup...');
        $clave = config('app.key');  // Usa la APP_KEY de Laravel (AES-256)

        $comando = sprintf(
            'openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 -in %s -out %s -pass pass:%s',
            escapeshellarg($temporal),
            escapeshellarg($destino),
            escapeshellarg($clave)
        );

        exec($comando, output: $out, result_code: $code);
        unlink($temporal);

        if ($code !== 0) {
            $this->error('Error al cifrar el backup.');
            Log::channel('security')->error('BACKUP_ERROR', ['codigo' => $code]);

            return self::FAILURE;
        }

        $this->info("Backup cifrado: backups/{$nombre}.enc (".$this->tamano($destino).')');
        Log::channel('security')->info('BACKUP_EXITOSO', ['archivo' => "{$nombre}.enc"]);

        // 3. Limpiar backups viejos
        $this->limpiarViejos();

        return self::SUCCESS;
    }

    private function volcarDB(array $db, string $destino): int
    {
        if ($db['driver'] === 'mysql') {
            // --single-transaction: snapshot consistente InnoDB sin bloquear (no
            //   requiere LOCK TABLES); el backup no congela la app en producción.
            // --no-tablespaces: evita el privilegio PROCESS que mysqldump 8.0
            //   exigiría por defecto → permite correr con usuario de permisos
            //   mínimos (solo SELECT/INSERT/UPDATE/DELETE). OWASP A04 / least privilege.
            $cmd = sprintf(
                'mysqldump --single-transaction --no-tablespaces --user=%s --password=%s --host=%s --port=%s %s > %s',
                escapeshellarg($db['username']),
                escapeshellarg($db['password']),
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['database']),
                escapeshellarg($destino)
            );
        } else {
            $cmd = sprintf(
                'PGPASSWORD=%s pg_dump -U %s -h %s -p %s %s > %s',
                escapeshellarg($db['password']),
                escapeshellarg($db['username']),
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['database']),
                escapeshellarg($destino)
            );
        }

        exec($cmd, result_code: $code);

        return $code;
    }

    private function limpiarViejos(): void
    {
        $limite = now()->subDays(self::RETENER_DIAS)->timestamp;
        $borrados = 0;

        foreach (glob(storage_path('app/backups/*.enc')) as $archivo) {
            if (filemtime($archivo) < $limite) {
                unlink($archivo);
                $borrados++;
            }
        }

        if ($borrados > 0) {
            $this->line('Backups eliminados (>'.self::RETENER_DIAS." días): {$borrados}");
        }
    }

    private function tamano(string $ruta): string
    {
        $bytes = filesize($ruta);

        return $bytes > 1_048_576
            ? round($bytes / 1_048_576, 1).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
