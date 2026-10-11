<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * OWASP A09 - Security Logging and Monitoring Failures
 * UA2 T03 - SIEM / UA6 T03 - Métricas de seguridad
 * Detecta patrones anómalos en los logs de auditoría.
 * NIST CSF: Detect → Respond
 */
class AnomalyDetector
{
    /**
     * Ejecutar como comando programado (cada 5 minutos).
     * Detecta intentos de fuerza bruta, accesos masivos, etc.
     */
    public static function analizar(): array
    {
        $alertas = [];

        $alertas = array_merge($alertas, self::detectarFuerzaBruta());
        $alertas = array_merge($alertas, self::detectarAccesosMasivos());
        $alertas = array_merge($alertas, self::detectarEliminacionesMasivas());

        foreach ($alertas as $alerta) {
            Log::channel('security')->alert('ANOMALIA_DETECTADA', $alerta);
        }

        return $alertas;
    }

    /** Más de 10 logins fallidos en 5 minutos desde una IP */
    private static function detectarFuerzaBruta(): array
    {
        $alertas = [];
        $ventana = now()->subMinutes(5);

        $resultados = AuditLog::where('accion', 'LOGIN_BLOQUEADO')
            ->where('created_at', '>=', $ventana)
            ->selectRaw('ip, COUNT(*) as total')
            ->groupBy('ip')
            ->having('total', '>=', 3)
            ->get();

        foreach ($resultados as $r) {
            $alertas[] = [
                'tipo' => 'FUERZA_BRUTA',
                'ip' => $r->ip,
                'intentos' => $r->total,
                'ventana' => '5 minutos',
            ];
        }

        return $alertas;
    }

    /** Un usuario accede a más de 200 registros en 1 minuto */
    private static function detectarAccesosMasivos(): array
    {
        $alertas = [];
        $ventana = now()->subMinute();

        $resultados = AuditLog::whereIn('accion', ['CREATE', 'UPDATE', 'DELETE'])
            ->where('created_at', '>=', $ventana)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->having('total', '>=', 200)
            ->get();

        foreach ($resultados as $r) {
            $alertas[] = [
                'tipo' => 'ACCESO_MASIVO',
                'user_id' => $r->user_id,
                'operaciones' => $r->total,
                'ventana' => '1 minuto',
            ];
        }

        return $alertas;
    }

    /** Más de 50 eliminaciones en 10 minutos por un usuario */
    private static function detectarEliminacionesMasivas(): array
    {
        $alertas = [];
        $ventana = now()->subMinutes(10);

        $resultados = AuditLog::where('accion', 'DELETE')
            ->where('created_at', '>=', $ventana)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->having('total', '>=', 50)
            ->get();

        foreach ($resultados as $r) {
            $alertas[] = [
                'tipo' => 'ELIMINACION_MASIVA',
                'user_id' => $r->user_id,
                'eliminaciones' => $r->total,
                'ventana' => '10 minutos',
            ];
        }

        return $alertas;
    }
}
