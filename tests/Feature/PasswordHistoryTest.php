<?php

namespace Tests\Feature;

use App\Models\PasswordHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\NoPasswordReuse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * CWE-521 / NIST SP 800-63B — Password history
 * Verifica que no se puede reutilizar una contraseña reciente.
 */
class PasswordHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_guardar_en_historial_crea_registro(): void
    {
        $this->user->guardarEnHistorial();

        $this->assertDatabaseHas('password_histories', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_rechaza_reutilizacion_de_contrasena_reciente(): void
    {
        // Guardar la contraseña actual en el historial
        PasswordHistory::create([
            'user_id' => $this->user->id,
            'password' => Hash::make('MiPassword@123!'),
        ]);

        $validator = Validator::make(
            ['password' => 'MiPassword@123!'],
            ['password' => [new NoPasswordReuse($this->user->id)]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_acepta_contrasena_nueva(): void
    {
        PasswordHistory::create([
            'user_id' => $this->user->id,
            'password' => Hash::make('ContraseñaVieja@123!'),
        ]);

        $validator = Validator::make(
            ['password' => 'ContraseñaNueva@456!'],
            ['password' => [new NoPasswordReuse($this->user->id)]]
        );

        $this->assertFalse($validator->fails());
    }

    public function test_mantiene_maximo_diez_registros(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->user->update(['password' => Hash::make("Pass{$i}@Test!")]);
            $this->user->guardarEnHistorial();
        }

        $count = PasswordHistory::where('user_id', $this->user->id)->count();
        $this->assertLessThanOrEqual(10, $count);
    }
}
