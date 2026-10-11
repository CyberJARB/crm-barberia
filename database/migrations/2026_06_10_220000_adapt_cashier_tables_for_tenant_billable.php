<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier con Tenant como modelo billable.
 *
 * El billable de este SaaS es App\Models\Tenant (la barbería), por lo que
 * Cashier resuelve la FK de `subscriptions` como `tenant_id` (getForeignKey
 * del modelo). Las migraciones stock de Cashier crean `user_id`, así que:
 *
 *  - Instalación existente (tablas creadas desde vendor): renombrar
 *    user_id -> tenant_id y recrear el índice compuesto.
 *  - Instalación nueva / BD de tests: crear las tablas directamente con
 *    tenant_id (las migraciones de vendor no viven en database/migrations).
 *
 * Nota: NO se añaden columnas stripe_* a `users` (create_customer_columns
 * de Cashier): el customer de Stripe es el tenant, no el usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        // === subscriptions ===
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->string('type');
                $table->string('stripe_id')->unique();
                $table->string('stripe_status');
                $table->string('stripe_price')->nullable();
                $table->integer('quantity')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'stripe_status']);
            });
        } elseif (Schema::hasColumn('subscriptions', 'user_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'stripe_status']);
                $table->renameColumn('user_id', 'tenant_id');
            });

            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['tenant_id', 'stripe_status']);
            });
        }

        // === subscription_items (incluye columnas meter de Cashier >= 15.4) ===
        if (! Schema::hasTable('subscription_items')) {
            Schema::create('subscription_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id');
                $table->string('stripe_id')->unique();
                $table->string('stripe_product');
                $table->string('stripe_price');
                $table->string('meter_id')->nullable();
                $table->integer('quantity')->nullable();
                $table->string('meter_event_name')->nullable();
                $table->timestamps();

                $table->index(['subscription_id', 'stripe_price']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'tenant_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'stripe_status']);
                $table->renameColumn('tenant_id', 'user_id');
            });

            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['user_id', 'stripe_status']);
            });
        }
    }
};
