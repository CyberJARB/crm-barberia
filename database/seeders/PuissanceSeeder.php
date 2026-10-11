<?php

namespace Database\Seeders;

use App\Models\HorarioPersonal;
use App\Models\Personal;
use App\Models\Servicio;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Tenant real: Puissance — barbería africana para hombres en Hamburg Hamm.
 * Horario del local: Lu–Vi 11–19, Sa 10–18, Do cerrado.
 *
 *   php artisan db:seed --class=PuissanceSeeder
 */
class PuissanceSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'puissance'],
            [
                'nombre' => 'Puissance',
                'tipo' => 'barberia',
                'telefono' => null,
                'email' => null,
                'direccion' => 'Horner Weg 254',
                'ciudad' => 'Hamburg',
                'pais' => 'DE',
                'moneda' => 'EUR',
                'zona_horaria' => 'Europe/Berlin',
                'plan' => 'profesional',
                'activo' => true,
            ],
        );

        // Servicios de barbería masculina (duración en minutos)
        $servicios = [
            ['nombre' => 'Haarschnitt & Fade',        'duracion_minutos' => 45,  'precio' => 25, 'categoria' => 'corte'],
            ['nombre' => 'Haarschnitt + Bart',         'duracion_minutos' => 60,  'precio' => 35, 'categoria' => 'corte'],
            ['nombre' => 'Bartpflege & Rasur',         'duracion_minutos' => 30,  'precio' => 15, 'categoria' => 'barba'],
            ['nombre' => 'Waves & Locs Pflege',        'duracion_minutos' => 60,  'precio' => 40, 'categoria' => 'pflege'],
            ['nombre' => 'Cornrows / Braids (Herren)', 'duracion_minutos' => 90,  'precio' => 50, 'categoria' => 'braids'],
            ['nombre' => 'Kinder-Haarschnitt',         'duracion_minutos' => 30,  'precio' => 15, 'categoria' => 'corte'],
        ];

        foreach ($servicios as $servicio) {
            Servicio::withoutGlobalScope('tenant')->firstOrCreate(
                ['tenant_id' => $tenant->id, 'nombre' => $servicio['nombre']],
                $servicio + ['activo' => true],
            );
        }

        // Dos barberos con el horario del local
        foreach (['Barbier 1', 'Barbier 2'] as $nombre) {
            $personal = Personal::withoutGlobalScope('tenant')->firstOrCreate(
                ['tenant_id' => $tenant->id, 'nombre' => $nombre],
                ['activo' => true],
            );

            // dia_semana: 0=Montag ... 6=Sonntag (convención del panel Filament)
            // Lu-Vi (0-4) 11-19, Sa (5) 10-18, Do (6) sin fila = cerrado
            foreach ([0, 1, 2, 3, 4] as $dia) {
                HorarioPersonal::firstOrCreate(
                    ['personal_id' => $personal->id, 'dia_semana' => $dia],
                    ['hora_inicio' => '11:00', 'hora_fin' => '19:00', 'activo' => true],
                );
            }
            HorarioPersonal::firstOrCreate(
                ['personal_id' => $personal->id, 'dia_semana' => 5],
                ['hora_inicio' => '10:00', 'hora_fin' => '18:00', 'activo' => true],
            );
        }

        $this->command?->info("Tenant Puissance listo (id {$tenant->id}).");
    }
}
