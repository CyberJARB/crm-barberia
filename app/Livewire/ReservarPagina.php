<?php

namespace App\Livewire;

use App\Models\Servicio;
use App\Models\Tenant;
use App\Services\Reservas;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Página pública de reservas alojada: /reservar/{slug}.
 *
 * Para negocios sin web propia — el CRM les da su página de reservas.
 * Misma lógica de capacidad que la API v1 (App\Services\Reservas).
 *
 * OWASP A01 — el tenant se resuelve por slug y queda fijado con #[Locked];
 * 'current_tenant_id' se vincula en mount() y en cada hydrate() para que
 * el global scope aísle todos los queries.
 *
 * @property-read Tenant $tenant Computed property de Livewire (getTenantProperty)
 */
class ReservarPagina extends Component
{
    #[Locked]
    public int $tenantId;

    #[Locked]
    public string $slug;

    // === Selección ===
    public ?int $servicioId = null;

    public string $fecha = '';

    public ?string $hora = null;

    // === Datos del cliente ===
    public string $nombre = '';

    public string $apellido = '';

    public string $telefono = '';

    public string $email = '';

    public string $notas = '';

    /** Pantalla de éxito (null = formulario). */
    public ?array $exito = null;

    public function mount(string $slug): void
    {
        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->where('activo', true)
            ->firstOrFail();

        $this->tenantId = $tenant->id;
        $this->slug = $slug;
        $this->fecha = today()->toDateString();

        $this->vincularTenant();
    }

    /** Livewire re-crea el request en cada interacción: re-vincular siempre. */
    public function hydrate(): void
    {
        $this->vincularTenant();
    }

    private function vincularTenant(): void
    {
        app()->instance('current_tenant_id', $this->tenantId);
    }

    // === Datos para la vista =========================================

    public function getTenantProperty(): Tenant
    {
        return Tenant::findOrFail($this->tenantId);
    }

    /** @return Collection<int, Servicio> */
    public function getServiciosProperty()
    {
        return Servicio::activos()->orderBy('nombre')->get();
    }

    /**
     * Próximos 14 días para el selector.
     *
     * @return list<array{valor: string, dia: string, num: string, hoy: bool}>
     */
    public function getDiasProperty(): array
    {
        return collect(range(0, 13))
            ->map(function (int $i): array {
                $dia = today()->addDays($i);

                return [
                    'valor' => $dia->toDateString(),
                    'dia' => $dia->locale('de')->isoFormat('dd'),
                    'num' => $dia->format('d.m.'),
                    'hoy' => $i === 0,
                ];
            })
            ->all();
    }

    /** @return list<array{hora: string, disponible: bool}> */
    public function getSlotsProperty(): array
    {
        $servicio = $this->servicioSeleccionado();
        $fecha = $this->fechaValida();

        if ($servicio === null || $fecha === null) {
            return [];
        }

        return app(Reservas::class)->calcularSlots($fecha, $servicio->duracion_minutos);
    }

    // === Interacciones ================================================

    public function elegirServicio(int $id): void
    {
        // findOrFail con global scope → no se puede elegir de otro tenant
        $this->servicioId = Servicio::activos()->findOrFail($id)->id;
        $this->hora = null;
    }

    public function elegirFecha(string $fecha): void
    {
        // CWE-20 — valor de cliente: validar formato y rango
        try {
            $dia = Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if (app(Reservas::class)->validarFecha($dia) !== null) {
            return;
        }

        $this->fecha = $dia->toDateString();
        $this->hora = null;
    }

    public function elegirHora(string $hora): void
    {
        if (preg_match('/^\d{2}:\d{2}$/', $hora) !== 1) {
            return;
        }

        $this->hora = $hora;
    }

    public function reservar(): void
    {
        $this->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'apellido' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'telefono' => ['required', 'string', 'max:30', 'regex:/^[\d\s+\-()\/]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [], [
            'nombre' => 'Vorname',
            'apellido' => 'Nachname',
            'telefono' => 'Telefon',
            'email' => 'E-Mail',
            'notas' => 'Anmerkungen',
        ]);

        $servicio = $this->servicioSeleccionado();
        $fecha = $this->fechaValida();

        if ($servicio === null || $fecha === null || $this->hora === null) {
            $this->addError('hora', 'Bitte wählen Sie Dienstleistung, Datum und Uhrzeit.');

            return;
        }

        // CWE-770 — máx. 5 reservas/hora por IP (anti-spam sin CAPTCHA)
        $claveLimite = 'reserva-online:'.request()->ip();
        if (RateLimiter::tooManyAttempts($claveLimite, 5)) {
            $this->addError('hora', 'Zu viele Buchungen. Bitte versuchen Sie es später erneut oder rufen Sie uns an.');

            return;
        }

        try {
            $cita = app(Reservas::class)->crearReserva($servicio, $fecha, $this->hora, [
                'nombre' => strip_tags(trim($this->nombre)),
                'apellido' => strip_tags(trim($this->apellido)),
                'telefono' => trim($this->telefono),
                'email' => $this->email !== '' ? strtolower(trim($this->email)) : null,
                'notas' => $this->notas !== '' ? strip_tags($this->notas) : null,
            ], 'Online');
        } catch (\DomainException) {
            $this->hora = null;
            $this->addError('hora', 'Dieser Termin wurde gerade vergeben — bitte wählen Sie eine andere Uhrzeit.');

            return;
        }

        RateLimiter::hit($claveLimite, 3600);

        $this->exito = [
            'servicio' => $servicio->nombre,
            'fecha' => $fecha->locale('de')->isoFormat('dddd, DD.MM.YYYY'),
            'hora' => substr((string) $cita->hora_inicio, 0, 5),
            'email' => $this->email !== '',
        ];
    }

    public function otraReserva(): void
    {
        $this->reset(['servicioId', 'hora', 'nombre', 'apellido', 'telefono', 'email', 'notas', 'exito']);
        $this->fecha = today()->toDateString();
    }

    // === Helpers ======================================================

    private function servicioSeleccionado(): ?Servicio
    {
        return $this->servicioId !== null
            ? Servicio::activos()->find($this->servicioId)
            : null;
    }

    private function fechaValida(): ?Carbon
    {
        try {
            $dia = Carbon::createFromFormat('Y-m-d', $this->fecha)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return app(Reservas::class)->validarFecha($dia) === null ? $dia : null;
    }

    public function render(): View
    {
        return view('livewire.reservar-pagina')
            ->layout('layouts.publico', ['title' => 'Termin buchen — '.$this->tenant->nombre]);
    }
}
