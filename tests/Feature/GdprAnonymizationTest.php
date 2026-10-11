<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GDPR Art. 17 — Right to Erasure
 * Verifica que la anonimización reemplaza todos los PII correctamente.
 */
class GdprAnonymizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonimizar_reemplaza_datos_personales(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Juan García',
            'email' => 'juan@ejemplo.com',
            'telefono' => '+49 123 456789',
            'notas' => 'Nota privada',
            'alergias' => 'Polen',
        ]);

        $cliente->anonimizar();
        $cliente->refresh();

        $this->assertStringContainsString('Anonimizado', $cliente->nombre);
        $this->assertNotEquals('juan@ejemplo.com', $cliente->email);
        $this->assertNull($cliente->telefono);
        $this->assertNull($cliente->notas);
        $this->assertNull($cliente->alergias);
        $this->assertNotNull($cliente->anonimizado_en);
        $this->assertFalse($cliente->activo);
    }

    public function test_anonimizar_preserva_el_registro(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $idOriginal = $cliente->id;

        $cliente->anonimizar();

        // El registro sigue existiendo (integridad del historial ISO 27001 A.12.4)
        $this->assertDatabaseHas('clientes', ['id' => $idOriginal]);
    }

    public function test_anonimizar_registra_en_audit_log(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $cliente->anonimizar();

        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'GDPR_BORRADO',
            'entidad' => 'Cliente',
            'entidad_id' => $cliente->id,
        ]);
    }
}
