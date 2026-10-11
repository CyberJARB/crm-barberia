<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Resiliencia / escalado horizontal:
 * - onOneServer(): con varios nodos de app, cada tarea corre UNA sola vez
 *   (lock atómico en la cache compartida) — sin emails duplicados al escalar
 * - withoutOverlapping(): una ejecución colgada no se solapa con la siguiente
 */

// OWASP A09 / NIST CSF Detect — Anomaly detection cada 5 minutos
Schedule::command('seguridad:detectar-anomalias')
    ->everyFiveMinutes()->onOneServer()->withoutOverlapping();

// ISO 27001 A.12.3 / NIST CSF Recover — Backup cifrado diario a las 02:00
Schedule::command('seguridad:backup')
    ->dailyAt('02:00')->onOneServer()->withoutOverlapping();

// Email — Rescate de recordatorios pendientes (por si la cola cayó)
Schedule::command('citas:recordatorios-pendientes')
    ->dailyAt('07:00')->onOneServer()->withoutOverlapping();

// Email — Felicitaciones de cumpleaños (opt-in por tenant)
Schedule::command('emails:geburtstage')
    ->dailyAt('09:00')->onOneServer()->withoutOverlapping();

// Warteliste — expirar entradas cuya fecha deseada ya pasó
Schedule::command('warteliste:expirar')
    ->dailyAt('00:30')->onOneServer()->withoutOverlapping();

// Resumen del negocio — tick horario; cada tenant elige su cadencia
// (mañana / 3h / 6h / 12h) en E-Mail Einstellungen
Schedule::command('emails:resumen')
    ->hourly()->onOneServer()->withoutOverlapping();
