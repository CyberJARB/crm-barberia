<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('smtp_host')->nullable()->after('zona_horaria');
            $table->unsignedSmallInteger('smtp_port')->default(587)->after('smtp_host');
            $table->string('smtp_username')->nullable()->after('smtp_port');
            $table->text('smtp_password')->nullable()->after('smtp_username');  // cifrado con Crypt
            $table->string('smtp_encryption', 10)->default('tls')->after('smtp_password');
            $table->string('mail_from_address')->nullable()->after('smtp_encryption');
            $table->string('mail_from_name')->nullable()->after('mail_from_address');
            $table->boolean('email_confirmacion_activo')->default(true)->after('mail_from_name');
            $table->boolean('email_recordatorio_activo')->default(true)->after('email_confirmacion_activo');
            $table->boolean('email_followup_activo')->default(false)->after('email_recordatorio_activo');
            $table->unsignedTinyInteger('email_followup_horas')->default(2)->after('email_followup_activo');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                'smtp_encryption', 'mail_from_address', 'mail_from_name',
                'email_confirmacion_activo', 'email_recordatorio_activo',
                'email_followup_activo', 'email_followup_horas',
            ]);
        });
    }
};
