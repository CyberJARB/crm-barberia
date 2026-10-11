<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recordatorios por WhatsApp/SMS — solo transaccionales.
     *
     * Cumplimiento UE/Alemania:
     * - DSGVO Art. 6(1)(b): recordatorios de cita = ejecución del contrato
     * - §7 UWG: aun así se exige consentimiento explícito del cliente para
     *   el canal (opt-in con timestamp como prueba, revocable en todo momento)
     * - DSGVO Art. 5(1)(c): minimización — el mensaje solo lleva fecha/hora
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('mensajeria_optin')->default(false)->after('alergias');
            $table->timestamp('mensajeria_optin_en')->nullable()->after('mensajeria_optin');
            $table->enum('mensajeria_canal', ['whatsapp', 'sms'])->default('whatsapp')->after('mensajeria_optin_en');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('mensajeria_activa')->default(false)->after('resumen_frecuencia');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['mensajeria_optin', 'mensajeria_optin_en', 'mensajeria_canal']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('mensajeria_activa');
        });
    }
};
