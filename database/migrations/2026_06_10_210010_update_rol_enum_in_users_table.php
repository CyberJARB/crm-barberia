<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // OWASP A01 / ISO 27001 A.9.2 — RBAC roles: propietario > admin > recepcionista > readonly
        DB::statement(
            "ALTER TABLE `users` MODIFY `rol` ENUM('propietario','admin','recepcionista','readonly') NOT NULL DEFAULT 'propietario'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `users` MODIFY `rol` ENUM('propietario','staff') NOT NULL DEFAULT 'propietario'"
        );
    }
};
