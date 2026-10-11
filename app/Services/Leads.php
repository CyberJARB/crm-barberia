<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Cliente;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio del embudo de leads (fuera de los Resources Filament,
 * consistente con Services\Reservas). Transiciones auditadas y conversión
 * transaccional a Cliente.
 */
class Leads
{
    /**
     * Avanza el lead por el embudo respetando TRANSICIONES (CWE-841) y audita.
     *
     * @throws \DomainException si la transición no es válida.
     */
    public function transicionar(Lead $lead, string $destino, ?string $motivoPerdida = null): Lead
    {
        if (! $lead->puedeTransicionarA($destino)) {
            throw new \DomainException("Übergang {$lead->estado} → {$destino} nicht erlaubt.");
        }

        $anterior = $lead->estado;

        $lead->estado = $destino;

        if ($destino === 'contactado' && $lead->contactado_en === null) {
            $lead->contactado_en = now();
        }

        if (in_array($destino, ['ganado', 'perdido'], true)) {
            $lead->cerrado_en = now();
            $lead->motivo_perdida = $destino === 'perdido' ? $motivoPerdida : null;
        }

        if ($destino === 'nuevo') {
            // Reapertura: limpiar el cierre previo
            $lead->cerrado_en = null;
            $lead->motivo_perdida = null;
        }

        $lead->save();

        AuditLog::registrar('LEAD_TRANSICION', 'Lead', $lead->id,
            ['estado' => $anterior],
            ['estado' => $destino, 'motivo_perdida' => $lead->motivo_perdida]);

        return $lead;
    }

    /**
     * Convierte un lead en Cliente (crea o enlaza). Idempotente y transaccional.
     *
     * El teléfono va cifrado no-determinista, así que el dedupe ligero es por
     * email (gotcha conocido del proyecto); los duplicados se fusionan en el panel.
     */
    public function convertir(Lead $lead): Cliente
    {
        return DB::transaction(function () use ($lead) {
            if ($lead->cliente_id !== null && $lead->cliente !== null) {
                return $lead->cliente; // ya convertido
            }

            $cliente = null;
            if (! empty($lead->email)) {
                $cliente = Cliente::where('email', $lead->email)->first();
            }

            $cliente ??= Cliente::create([
                'nombre' => $lead->nombre,
                'email' => $lead->email,
                'telefono' => $lead->telefono,
                'mensajeria_optin' => $lead->mensajeria_optin,
                'mensajeria_canal' => 'whatsapp',
                'activo' => true,
            ]);

            $lead->forceFill(['cliente_id' => $cliente->id])->save();

            AuditLog::registrar('LEAD_CONVERTIDO', 'Lead', $lead->id, null, [
                'cliente_id' => $cliente->id,
            ]);

            return $cliente;
        });
    }
}
