<?php

namespace Tests\Feature;

use App\Filament\Resources\ClienteResource\Pages\ListClientes;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step-up authentication (OWASP A07 / Zero Trust "verify explicitly").
 * Las acciones críticas (export GDPR, anonimizar, borrar) exigen reconfirmar
 * la contraseña aunque la sesión esté activa.
 */
class StepUpAuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'propietario', // cumple esAdmin() y esPropietario()
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    public function test_export_gdpr_rechaza_password_incorrecto(): void
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ListClientes::class)
            ->callTableAction('gdpr_exportar', $cliente, ['step_up_password' => 'password-equivocado'])
            ->assertHasTableActionErrors(['step_up_password']);

        // La exportación NO debe haberse registrado.
        $this->assertDatabaseMissing('audit_logs', ['accion' => 'GDPR_EXPORTAR']);
    }

    public function test_export_gdpr_acepta_password_correcto(): void
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ListClientes::class)
            ->callTableAction('gdpr_exportar', $cliente, ['step_up_password' => 'Test#Clave2026!'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('audit_logs', ['accion' => 'GDPR_EXPORTAR']);
    }

    public function test_anonimizar_rechaza_password_incorrecto(): void
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ListClientes::class)
            ->callTableAction('gdpr_anonimizar', $cliente, ['step_up_password' => 'mal'])
            ->assertHasTableActionErrors(['step_up_password']);

        // El cliente NO debe haber sido anonimizado.
        $this->assertNull($cliente->fresh()->anonimizado_en);
    }
}
