<?php

namespace Tests\Feature;

use App\Filament\Super\Resources\TenantResource;
use App\Filament\Super\Resources\TenantResource\Pages\CreateTenant;
use App\Mail\AnuncioMail;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gestión completa desde /super: alta manual de negocios, comunicados
 * y offboarding — todo auditado.
 */
class SuperGestionTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create([
            'tenant_id' => null,
            'rol' => 'superadmin',
        ]);

        // Los componentes Livewire de /super deben resolverse en su panel
        Filament::setCurrentPanel('super');
    }

    public function test_alta_manual_crea_tenant_con_propietario_y_trial(): void
    {
        Livewire::actingAs($this->superadmin)
            ->test(CreateTenant::class)
            ->fillForm([
                'nombre' => 'Nagelstudio Glanz',
                'tipo' => 'unas',
                'ciudad' => 'Hamburg',
                'plan' => 'basico',
                'inhaber_nombre' => 'Maria Petrova',
                'inhaber_email' => 'maria@glanz-hamburg.de',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('nombre', 'Nagelstudio Glanz')->sole();
        $this->assertSame('nagelstudio-glanz', $tenant->slug);
        $this->assertTrue($tenant->onGenericTrial());

        $inhaber = User::where('email', 'maria@glanz-hamburg.de')->sole();
        $this->assertSame($tenant->id, $inhaber->tenant_id);
        $this->assertSame('propietario', $inhaber->rol);
        $this->assertTrue($inhaber->debe_cambiar_password);

        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'SAAS_TENANT_CREADO',
            'entidad' => 'Tenant',
            'entidad_id' => $tenant->id,
        ]);
    }

    public function test_email_de_inhaber_duplicado_es_rechazado(): void
    {
        $existente = User::factory()->create();

        Livewire::actingAs($this->superadmin)
            ->test(CreateTenant::class)
            ->fillForm([
                'nombre' => 'Otro Studio',
                'tipo' => 'salon',
                'plan' => 'basico',
                'inhaber_nombre' => 'Max',
                'inhaber_email' => $existente->email,
            ])
            ->call('create')
            ->assertHasFormErrors(['inhaber_email']);

        $this->assertDatabaseMissing('tenants', ['nombre' => 'Otro Studio']);
    }

    public function test_anuncio_va_solo_a_gestion_activa_y_se_audita(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $propietario = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);
        $recepcion = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'recepcionista']);

        $enviados = TenantResource::enviarAnuncio($tenant, 'Wartung am Sonntag', 'Das System ist kurz offline.');

        $this->assertSame(1, $enviados);

        Mail::assertQueued(AnuncioMail::class, fn (AnuncioMail $m): bool => $m->hasTo($propietario->email));
        Mail::assertNotQueued(AnuncioMail::class, fn (AnuncioMail $m): bool => $m->hasTo($recepcion->email));

        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'SAAS_ANUNCIO_ENVIADO',
            'entidad' => 'Tenant',
            'entidad_id' => $tenant->id,
        ]);
    }
}
