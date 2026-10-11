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
        Schema::create('recordatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();
            $table->enum('tipo', ['whatsapp', 'email'])->default('whatsapp');
            $table->timestamp('programado_para');
            $table->timestamp('enviado_at')->nullable();
            $table->enum('estado', ['pendiente', 'enviado', 'fallido'])->default('pendiente');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['estado', 'programado_para']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recordatorios');
    }
};
