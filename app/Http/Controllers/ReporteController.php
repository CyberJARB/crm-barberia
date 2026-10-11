<?php

namespace App\Http\Controllers;

use App\Exports\CitasExport;
use App\Exports\ClientesExport;
use App\Exports\IngresosExport;
use App\Exports\PersonalExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga de reportes en PDF / Excel.
 *
 * OWASP A01 / CWE-639 — solo administradores; todos los datos se
 * obtienen filtrados por el tenant del usuario autenticado.
 * CWE-1284 — el rango de fechas se limita a 366 días como máximo.
 */
class ReporteController extends Controller
{
    private const MAX_DIAS = 366;

    public function descargar(Request $request): Response
    {
        $user = $request->user();

        // OWASP A01 — solo admin/propietario del tenant
        abort_unless($user !== null && $user->esAdmin() && $user->tenant_id !== null, 403);

        // OWASP A04 — gating por plan (defensa en profundidad: la página
        // ya lo oculta, pero la URL directa también debe negarse)
        abort_unless($user->tenant?->permite('berichte') ?? false, 403);

        $validated = $request->validate([
            'tipo_reporte' => ['required', 'string', 'in:ingresos,citas,clientes,personal'],
            'fecha_inicio' => ['required_unless:tipo_reporte,clientes', 'nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['required_unless:tipo_reporte,clientes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'formato' => ['required', 'string', 'in:pdf,excel'],
        ]);

        $tenantId = (int) $user->tenant_id;
        $tenant = $user->tenant;

        $inicio = isset($validated['fecha_inicio'])
            ? Carbon::createFromFormat('Y-m-d', $validated['fecha_inicio'])->startOfDay()
            : now()->startOfMonth();
        $hasta = isset($validated['fecha_hasta'])
            ? Carbon::createFromFormat('Y-m-d', $validated['fecha_hasta'])->endOfDay()
            : now()->endOfDay();

        // CWE-1284 — validación de rango máximo (evita exports masivos / DoS)
        abort_if(
            $inicio->diffInDays($hasta) > self::MAX_DIAS,
            422,
            'Der Zeitraum darf maximal 366 Tage betragen.'
        );

        $desde = $inicio->toDateString();
        $bis = $hasta->toDateString();
        $sufijo = "{$desde}_{$bis}";
        $periodo = $inicio->format('d.m.Y').' – '.$hasta->format('d.m.Y');

        return match ($validated['tipo_reporte']) {
            'ingresos' => $validated['formato'] === 'pdf'
                ? $this->pdfIngresos($tenant, $tenantId, $desde, $bis, $periodo, $sufijo)
                : Excel::download(new IngresosExport($tenantId, $desde, $bis), "umsatzbericht_{$sufijo}.xlsx"),

            'citas' => $validated['formato'] === 'pdf'
                ? $this->pdfCitas($tenant, $tenantId, $desde, $bis, $periodo, $sufijo)
                : Excel::download(new CitasExport($tenantId, $desde, $bis), "terminbericht_{$sufijo}.xlsx"),

            // Kundenliste: solo Excel (lista completa, sin filtro de fechas)
            'clientes' => Excel::download(new ClientesExport($tenantId), 'kundenliste_'.now()->toDateString().'.xlsx'),

            'personal' => $validated['formato'] === 'pdf'
                ? $this->pdfPersonal($tenant, $tenantId, $desde, $bis, $periodo, $sufijo)
                : Excel::download(new PersonalExport($tenantId, $desde, $bis), "mitarbeiterbericht_{$sufijo}.xlsx"),

            // Inalcanzable: 'in:' ya validó el valor (defensa en profundidad)
            default => abort(422),
        };
    }

    private function pdfIngresos(object $tenant, int $tenantId, string $desde, string $bis, string $periodo, string $sufijo): Response
    {
        $pagos = (new IngresosExport($tenantId, $desde, $bis))->query()->get();

        return Pdf::loadView('reportes.ingresos-pdf', [
            'tenant' => $tenant,
            'periodo' => $periodo,
            'pagos' => $pagos,
            'total' => (float) $pagos->sum('monto'),
        ])->setPaper('a4')->download("umsatzbericht_{$sufijo}.pdf");
    }

    private function pdfCitas(object $tenant, int $tenantId, string $desde, string $bis, string $periodo, string $sufijo): Response
    {
        $citas = (new CitasExport($tenantId, $desde, $bis))->query()->get();

        return Pdf::loadView('reportes.citas-pdf', [
            'tenant' => $tenant,
            'periodo' => $periodo,
            'citas' => $citas,
        ])->setPaper('a4', 'landscape')->download("terminbericht_{$sufijo}.pdf");
    }

    private function pdfPersonal(object $tenant, int $tenantId, string $desde, string $bis, string $periodo, string $sufijo): Response
    {
        $filas = (new PersonalExport($tenantId, $desde, $bis))->collection();

        return Pdf::loadView('reportes.personal-pdf', [
            'tenant' => $tenant,
            'periodo' => $periodo,
            'filas' => $filas,
        ])->setPaper('a4')->download("mitarbeiterbericht_{$sufijo}.pdf");
    }
}
