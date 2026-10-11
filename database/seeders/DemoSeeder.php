<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Personal;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Tenant;
use App\Models\Vale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de demostración para el tenant demo-barberia:
 * empleados, servicios, clientes y citas de la semana actual.
 * Idempotente: no duplica si ya hay datos.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo-barberia')->first();

        if (! $tenant) {
            $this->command->error('Tenant demo-barberia no existe. Ejecuta primero AdminSeeder.');

            return;
        }

        if (Personal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()) {
            $this->command->warn('El tenant demo ya tiene datos — no se duplica nada.');

            return;
        }

        $tid = $tenant->id;

        // === Mitarbeiter ===
        $empleados = collect([
            ['nombre' => 'Murat Aydin',  'color' => '#6366f1'],
            ['nombre' => 'Lena Schmidt', 'color' => '#ec4899'],
            ['nombre' => 'Jonas Weber',  'color' => '#10b981'],
        ])->map(fn (array $e) => Personal::create($e + ['tenant_id' => $tid, 'activo' => true]));

        // Arbeitszeiten Mo–Sa 09:00–19:00
        foreach ($empleados as $empleado) {
            foreach (range(0, 5) as $dia) {
                $empleado->horarios()->create([
                    'dia_semana' => $dia,
                    'hora_inicio' => '09:00',
                    'hora_fin' => '19:00',
                    'activo' => true,
                ]);
            }
        }

        // === Dienstleistungen ===
        $servicios = collect([
            ['nombre' => 'Herrenhaarschnitt',      'precio' => 28, 'duracion_minutos' => 30, 'categoria' => 'Haare',  'color' => '#6366f1'],
            ['nombre' => 'Bart trimmen & Kontur',  'precio' => 18, 'duracion_minutos' => 20, 'categoria' => 'Bart',   'color' => '#f59e0b'],
            ['nombre' => 'Komplett-Paket',         'precio' => 42, 'duracion_minutos' => 50, 'categoria' => 'Kombi',  'color' => '#10b981'],
            ['nombre' => 'Damenhaarschnitt',       'precio' => 45, 'duracion_minutos' => 45, 'categoria' => 'Haare',  'color' => '#ec4899'],
            ['nombre' => 'Färben',                 'precio' => 65, 'duracion_minutos' => 90, 'categoria' => 'Farbe',  'color' => '#8b5cf6'],
            ['nombre' => 'Kinderhaarschnitt',      'precio' => 15, 'duracion_minutos' => 20, 'categoria' => 'Haare',  'color' => '#06b6d4'],
        ])->map(fn (array $s) => Servicio::create($s + ['tenant_id' => $tid, 'activo' => true]));

        // === Kunden ===
        $clientes = collect([
            'Thomas Müller', 'Anna Becker', 'Mehmet Yilmaz', 'Sophie Wagner',
            'Lukas Hoffmann', 'Elif Demir', 'Felix Schneider', 'Marie Fischer',
        ])->map(fn (string $nombre, int $i) => Cliente::create([
            'tenant_id' => $tid,
            'nombre' => $nombre,
            'telefono' => '+49 17'.$i.' 555010'.$i,
            'email' => str_replace(' ', '.', mb_strtolower($nombre)).'@example.de',
            'activo' => true,
        ]));

        // === Termine de la semana actual ===
        $lunes = now()->startOfWeek(Carbon::MONDAY);
        $estadosPasado = ['completada', 'completada', 'no_show'];

        $plan = [
            // [díaOffset, hora, empleadoIdx, clienteIdx, servicioIdxs]
            [0, '09:30', 0, 0, [0]],
            [0, '11:00', 1, 3, [3]],
            [0, '15:00', 2, 4, [2]],
            [1, '10:00', 0, 2, [0, 1]],
            [1, '14:30', 1, 7, [4]],
            [2, '09:00', 2, 5, [0]],
            [2, '12:00', 0, 6, [2]],
            [3, '10:30', 1, 1, [3]],
            [3, '16:00', 2, 0, [1]],
            [4, '09:30', 0, 4, [0]],
            [4, '11:30', 1, 5, [4]],
            [4, '17:00', 2, 2, [2]],
            [5, '10:00', 0, 7, [0, 1]],
            [5, '13:00', 1, 6, [5]],
        ];

        foreach ($plan as $i => [$diaOffset, $hora, $empIdx, $cliIdx, $servIdxs]) {
            $fecha = $lunes->copy()->addDays($diaOffset);
            $inicio = Carbon::parse($hora);
            $elegidos = collect($servIdxs)->map(fn (int $idx) => $servicios[$idx]);
            $duracion = (int) $elegidos->sum('duracion_minutos');
            $total = (float) $elegidos->sum('precio');

            $esPasado = $fecha->copy()->setTimeFrom($inicio)->isPast();
            $estado = $esPasado
                ? $estadosPasado[$i % count($estadosPasado)]
                : ($i % 3 === 0 ? 'pendiente' : 'confirmada');

            $cita = Cita::create([
                'tenant_id' => $tid,
                'cliente_id' => $clientes[$cliIdx]->id,
                'personal_id' => $empleados[$empIdx]->id,
                'fecha' => $fecha->toDateString(),
                'hora_inicio' => $inicio->format('H:i:s'),
                'hora_fin' => $inicio->copy()->addMinutes($duracion)->format('H:i:s'),
                'estado' => $estado,
                'total' => $total,
            ]);

            foreach ($elegidos as $servicio) {
                $cita->servicios()->attach($servicio->id, [
                    'precio' => $servicio->precio,
                    'duracion_minutos' => $servicio->duracion_minutos,
                ]);
            }

            // Las completadas generan su pago (mismo flujo que en producción)
            if ($estado === 'completada') {
                $cita->pagos()->create([
                    'tenant_id' => $tid,
                    'cliente_id' => $cita->cliente_id,
                    'monto' => $total,
                    'metodo' => $i % 2 === 0 ? 'efectivo' : 'tarjeta',
                ]);
            }
        }

        // === Gutscheine ===
        Vale::create([
            'tenant_id' => $tid,
            'valor_inicial' => 50,
            'cliente_id' => $clientes[1]->id,
            'valido_hasta' => now()->addYears(3),
            'notas' => 'Geburtstagsgeschenk',
        ]);
        Vale::create([
            'tenant_id' => $tid,
            'valor_inicial' => 25,
            'saldo' => 7.50,
            'valido_hasta' => now()->addYears(3),
        ]);

        // === Produkte ===
        collect([
            ['nombre' => 'Matte Pomade 100ml',     'categoria' => 'Styling', 'precio' => 18.90, 'stock' => 12],
            ['nombre' => 'Bartöl Sandelholz 30ml', 'categoria' => 'Bart',    'precio' => 14.50, 'stock' => 8],
            ['nombre' => 'Shampoo Anti-Schuppen',  'categoria' => 'Pflege',  'precio' => 11.90, 'stock' => 2],
            ['nombre' => 'Haarwachs Strong Hold',  'categoria' => 'Styling', 'precio' => 16.50, 'stock' => 15],
        ])->each(fn (array $p) => Producto::create($p + ['tenant_id' => $tid, 'activo' => true]));

        $this->command->info('Datos demo creados: 3 Mitarbeiter, 6 Dienstleistungen, 8 Kunden, '.count($plan).' Termine, 2 Gutscheine, 4 Produkte.');
    }
}
