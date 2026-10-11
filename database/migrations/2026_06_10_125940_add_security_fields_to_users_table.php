<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OWASP A07 / CWE-307 — Account lockout
 * OWASP A07 / CWE-613 — Session management
 * ISO 27001 A.9.4 — Access control
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Bloqueo de cuenta tras intentos fallidos (CWE-307)
            $table->unsignedSmallInteger('intentos_fallidos')->default(0)->after('activo');
            $table->timestamp('bloqueado_hasta')->nullable()->after('intentos_fallidos');
            $table->timestamp('ultimo_login')->nullable()->after('bloqueado_hasta');
            $table->string('ultimo_login_ip', 45)->nullable()->after('ultimo_login');

            // 2FA (OWASP A07 - MFA)
            $table->text('two_factor_secret')->nullable()->after('ultimo_login_ip');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            // Forzar cambio de contraseña
            $table->boolean('debe_cambiar_password')->default(false)->after('two_factor_confirmed_at');
            $table->timestamp('password_changed_at')->nullable()->after('debe_cambiar_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'intentos_fallidos', 'bloqueado_hasta', 'ultimo_login',
                'ultimo_login_ip', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'debe_cambiar_password', 'password_changed_at',
            ]);
        });
    }
};
