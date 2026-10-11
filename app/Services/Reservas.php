<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Personal;
use App\Models\Servicio;
use Carbon\Carbon;

/**
 * Lógica de reservas online — compartida por la API pública (v1) y la
 * página de reservas alojada (/reservar/{slug}).
 *
 * Requiere 'current_tenant_id' vinculado en el contenedor: todos los
 * modelos con BelongsToTenant quedan aislados automáticamente (OWASP A01).
 *
 * Modelo de capacidad: un slot está libre si el nº de citas activas que
 * lo solapan es menor que el nº de empleados trabajando en ese momento.
 * Así las citas sin personal asignado también ocupan plaza (no overbooking).
 */
class Reservas
{
    public const SLOT_MINUTOS = 30;

    public const ANTELACION_MINUTOS = 120;  // mínimo para citas de hoy

    public const DIAS_MAXIMO = 62;          // reservable ~2 meses

    public const ESTADOS_OCUPAN = ['pendiente', 'confirmada', 'en_progreso'];

    /**
     * Slots del día como [{hora:'11:00', disponible:bool}, ...].
     * Vacío si nadie trabaja ese día (cerrado).
     *
     * @return list<array{hora: string, disponible: bool}>
     */
    public function calcularSlots(Carbon $fecha, int $duracion): array
    {
        // Convención de la app (= panel Filament): 0=Montag ... 6=Sonntag.
        // OJO: el comentario de la migración dice 0=Dom, pero el panel y
        // HorarioPersonal::getNombreDiaAttribute usan 0=Lunes.
        $diaSemana = $fecha->dayOfWeekIso - 1;

        $personal = Personal::activos()
            ->disponiblesEl($fecha->toDateString()) // sin vacaciones/baja ese día
            ->whereHas('horarios', fn ($q) => $q->where('dia_semana', $diaSemana)->where('activo', true))
            ->with(['horarios' => fn ($q) => $q->where('dia_semana', $diaSemana)->where('activo', true)])
            ->get();

        if ($personal->isEmpty()) {
            return [];
        }

        $citas = Cita::whereDate('fecha', $fecha->toDateString())
            ->whereIn('estado', self::ESTADOS_OCUPAN)
            ->get(['hora_inicio', 'hora_fin']);

        $minimoHoy = $fecha->isToday()
            ? now()->addMinutes(self::ANTELACION_MINUTOS)->format('H:i')
            : null;

        $slots = [];

        foreach ($personal as $empleado) {
            $horario = $empleado->horarios->first();
            $inicio = $this->aMinutos(substr($horario->hora_inicio, 0, 5));
            $fin = $this->aMinutos(substr($horario->hora_fin, 0, 5));

            for ($t = $inicio; $t + $duracion <= $fin; $t += self::SLOT_MINUTOS) {
                $hora = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
                $slots[$hora]['capacidad'] = ($slots[$hora]['capacidad'] ?? 0) + 1;
            }
        }

        ksort($slots);

        $resultado = [];
        foreach ($slots as $hora => $info) {
            $slotInicio = $this->aMinutos($hora);
            $slotFin = $slotInicio + $duracion;

            $ocupadas = $citas->filter(function ($cita) use ($slotInicio, $slotFin) {
                $cInicio = $this->aMinutos(substr($cita->hora_inicio, 0, 5));
                $cFin = $this->aMinutos(substr($cita->hora_fin, 0, 5));

                return $cInicio < $slotFin && $cFin > $slotInicio;
            })->count();

            $disponible = $ocupadas < $info['capacidad'];

            if ($minimoHoy !== null && $hora < $minimoHoy) {
                $disponible = false;
            }

            $resultado[] = ['hora' => $hora, 'disponible' => $disponible];
        }

        return $resultado;
    }

    /**
     * Valida la fecha de reserva: ni pasada ni más allá del horizonte.
     * Devuelve el mensaje de error o null si es válida.
     */
    public function validarFecha(Carbon $fecha): ?string
    {
        if ($fecha->isPast() && ! $fecha->isToday()) {
            return 'La fecha ya pasó.';
        }
        if ($fecha->gt(today()->addDays(self::DIAS_MAXIMO))) {
            return 'Fecha demasiado lejana.';
        }

        return null;
    }

