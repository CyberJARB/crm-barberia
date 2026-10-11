<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OWASP A01 / CWE-285 — Insecure Direct Object Reference
 * CWE-862 — Missing Authorization
 * Verifica que las políticas RBAC se aplican correctamente.
 */
class RbacPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $propietario;

    private User $recepcionista;

    private User $readonly;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $this->tenant->id);

        $this->propietario = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'propietario',
        ]);

        $this->recepcionista = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'recepcionista',
        ]);

        $this->readonly = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'readonly',
        ]);

        $this->cliente = Cliente::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // === Propietario puede todo ===

    public function test_propietario_puede_crear_cliente(): void
    {
        $this->assertTrue($this->propietario->can('create', Cliente::class));
    }

    public function test_propietario_puede_editar_cliente(): void
    {
        $this->assertTrue($this->propietario->can('update', $this->cliente));
    }

    public function test_propietario_puede_borrar_cliente(): void
    {
        $this->assertTrue($this->propietario->can('delete', $this->cliente));
    }

    // === Recepcionista puede crear y ver, no borrar ===

    public function test_recepcionista_puede_crear_cliente(): void
    {
        $this->assertTrue($this->recepcionista->can('create', Cliente::class));
    }

    public function test_recepcionista_no_puede_borrar_cliente(): void
    {
        $this->assertFalse($this->recepcionista->can('delete', $this->cliente));
    }

    // === Readonly solo puede ver ===

    public function test_readonly_no_puede_crear_cliente(): void
    {
        $this->assertFalse($this->readonly->can('create', Cliente::class));
    }

    public function test_readonly_no_puede_editar_cliente(): void
    {
        $this->assertFalse($this->readonly->can('update', $this->cliente));
    }

    // === IDOR: usuario de otro tenant no puede acceder ===

    public function test_usuario_otro_tenant_no_puede_ver_cliente(): void
    {
        $otroTenant = Tenant::factory()->create();
        $otroUser = User::factory()->create([
            'tenant_id' => $otroTenant->id,
            'rol' => 'propietario',
        ]);

        $this->assertFalse($otroUser->can('view', $this->cliente));
    }

    public function test_usuario_otro_tenant_no_puede_editar_cliente(): void
    {
        $otroTenant = Tenant::factory()->create();
        $otroUser = User::factory()->create([
            'tenant_id' => $otroTenant->id,
            'rol' => 'propietario',
        ]);

        $this->assertFalse($otroUser->can('update', $this->cliente));
    }

    // === Solo propietario puede borrar pagos (rastro de auditoría financiera) ===

    public function test_recepcionista_no_puede_borrar_pagos(): void
    {
        $pago = Pago::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertFalse($this->recepcionista->can('delete', $pago));
    }

    public function test_propietario_puede_borrar_pagos(): void
    {
        $pago = Pago::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertTrue($this->propietario->can('delete', $pago));
    }
}
