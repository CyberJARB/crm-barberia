<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leads / Interessenten — embudo de ventas multi-sector.
     * PII (telefono, mensaje) se cifra en el modelo (AES-256, OWASP A02).
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->text('telefono')->nullable();   // cifrado en el modelo
            $table->string('email')->nullable();
            $table->text('mensaje')->nullable();     // cifrado en el modelo
            $table->enum('fuente', ['web', 'whatsapp', 'telefono', 'instagram', 'empfehlung', 'n8n', 'manual'])
                ->default('manual');
            $table->enum('estado', ['nuevo', 'contactado', 'cualificado', 'propuesta', 'ganado', 'perdido'])
                ->default('nuevo');
            $table->decimal('valor_estimado', 10, 2)->nullable();
            $table->enum('motivo_perdida', ['precio', 'competencia', 'sin_respuesta', 'no_cualificado', 'otro'])
                ->nullable();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->boolean('mensajeria_optin')->default(false);
            $table->timestamp('contactado_en')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'estado']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'asignado_a']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
