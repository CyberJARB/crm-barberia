<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rol del operador del SaaS (panel /super). No pertenece a ningún
     * tenant (tenant_id NULL) y no puede entrar al panel de los negocios.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `users` MODIFY `rol` ENUM('propietario','admin','recepcionista','readonly','superadmin') NOT NULL DEFAULT 'propietario'"
        );
    }

    public function down(): void
    {
        DB::table('users')->where('rol', 'superadmin')->delete();
        DB::statement(
            "ALTER TABLE `users` MODIFY `rol` ENUM('propietario','admin','recepcionista','readonly') NOT NULL DEFAULT 'propietario'"
        );
    }
};
