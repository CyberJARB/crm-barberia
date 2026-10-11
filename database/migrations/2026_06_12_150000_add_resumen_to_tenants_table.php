<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resumen automático del negocio por email (opt-in por tenant):
     * cada mañana o cada 3 horas durante el horario comercial.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('resumen_activo')->default(false)->after('email_geburtstag_activo');
            $table->enum('resumen_frecuencia', ['manana', '3h'])->default('manana')->after('resumen_activo');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['resumen_activo', 'resumen_frecuencia']);
        });
    }
};
