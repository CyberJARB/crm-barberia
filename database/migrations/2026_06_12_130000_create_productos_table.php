<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Productos de venta (pomadas, champús…) con control de stock.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->string('categoria', 100)->nullable();
            $table->decimal('precio', 8, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('stock_minimo')->default(3);
            $table->boolean('activo')->default(true);
            $table->text('descripcion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'activo']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            // Trazabilidad de ventas de producto (OWASP A09)
            $table->foreignId('producto_id')->nullable()->after('vale_id')
                ->constrained('productos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
        });

        Schema::dropIfExists('productos');
    }
};