    /**
     * Crea la solicitud de cita (estado: pendiente) revalidando el slot
     * server-side (condición de carrera / manipulación). El observer de
     * Cita dispara confirmación + recordatorio según config del tenant.
     *
     * @param  array{nombre: string, apellido: string, telefono: string, email?: string|null, notas?: string|null}  $datosCliente
     *
     * @throws \DomainException si el slot ya no está disponible (code SLOT_OCUPADO)
     */
    public function crearReserva(
        Servicio $servicio,
        Carbon $fecha,
        string $hora,
        array $datosCliente,
        string $origen = 'Web',
    ): Cita {
        $slots = $this->calcularSlots($fecha, $servicio->duracion_minutos);
        $slot = collect($slots)->firstWhere('hora', $hora);

        if (! $slot || ! $slot['disponible']) {
            throw new \DomainException('SLOT_OCUPADO');
        }

        $nombreCompleto = trim($datosCliente['nombre'].' '.$datosCliente['apellido']);

        // El teléfono está cifrado no-determinista → el lookup es por nombre;
        // los duplicados se fusionan desde el panel.
        $cliente = Cliente::where('nombre', $nombreCompleto)->first()
            ?? Cliente::create([
                'nombre' => $nombreCompleto,
                'telefono' => $datosCliente['telefono'],
                'email' => $datosCliente['email'] ?? null,
                'activo' => true,
            ]);

        // Cliente recurrente sin email guardado y ahora nos lo da: completarlo
        if (empty($cliente->email) && ! empty($datosCliente['email'])) {
            $cliente->update(['email' => $datosCliente['email']]);
        }

        $horaFin = Carbon::createFromFormat('H:i', $hora)
            ->addMinutes($servicio->duracion_minutos)
            ->format('H:i');

        $cita = Cita::create([
            'cliente_id' => $cliente->id,
            'personal_id' => $this->personalLibre($fecha, $hora, $horaFin),
            'fecha' => $fecha->toDateString(),
            'hora_inicio' => $hora,
            'hora_fin' => $horaFin,
            'estado' => 'pendiente',
            'total' => $servicio->precio,
            'notas' => $this->notasConOrigen($origen, $datosCliente),
        ]);

        $cita->servicios()->attach($servicio->id, [
            'precio' => $servicio->precio,
            'duracion_minutos' => $servicio->duracion_minutos,
        ]);

        AuditLog::registrar('API_CITA_CREADA', 'Cita', $cita->id, null, [
            'origen' => $origen,
            'servicio' => $servicio->nombre,
            'fecha' => $fecha->toDateString(),
            'hora' => $hora,
        ]);

        return $cita;
    }

    /** Primer empleado que trabaja ese tramo, sin ausencia y sin cita solapada. */
    public function personalLibre(Carbon $fecha, string $horaInicio, string $horaFin): ?int
    {
        $diaSemana = $fecha->dayOfWeekIso - 1; // 0=Montag (convención del panel)
        $inicio = $this->aMinutos($horaInicio);
        $fin = $this->aMinutos($horaFin);

        $candidatos = Personal::activos()
            ->disponiblesEl($fecha->toDateString()) // sin vacaciones/baja ese día
            ->whereHas('horarios', fn ($q) => $q->where('dia_semana', $diaSemana)->where('activo', true))
            ->with(['horarios' => fn ($q) => $q->where('dia_semana', $diaSemana)->where('activo', true)])
            ->get();

        foreach ($candidatos as $empleado) {
            $horario = $empleado->horarios->first();
            if ($this->aMinutos(substr($horario->hora_inicio, 0, 5)) > $inicio
                || $this->aMinutos(substr($horario->hora_fin, 0, 5)) < $fin) {
                continue;
            }

            $solapada = Cita::where('personal_id', $empleado->id)
                ->whereDate('fecha', $fecha->toDateString())
                ->whereIn('estado', self::ESTADOS_OCUPAN)
                ->where('hora_inicio', '<', $horaFin.':00')
                ->where('hora_fin', '>', $horaInicio.':00')
                ->exists();

            if (! $solapada) {
                return $empleado->id;
            }
        }

        return null; // sin asignar — el salón lo asigna desde el panel
    }

    /**
     * @param  array{telefono: string, notas?: string|null}  $datos
     */
    private function notasConOrigen(string $origen, array $datos): string
    {
        $partes = ["[{$origen}]"];
        $partes[] = 'Tel: '.$datos['telefono'];
        if (! empty($datos['notas'])) {
            $partes[] = $datos['notas'];
        }

        return implode(' — ', $partes);
    }

    private function aMinutos(string $hora): int
    {
        [$h, $m] = explode(':', $hora);

        return (int) $h * 60 + (int) $m;
    }
}
