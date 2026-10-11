<?php

namespace App\Support;

/**
 * Catálogo central de planes de suscripción.
 *
 * Única fuente de verdad para nombre, precio y features de cada plan.
 * Los Stripe Price IDs viven en config('cashier.prices') — OWASP A04 /
 * CWE-639: todo plan recibido del cliente se valida contra esta allowlist.
 */
final class Planes
{
    /** Datos de presentación de cada plan (claves = enum `tenants.plan`). */
    public const PLANES = [
        'basico' => [
            'nombre' => 'Basis',
            'precio' => 29,
            'destacado' => false,
            'features' => [
                '1 Standort',
                'Bis zu 3 Mitarbeiter',
                'Terminverwaltung',
                'Kundenprofile',
                'E-Mail-Benachrichtigungen',
            ],
        ],
        'profesional' => [
            'nombre' => 'Professionell',
            'precio' => 59,
            'destacado' => true,
            'features' => [
                'Alles aus Basis',
                'Bis zu 10 Mitarbeiter',
                'WhatsApp-Integration',
                'Berichte & Statistiken',
                'Priority-Support',
            ],
        ],
        'premium' => [
            'nombre' => 'Premium',
            'precio' => 99,
            'destacado' => false,
            'features' => [
                'Alles aus Professionell',
                'Unbegrenzte Mitarbeiter',
                'Mehrere Standorte',
                'API-Zugang',
                'Dedicated Support',
            ],
        ],
    ];

    /**
     * Límites técnicos de cada plan (gating server-side, OWASP A04).
     * max_personal = null → ilimitado.
     */
    public const LIMITES = [
        'basico' => [
            'max_personal' => 3,
            'whatsapp' => false,
            'berichte' => false,
            'api' => false,
        ],
        'profesional' => [
            'max_personal' => 10,
            'whatsapp' => true,
            'berichte' => true,
            'api' => false,
        ],
        'premium' => [
            'max_personal' => null,
            'whatsapp' => true,
            'berichte' => true,
            'api' => true,
        ],
    ];

    /** @return array{max_personal: int|null, whatsapp: bool, berichte: bool, api: bool} */
    public static function limites(string $plan): array
    {
        // Plan desconocido → límites del plan más restrictivo (fail-closed)
        return self::LIMITES[$plan] ?? self::LIMITES['basico'];
    }

    /** @return array<string, array{nombre: string, precio: int, destacado: bool, features: array<int, string>}> */
    public static function todos(): array
    {
        return self::PLANES;
    }

    /** @return array<int, string> Claves válidas para validación (allowlist) */
    public static function claves(): array
    {
        return array_keys(self::PLANES);
    }

    /** Stripe Price ID configurado para un plan (null si no está configurado). */
    public static function precioId(string $plan): ?string
    {
        $precio = config('cashier.prices.'.$plan);

        return is_string($precio) && $precio !== '' ? $precio : null;
    }

    /** Mapea un Stripe Price ID de vuelta al nombre interno del plan. */
    public static function desdePrecioId(?string $priceId): ?string
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        foreach (self::claves() as $plan) {
            if (self::precioId($plan) === $priceId) {
                return $plan;
            }
        }

        return null;
    }

    /** Nombre comercial (alemán) de un plan interno. */
    public static function nombre(?string $plan): string
    {
        return self::PLANES[$plan]['nombre'] ?? ($plan ?? '—');
    }
}
