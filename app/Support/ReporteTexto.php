<?php

namespace App\Support;

/**
 * Traducciones al alemán y formato numérico alemán para los reportes
 * (PDF + Excel). Centralizado para evitar duplicación entre exports,
 * controller y vistas Blade.
 */
final class ReporteTexto
{
    private const METODOS = [
        'efectivo' => 'Barzahlung',
        'tarjeta' => 'Kartenzahlung',
        'transferencia' => 'Überweisung',
        'bizum' => 'Bizum',
    ];

    /**
     * Métodos que solo crea el sistema (no seleccionables a mano en el
     * modal de cobro): el canje de vale pasa por Cita::cobrar() con Vale.
     */
    private const METODOS_SISTEMA = [
        'vale' => 'Gutschein',
    ];

    private const ESTADOS = [
        'pendiente' => 'Ausstehend',
        'confirmada' => 'Bestätigt',
        'en_progreso' => 'In Bearbeitung',
        'completada' => 'Abgeschlossen',
        'cancelada' => 'Storniert',
        'no_show' => 'Nicht erschienen',
    ];

    /**
     * Mapa completo valor BD => etiqueta alemana (para Selects/filtros de Filament).
     *
     * @return array<string, string>
     */
    public static function metodos(): array
    {
        return self::METODOS;
    }

    /**
     * @return array<string, string>
     */
    public static function estados(): array
    {
        return self::ESTADOS;
    }

    public static function metodo(?string $metodo): string
    {
        return self::METODOS[$metodo]
            ?? self::METODOS_SISTEMA[$metodo]
            ?? ucfirst((string) $metodo);
    }

    public static function estado(?string $estado): string
    {
        return self::ESTADOS[$estado] ?? ucfirst((string) $estado);
    }

    /**
     * Color Filament del badge de estado de cita (único sitio del mapa).
     */
    public static function estadoColor(?string $estado): string
    {
        return match ($estado) {
            'pendiente' => 'warning',
            'confirmada' => 'success',
            'en_progreso' => 'info',
            'completada' => 'primary',
            default => 'danger',
        };
    }

    /**
     * Formato numérico alemán: 1.234,56 €
     */
    public static function euro(float|int|string|null $monto): string
    {
        return number_format((float) $monto, 2, ',', '.').' €';
    }

    public static function fecha(?\DateTimeInterface $fecha): string
    {
        return $fecha?->format('d.m.Y') ?? '—';
    }
}
