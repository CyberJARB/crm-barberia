<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Venta de productos: stock atómico con lock (CWE-362), precio
 * server-side (CWE-602), método validado (CWE-639) y auditoría (OWASP A09).
 */
class ProduktVerkaufTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function producto(array $extra = []): Producto
    {
        return Producto::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'precio' => 12.50,
            'stock' => 10,
        ], $extra));
    }

    public function test_vender_decrementa_stock_y_crea_pago_con_precio_de_bd(): void
    {
        $producto = $this->producto();
        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);

        $pago = $producto->vender(3, 'tarjeta', $cliente);

        $this->assertSame(7, $producto->fresh()->stock);

        // 3 × 12,50 = 37,50 — calculado server-side
        $this->assertSame('37.50', (string) $pago->monto);
        $this->assertSame('tarjeta', $pago->metodo);
        $this->assertSame($producto->id, $pago->producto_id);
        $this->assertSame($cliente->id, $pago->cliente_id);
        $this->assertSame('Produktverkauf', $pago->notas);
        $this->assertStringContainsString('3x', $pago->referencia);

        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'PRODUKT_VERKAUFT',
            'entidad' => 'Producto',
            'entidad_id' => $producto->id,
        ]);
    }

    public function test_stock_insuficiente_rechaza_la_venta_sin_efectos(): void
    {
        $producto = $this->producto(['stock' => 2]);

        try {
            $producto->vender(5, 'efectivo');
            $this->fail('Se esperaba DomainException.');
        } catch (\DomainException) {
            // esperado
        }

        $this->assertSame(2, $producto->fresh()->stock);
        $this->assertSame(0, Pago::count());
    }

    public function test_producto_inactivo_no_se_puede_vender(): void
    {
        $producto = $this->producto(['activo' => false]);

        $this->expectException(\DomainException::class);

        $producto->vender(1, 'efectivo');
    }

    public function test_metodo_de_pago_invalido_es_rechazado(): void
    {
        $producto = $this->producto();

        $this->expectException(\InvalidArgumentException::class);

        $producto->vender(1, 'paypal');
    }

    public function test_cantidad_invalida_es_rechazada(): void
    {
        $producto = $this->producto();

        $this->expectException(\DomainException::class);

        $producto->vender(0, 'efectivo');
    }

    public function test_stock_bajo_se_detecta_correctamente(): void
    {
        $ok = $this->producto(['stock' => 10, 'stock_minimo' => 3]);
        $justo = $this->producto(['stock' => 3, 'stock_minimo' => 3]);
        $bajo = $this->producto(['stock' => 1, 'stock_minimo' => 3]);

        $this->assertFalse($ok->tieneStockBajo());
        $this->assertTrue($justo->tieneStockBajo());
        $this->assertTrue($bajo->tieneStockBajo());

        $this->assertSame(2, Producto::stockBajo()->count());
    }

    public function test_global_scope_oculta_productos_de_otros_tenants(): void
    {
        $this->producto();

        $otroTenant = Tenant::factory()->create();
        Producto::factory()->create(['tenant_id' => $otroTenant->id]);

        $this->assertSame(1, Producto::count());
    }
}
