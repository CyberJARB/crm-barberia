<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Opt-in por tenant (DSGVO: el saludo usa la base legal del
            // interés legítimo — cada negocio decide si lo activa)
            $table->boolean('email_geburtstag_activo')->default(false)
                ->after('email_followup_horas');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('email_geburtstag_activo');
        });
    }
};
