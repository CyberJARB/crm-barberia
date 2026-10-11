<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite registrar pagos con vale regalo: nuevo valor 'vale' en el enum
     * y FK al vale canjeado para trazabilidad completa (OWASP A09).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE pagos MODIFY metodo ENUM('efectivo','tarjeta','transferencia','bizum','vale') NOT NULL DEFAULT 'efectivo'");

        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('vale_id')->nullable()->after('cliente_id')
                ->constrained('vales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vale_id');
        });

        DB::table('pagos')->where('metodo', 'vale')->update(['metodo' => 'efectivo']);
        DB::statement("ALTER TABLE pagos MODIFY metodo ENUM('efectivo','tarjeta','transferencia','bizum') NOT NULL DEFAULT 'efectivo'");
    }
};
