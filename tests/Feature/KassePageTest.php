<?php

namespace Tests\Feature;

use App\Filament\Pages\KassePage;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Página Tagesabschluss: desglose diario por método de pago,
 * solo con pagos del propio tenant (OWASP A01) y sin días futuros.
 */
class KassePageTest extends TestCase
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
            'rol' => 'propietario',
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function urlKasse(): string
    {
        return str_replace('http://', 'https://', route('filament.admin.pages.kasse'));
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get($this->urlKasse())
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_muestra_los_pagos_de_hoy_con_desglose(): void
    {
        $cliente = Cliente::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Max Mustermann',
        ]);

        Pago::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'monto' => 45.50,
            'metodo' => 'efectivo',
        ]);
        Pago::factory()->create([
            'tenant_id' => $this->tenant->id,
            'monto' => 30.00,
            'metodo' => 'tarjeta',
        ]);

        $this->actingAs($this->user)
            ->get($this->urlKasse())
            ->assertOk()
            ->assertSee('Tagesabschluss')
            ->assertSee('Max Mustermann')
            ->assertSee('Barzahlung')
            ->assertSee('Kartenzahlung')
            // Gesamt: 75,50 €
            ->assertSee('75,50');
    }

    public function test_no_muestra_pagos_de_otro_tenant(): void
    {
        $otroTenant = Tenant::factory()->create();
        $ajeno = Cliente::factory()->create([
            'tenant_id' => $otroTenant->id,
            'nombre' => 'Fremder Kunde',
        ]);
        Pago::factory()->create([
            'tenant_id' => $otroTenant->id,
            'cliente_id' => $ajeno->id,
            'monto' => 99.99,
            'metodo' => 'efectivo',
        ]);

        $this->actingAs($this->user)
            ->get($this->urlKasse())
            ->assertOk()
            ->assertDontSee('Fremder Kunde')
            ->assertDontSee('99,99');
    }

    public function test_un_dia_futuro_se_normaliza_a_hoy(): void
    {
        $page = new KassePage;
        $page->fecha = now()->addWeek()->toDateString();

        $this->assertTrue($page->dia()->isToday());
    }

    public function test_fecha_corrupta_se_normaliza_a_hoy(): void
    {
        $page = new KassePage;
        $page->fecha = "'; DROP TABLE pagos; --";

        $this->assertTrue($page->dia()->isToday());
    }
}
