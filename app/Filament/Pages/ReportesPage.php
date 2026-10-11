<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Página de reportes (/admin/berichte) — PDF y Excel.
 *
 * OWASP A01 — solo admin/propietario (canAccess). La descarga real
 * la sirve ReporteController, que vuelve a verificar rol y tenant
 * (defensa en profundidad: la UI nunca es la única barrera).
 */
class ReportesPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.pages.reportes';

    protected static ?string $title = 'Berichte';

    protected static ?string $slug = 'berichte';

    protected static ?int $navigationSort = 6;

    public ?array $data = [];

    /** OWASP A01 / RBAC — recepcionistas y readonly no exportan datos.
     *  OWASP A04 — Berichte solo desde el plan Professionell. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->esAdmin() ?? false)
            && ($user->tenant?->permite('berichte') ?? false);
    }

    public function mount(): void
    {
        $this->form->fill([
            'tipo_reporte' => 'ingresos',
            'fecha_inicio' => now()->startOfMonth()->toDateString(),
            'fecha_hasta' => now()->toDateString(),
            'formato' => 'pdf',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Bericht erstellen')
                    ->description('Wählen Sie Berichtstyp, Zeitraum und Format. Der Download startet sofort.')
                    ->schema([
                        Select::make('tipo_reporte')
                            ->label('Berichtstyp')
                            ->options([
                                'ingresos' => 'Umsatzbericht (Zahlungen)',
                                'citas' => 'Terminbericht',
                                'personal' => 'Mitarbeiterbericht (Leistung)',
                                'clientes' => 'Kundenliste (nur Excel)',
                            ])
                            ->required()
                            ->live(),

                        Radio::make('formato')
                            ->label('Format')
                            ->options(fn ($get): array => $get('tipo_reporte') === 'clientes'
                                ? ['excel' => 'Excel (.xlsx)']
                                : ['pdf' => 'PDF', 'excel' => 'Excel (.xlsx)'])
                            ->inline()
                            ->required(),

                        DatePicker::make('fecha_inicio')
                            ->label('Von')
                            ->maxDate(now())
                            ->required(fn ($get): bool => $get('tipo_reporte') !== 'clientes')
                            ->visible(fn ($get): bool => $get('tipo_reporte') !== 'clientes'),

                        DatePicker::make('fecha_hasta')
                            ->label('Bis')
                            ->maxDate(now())
                            ->afterOrEqual('fecha_inicio')
                            ->required(fn ($get): bool => $get('tipo_reporte') !== 'clientes')
                            ->visible(fn ($get): bool => $get('tipo_reporte') !== 'clientes'),
                    ])->columns(2),
            ]);
    }

    /**
     * Redirige a la ruta de descarga con los parámetros validados.
     * La validación definitiva (rango ≤366 días, rol, tenant) ocurre
     * en ReporteController — aquí solo se valida el formulario.
     */
    public function descargar(): void
    {
        $data = $this->form->getState();

        // La Kundenliste solo existe en Excel
        if ($data['tipo_reporte'] === 'clientes') {
            $data['formato'] = 'excel';
            unset($data['fecha_inicio'], $data['fecha_hasta']);
        }

        $this->redirect(route('reportes.descargar', array_filter($data)));
    }
}
