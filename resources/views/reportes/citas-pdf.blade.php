@extends('reportes.layout-pdf')

@section('titulo', 'Terminbericht')

@section('contenido')
    <h2 class="seccion">Termine im Zeitraum {{ $periodo }}</h2>

    @if ($citas->isEmpty())
        <div class="sin-datos">Keine Termine im ausgewählten Zeitraum gefunden.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Datum</th>
                    <th style="width: 7%;">Uhrzeit</th>
                    <th style="width: 17%;">Kunde</th>
                    <th style="width: 15%;">Mitarbeiter</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 24%;">Dienstleistungen</th>
                    <th style="width: 10%;" class="derecha-num">Betrag (€)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($citas as $cita)
                    <tr>
                        <td>{{ \App\Support\ReporteTexto::fecha($cita->fecha) }}</td>
                        <td>{{ substr((string) $cita->hora_inicio, 0, 5) }}</td>
                        <td>{{ $cita->cliente?->nombre ?? '—' }}</td>
                        <td>{{ $cita->personal?->nombre ?? '—' }}</td>
                        <td>{{ \App\Support\ReporteTexto::estado($cita->estado) }}</td>
                        <td>{{ $cita->servicios->pluck('nombre')->implode(', ') ?: '—' }}</td>
                        <td class="derecha-num">{{ \App\Support\ReporteTexto::euro($cita->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6">Gesamt ({{ $citas->count() }} Termine)</td>
                    <td class="derecha-num">{{ \App\Support\ReporteTexto::euro($citas->sum('total')) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection
