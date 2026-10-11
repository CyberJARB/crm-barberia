<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abwesenheiten (vacaciones, baja por enfermedad…) del personal.
     * Afectan a la disponibilidad pública y al formulario de citas.
     */
    public function up(): void
    {
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('motivo', ['vacaciones', 'enfermedad', 'otro'])->default('vacaciones');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'personal_id', 'fecha_inicio', 'fecha_fin'], 'ausencias_rango_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias');
    }
};
