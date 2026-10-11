<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vales regalo (Gutscheine). El saldo vive en BD y solo se modifica
     * server-side dentro de transacciones con lock (CWE-362 race condition).
     */
    public function up(): void
    {
        Schema::create('vales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->decimal('valor_inicial', 8, 2);
            $table->decimal('saldo', 8, 2);
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            // BGB §195: prescripción estándar de 3 años en Alemania
            $table->date('valido_hasta')->nullable();
            $table->timestamp('anulado_en')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // El código solo debe ser único dentro del tenant (OWASP A01)
            $table->unique(['tenant_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vales');
    }
};
