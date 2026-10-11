<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISO 27001 A.8.3 — Preservación de datos
 * CWE-284 — Nunca borrar registros permanentemente
 * Soft deletes: los registros eliminados quedan en BD para auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = ['clientes', 'citas', 'servicios', 'personal', 'pagos'];

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = ['clientes', 'citas', 'servicios', 'personal', 'pagos'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
