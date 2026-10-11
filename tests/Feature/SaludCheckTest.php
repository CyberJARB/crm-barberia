<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Health check profundo para el load balancer: BD + cache + cola.
 */
class SaludCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_salud_devuelve_ok_con_dependencias_sanas(): void
    {
        $this->getJson('https://localhost/salud')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'db' => 'ok',
                'cache' => 'ok',
                'queue' => 'ok',
            ]);
    }

    public function test_salud_no_expone_detalles_internos(): void
    {
        $respuesta = $this->getJson('https://localhost/salud');

        // Solo claves de estado — sin versiones, rutas ni stack traces
        $this->assertSame(
            ['status', 'db', 'cache', 'queue'],
            array_keys($respuesta->json()),
        );
    }
}
