<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use App\Services\Reservas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pública de reservas — consumida por el sitio web del tenant.
 *
 * El middleware AutenticarTenantApi ya resolvió el tenant y vinculó
 * 'current_tenant_id', por lo que todos los modelos con BelongsToTenant
 * quedan automáticamente aislados (OWASP A01).
 *
 * La lógica de slots/capacidad vive en App\Services\Reservas (compartida
 * con la página de reservas alojada /reservar/{slug}).
 */
class ReservaPublicaController extends Controller
{
    public function __construct(private readonly Reservas $reservas) {}

    /** GET /api/v1/servicios — catálogo activo del tenant */
    public function servicios(): JsonResponse
    {
        $servicios = Servicio::activos()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'duracion_minutos', 'precio']);

        return response()->json(['servicios' => $servicios]);
    }

    /** GET /api/v1/disponibilidad?fecha=Y-m-d&servicio_id=N */
    public function disponibilidad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'servicio_id' => ['required', 'integer'],
        ]);

        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();

        if ($error = $this->reservas->validarFecha($fecha)) {
            return response()->json(['message' => $error], 422);
        }

        // findOrFail con global scope → 404 si el servicio es de otro tenant
        $servicio = Servicio::activos()->findOrFail($datos['servicio_id']);

        $slots = $this->reservas->calcularSlots($fecha, $servicio->duracion_minutos);

        return response()->json([
            'fecha' => $fecha->toDateString(),
            'abierto' => $slots !== [],
            'slots' => $slots,
        ]);
    }

    /** POST /api/v1/citas — crea una solicitud (estado: pendiente) */
    public function crearCita(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['required', 'string', 'max:80'],
            'telefono' => ['required', 'string', 'max:30'],
            'servicio_id' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'notas' => ['nullable', 'string', 'max:500'],
            'lang' => ['nullable', 'in:de,fr,en'],
        ]);

        $servicio = Servicio::activos()->findOrFail($datos['servicio_id']);
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();

        try {
            $cita = $this->reservas->crearReserva(
                $servicio,
                $fecha,
                $datos['hora'],
                $datos,
                'Web'.(isset($datos['lang']) ? ' · '.$datos['lang'] : ''),
            );
        } catch (\DomainException) {
            return response()->json([
                'message' => 'El horario ya no está disponible.',
                'code' => 'SLOT_OCUPADO',
            ], 422);
        }

        return response()->json([
            'id' => $cita->id,
            'estado' => $cita->estado,
            'fecha' => $fecha->toDateString(),
            'hora' => $datos['hora'],
        ], 201);
    }
}
