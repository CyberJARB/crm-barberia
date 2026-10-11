<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Página Abonnement: renderiza el rediseño y solo es accesible
 * para el propietario (OWASP A01).
 */
class SuscripcionPageTest extends TestCase
{
    use RefreshDatabase;

    private function url(): string
    {
        return str_replace('http://', 'https://', route('filament.admin.pages.suscripcion'));
    }

    public function test_propietario_ve_la_pagina_con_planes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        app()->instance('current_tenant_id', $tenant->id);

        $this->actingAs($user)
            ->get($this->url())
            ->assertOk()
            ->assertSee('Plan wählen')
            ->assertSee('Zahlungsmethode')
            ->assertSee('abo-hero', false); // CSS del rediseño presente
    }

    public function test_recepcionista_no_accede(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'recepcionista']);

        app()->instance('current_tenant_id', $tenant->id);

        $this->actingAs($user)
            ->get($this->url())
            ->assertForbidden();
    }
}
