<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El CRM se vende a varios tipos de negocio de belleza:
     * barberías, salones de mujer, estudios de uñas, spas…
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tenants MODIFY tipo ENUM('barberia','salon','unas','spa','restaurante','otro') NOT NULL DEFAULT 'barberia'");
    }

    public function down(): void
    {
        DB::table('tenants')->whereIn('tipo', ['unas', 'spa', 'otro'])->update(['tipo' => 'salon']);
        DB::statement("ALTER TABLE tenants MODIFY tipo ENUM('barberia','salon','restaurante') NOT NULL DEFAULT 'barberia'");
    }
};
