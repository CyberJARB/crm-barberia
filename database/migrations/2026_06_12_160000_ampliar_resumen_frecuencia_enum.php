<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El negocio elige la cadencia de su update: mañana, 3h, 6h o 12h.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tenants MODIFY resumen_frecuencia ENUM('manana','3h','6h','12h') NOT NULL DEFAULT 'manana'");
    }

    public function down(): void
    {
        DB::table('tenants')->whereIn('resumen_frecuencia', ['6h', '12h'])->update(['resumen_frecuencia' => 'manana']);
        DB::statement("ALTER TABLE tenants MODIFY resumen_frecuencia ENUM('manana','3h') NOT NULL DEFAULT 'manana'");
    }
};
