<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Warteliste: clientes esperando hueco para un día concreto.
     */
    public function up(): void
    {
        Schema::create('lista_espera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->date('fecha_deseada');
            $table->enum('franja', ['manana', 'tarde', 'cualquiera'])->default('cualquiera');
            $table->enum('estado', ['esperando', 'avisado', 'convertido', 'expirado'])->default('esperando');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'fecha_deseada', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_espera');
    }
};
