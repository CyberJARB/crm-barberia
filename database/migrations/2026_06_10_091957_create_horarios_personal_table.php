<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('horarios_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->tinyInteger('dia_semana')->comment('0=Dom, 1=Lun, ..., 6=Sab');
            $table->time('hora_inicio')->default('09:00:00');
            $table->time('hora_fin')->default('18:00:00');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['personal_id', 'dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios_personal');
    }
};
