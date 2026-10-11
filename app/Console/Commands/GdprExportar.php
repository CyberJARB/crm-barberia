<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Pago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * GDPR Art. 20 — Right to Data Portability
 * Exporta todos los datos de un cliente como JSON portable.
 * Uso: php artisan gdpr:exportar {cliente_id}
 */
class GdprExportar extends Command
{
    protected $signature = 'gdpr:exportar {cliente_id : ID del cliente}';

    protected $description = 'Exporta los datos personales de un cliente (GDPR Art. 20)';

    public function handle(): int
    {
        $clienteId = $this->argument('cliente_id');

        // withoutGlobalScopes para acceder aunque esté anonimizado
        $cliente = Cliente::withoutGlobalScopes()->with(['citas.servicios', 'pagos'])->find($clienteId);

        if (! $cliente) {
            $this->error("Cliente #{$clienteId} no encontrado.");

            return self::FAILURE;
        }

        $datos = [
            'exportado_en' => now()->toIso8601String(),
            'base_legal' => 'GDPR Art. 20 — Derecho a la portabilidad',
            'responsable' => config('app.name'),
            'datos_personales' => [
                'nombre' => $cliente->nombre,
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'fecha_nacimiento' => $cliente->fecha_nacimiento?->toDateString(),
                'notas' => $cliente->notas,
                'alergias' => $cliente->alergias,
                'activo' => $cliente->activo,
                'primera_visita' => $cliente->created_at?->toDateString(),
                'ultima_visita' => $cliente->ultima_visita?->toDateString(),
                'total_visitas' => $cliente->total_visitas,
            ],
            'historial_citas' => $cliente->citas->map(fn (Cita $c) => [
                'fecha' => $c->fecha->toDateString(),
                'hora' => $c->hora_inicio,
                'estado' => $c->estado,
                'servicios' => $c->servicios->pluck('nombre'),
                'notas' => $c->notas,
            ])->values(),
            'historial_pagos' => $cliente->pagos->map(fn (Pago $p) => [
                'fecha' => $p->created_at?->toDateString(),
                'importe' => $p->monto,
                'metodo' => $p->metodo,
            ])->values(),
        ];

        $nombre = "gdpr_export_cliente_{$clienteId}_".now()->format('Y-m-d_H-i-s').'.json';
        $contenido = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Storage::put("gdpr/{$nombre}", $contenido);

        // Auditar la exportación (GDPR Art. 5.2 — Responsabilidad proactiva)
        AuditLog::registrar('GDPR_EXPORTAR', 'Cliente', (int) $clienteId, null, [
            'archivo' => $nombre,
        ], 'ok');

        $this->info("Exportación guardada: storage/app/gdpr/{$nombre}");

        return self::SUCCESS;
    }
}
