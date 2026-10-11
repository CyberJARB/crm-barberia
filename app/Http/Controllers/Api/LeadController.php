<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarAcuseLead;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ingesta de leads para la web del tenant y para n8n.
 *
 * Autenticación: header X-API-Key (AutenticarTenantApi ya vinculó
 * current_tenant_id → el global scope inyecta tenant_id automáticamente).
 * El acceso a la API es una feature de plan (premium); la captura manual
 * de leads en el panel está disponible en todos los planes.
 */
class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'mensaje' => ['nullable', 'string', 'max:5000'],
            'fuente' => ['nullable', 'in:web,whatsapp,telefono,instagram,empfehlung,n8n,manual'],
            'valor_estimado' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'optin' => ['nullable', 'boolean'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ]);

        $lead = Lead::create([
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'] ?? null,
            'email' => $datos['email'] ?? null,
            'mensaje' => $datos['mensaje'] ?? null,
            'fuente' => $datos['fuente'] ?? 'web',
            'estado' => 'nuevo',
            'valor_estimado' => $datos['valor_estimado'] ?? null,
            'mensajeria_optin' => (bool) ($datos['optin'] ?? false),
            'utm_source' => $datos['utm_source'] ?? null,
            'utm_medium' => $datos['utm_medium'] ?? null,
            'utm_campaign' => $datos['utm_campaign'] ?? null,
        ]);

        // Acuse automático: solo con opt-in (§7 UWG) y plan que incluya WhatsApp.
        // El job revalida ambas condiciones en el momento del envío.
        if ($lead->mensajeria_optin && $lead->telefono && $lead->tenant->permite('whatsapp')) {
            EnviarAcuseLead::dispatch($lead->id)->afterCommit();
        }

        return response()->json(['ok' => true, 'id' => $lead->id], 201);
    }
}
