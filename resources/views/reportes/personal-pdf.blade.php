@extends('reportes.layout-pdf')

@section('titulo', 'Mitarbeiterbericht')

@section('contenido')
    <h2 class="seccion">Mitarbeiter-Leistung im Zeitraum {{ $periodo }}</h2>

    @if ($filas->isEmpty())
        <div class="sin-datos">Keine Mitarbeiterdaten im ausgewählten Zeitraum gefunden.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 32%;">Mitarbeiter</th>
                    <th style="width: 17%;" class="centrado">Abgeschlossen</th>
                    <th style="width: 17%;" class="centrado">Abgesagt</th>
                    <th style="width: 17%;" class="derecha-num">Umsatz (€)</th>
                    <th style="width: 17%;" class="derecha-num">Ø pro Termin (€)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ $fila['mitarbeiter'] }}</td>
                        <td class="centrado">{{ $fila['completadas'] }}</td>
                        <td class="centrado">{{ $fila['canceladas'] }}</td>
                        <td class="derecha-num">{{ \App\Support\ReporteTexto::euro($fila['umsatz']) }}</td>
                        <td class="derecha-num">{{ \App\Support\ReporteTexto::euro($fila['promedio']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Gesamt ({{ $filas->count() }} Mitarbeiter)</td>
                    <td class="centrado">{{ $filas->sum('completadas') }}</td>
                    <td class="centrado">{{ $filas->sum('canceladas') }}</td>
                    <td class="derecha-num">{{ \App\Support\ReporteTexto::euro($filas->sum('umsatz')) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection
