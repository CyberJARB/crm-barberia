@extends('reportes.layout-pdf')

@section('titulo', 'Umsatzbericht')

@section('contenido')
    <h2 class="seccion">Einnahmen im Zeitraum {{ $periodo }}</h2>

    @if ($pagos->isEmpty())
        <div class="sin-datos">Keine Zahlungen im ausgewählten Zeitraum gefunden.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 16%;">Datum</th>
                    <th style="width: 28%;">Kunde</th>
                    <th style="width: 18%;">Methode</th>
                    <th style="width: 14%;" class="derecha-num">Betrag (€)</th>
                    <th style="width: 24%;">Referenz</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pagos as $pago)
                    <tr>
                        <td>{{ $pago->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $pago->cliente?->nombre ?? '—' }}</td>
                        <td>{{ \App\Support\ReporteTexto::metodo($pago->metodo) }}</td>
                        <td class="derecha-num">{{ \App\Support\ReporteTexto::euro($pago->monto) }}</td>
                        <td>{{ $pago->referencia ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Gesamtsumme ({{ $pagos->count() }} Zahlungen)</td>
                    <td class="derecha-num">{{ \App\Support\ReporteTexto::euro($total) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection
