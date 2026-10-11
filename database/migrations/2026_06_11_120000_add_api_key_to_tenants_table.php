<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API pública de reservas (sitio web del tenant → CRM).
     * OWASP A02 — la clave se guarda como hash SHA-256, nunca en claro:
     * un volcado de la BD no revela claves utilizables.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('api_key_hash', 64)->nullable()->unique()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('api_key_hash');
        });
    }
};
