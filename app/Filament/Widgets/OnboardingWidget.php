<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CitaResource;
use App\Filament\Resources\PersonalResource;
use App\Filament\Resources\ServicioResource;
use App\Models\Cita;
use App\Models\HorarioPersonal;
use App\Models\Personal;
use App\Models\Servicio;
use Filament\Widgets\Widget;

/**
 * Schnellstart — checklist de arranque para negocios nuevos.
 * Aparece arriba del dashboard hasta completar los 4 pasos esenciales
 * (de esto depende que el trial convierta). Solo gestión (admin+).
 */
class OnboardingWidget extends Widget
{
    protected string $view = 'filament.widgets.onboarding';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        if ($user === null || ! $user->esAdmin()) {
            return false;
        }

        return collect(self::pasos())->contains(fn (array $paso): bool => ! $paso['hecho']);
    }

    /**
     * @return array<int, array{titulo: string, descripcion: string, hecho: bool, url: string, icono: string}>
     */
    public static function pasos(): array
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $hayServicios = Servicio::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('activo', true)->exists();

        $hayPersonal = Personal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('activo', true)->exists();

        $hayHorarios = HorarioPersonal::query()
            ->whereHas('personal', fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId))
            ->where('activo', true)
            ->exists();

        $hayCitas = Cita::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->exists();

        return [
            [
                'titulo' => 'Dienstleistungen anlegen',
                'descripcion' => 'Haarschnitt, Bart, Maniküre… mit Preis und Dauer.',
                'hecho' => $hayServicios,
                'url' => ServicioResource::getUrl('create'),
                'icono' => '✂️',
            ],
            [
                'titulo' => 'Mitarbeiter & Arbeitszeiten',
                'descripcion' => 'Team anlegen und Arbeitszeiten hinterlegen — sie steuern die Online-Buchung.',
                'hecho' => $hayPersonal && $hayHorarios,
                'url' => $hayPersonal
                    ? PersonalResource::getUrl('index')
                    : PersonalResource::getUrl('create'),
                'icono' => '👤',
            ],
            [
                'titulo' => 'Ersten Termin anlegen',
                'descripcion' => 'Probieren Sie den Kalender aus — oder warten Sie auf die erste Online-Buchung.',
                'hecho' => $hayCitas,
                'url' => CitaResource::getUrl('create'),
                'icono' => '📅',
            ],
            [
                'titulo' => 'Buchungsseite teilen',
                'descripcion' => 'Ihr Link für Instagram, Google und WhatsApp-Status.',
                'hecho' => $hayCitas, // se da por hecho con la actividad
                'url' => url('/reservar/'.auth()->user()->tenant->slug),
                'icono' => '🔗',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $pasos = self::pasos();
        $hechos = count(array_filter($pasos, fn (array $p): bool => $p['hecho']));

        return [
            'pasos' => $pasos,
            'hechos' => $hechos,
            'total' => count($pasos),
            'urlReserva' => url('/reservar/'.auth()->user()->tenant->slug),
        ];
    }
}
