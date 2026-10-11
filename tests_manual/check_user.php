<?php

use App\Models\AuditLog;
use App\Models\Cliente;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Support\SsrfGuard;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== PRUEBA CRM BARBERÍAS ===\n\n";

// 1. Usuario admin
$user = User::where('email', 'admin@crm-barberia.de')->first();
if (! $user) {
    exit("ERROR: usuario no encontrado\n");
}

echo "--- USUARIO DEMO ---\n";
echo 'Email:             '.$user->email."\n";
echo 'Rol:               '.$user->rol."\n";
echo 'Activo:            '.($user->activo ? 'SI' : 'NO')."\n";
echo 'Bloqueado:         '.($user->estaBloqueado() ? 'SI' : 'NO')."\n";
echo 'Intentos fallidos: '.$user->intentos_fallidos."\n";
echo '2FA activado:      '.($user->getAppAuthenticationSecret() ? 'SI' : 'NO')."\n";
echo 'Tenant:            '.$user->tenant->nombre."\n";
echo 'Plan:              '.$user->tenant->plan."\n";
echo "\n";

// 2. Simular bloqueo
echo "--- PRUEBA BLOQUEO DE CUENTA (CWE-307) ---\n";
$userTest = User::where('email', 'admin@crm-barberia.de')->first();
$intentosAntes = $userTest->intentos_fallidos;
echo "Intentos antes: $intentosAntes\n";

// Simular 5 intentos fallidos
for ($i = 1; $i <= 5; $i++) {
    $userTest->registrarIntentoFallido();
    $userTest->refresh();
    echo "  Intento $i → intentos_fallidos=".$userTest->intentos_fallidos.
         ' bloqueado='.($userTest->estaBloqueado() ? 'SI' : 'NO')."\n";
}
echo 'Minutos para desbloqueo: '.$userTest->minutosParaDesbloqueo()."\n";

// Restablecer
$userTest->registrarLoginExitoso();
$userTest->refresh();
echo 'Tras login exitoso → intentos='.$userTest->intentos_fallidos.' bloqueado='.($userTest->estaBloqueado() ? 'SI' : 'NO')."\n";
echo "\n";

// 3. Historial de contraseñas
echo "--- HISTORIAL DE CONTRASEÑAS (CWE-521) ---\n";
$user->guardarEnHistorial();
$historial = PasswordHistory::where('user_id', $user->id)->count();
echo "Registros en historial: $historial\n";
echo "\n";

// 4. Tenant isolation
echo "--- AISLAMIENTO DE TENANT (OWASP A01) ---\n";
app()->instance('current_tenant_id', $user->tenant_id);
$clientes = Cliente::all();
echo 'Clientes visibles para tenant '.$user->tenant_id.': '.$clientes->count()."\n";

// Sin scope — todos los clientes
$todosClientes = Cliente::withoutGlobalScopes()->count();
echo "Clientes totales en BD (sin scope): $todosClientes\n";
echo "\n";

// 5. Audit log
echo "--- AUDIT LOG (ISO 27001 A.12.4) ---\n";
$logs = AuditLog::orderByDesc('created_at')->limit(5)->get();
echo "Últimas 5 entradas en audit_log:\n";
foreach ($logs as $log) {
    echo "  [{$log->created_at}] {$log->accion} → {$log->entidad} #{$log->entidad_id} ({$log->resultado})\n";
}
echo "\n";

// 6. SSRF Guard
echo "--- SSRF GUARD (OWASP A10) ---\n";
$pruebas = [
    ['http://127.0.0.1/secret',           'DEBE BLOQUEAR'],
    ['http://192.168.1.1/admin',          'DEBE BLOQUEAR'],
    ['http://10.0.0.1/internal',          'DEBE BLOQUEAR'],
    ['http://169.254.169.254/meta-data/', 'DEBE BLOQUEAR'],
    ['file:///etc/passwd',                'DEBE BLOQUEAR'],
];
foreach ($pruebas as [$url, $esperado]) {
    try {
        SsrfGuard::validar($url);
        echo "  FALLO: $url (debería haber sido bloqueada)\n";
    } catch (InvalidArgumentException $e) {
        echo "  OK BLOQUEADA: $url\n";
    }
}
echo "\n";

// 7. Comando auditar seguridad
echo "--- AUDITORÍA DE SEGURIDAD ---\n";
$kernel = $app->make(Kernel::class);
$kernel->call('seguridad:auditar');
echo $kernel->output();

echo "\n=== PRUEBA COMPLETADA ===\n";
