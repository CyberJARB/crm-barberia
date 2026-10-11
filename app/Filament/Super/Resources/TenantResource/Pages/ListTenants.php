<?php

namespace App\Filament\Super\Resources\TenantResource\Pages;

use App\Filament\Super\Resources\TenantResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Geschäft anlegen')
                ->icon('heroicon-m-plus'),

            // Comunicado masivo (mantenimiento, novedades) a todos los activos
            Action::make('ankuendigung')
                ->label('Ankündigung an alle')
                ->icon('heroicon-m-megaphone')
                ->color('warning')
                ->form([
                    TextInput::make('asunto')
                        ->label('Betreff')->required()->maxLength(150),
                    Textarea::make('mensaje')
                        ->label('Nachricht')->required()->rows(6)->maxLength(5000),
                ])
                ->requiresConfirmation()
                ->modalDescription('Die Nachricht geht an die Inhaber/Admins ALLER aktiven Geschäfte.')
                ->action(function (array $data): void {
                    $total = 0;
                    Tenant::query()->where('activo', true)->each(function (Tenant $tenant) use ($data, &$total): void {
                        $total += TenantResource::enviarAnuncio($tenant, $data['asunto'], $data['mensaje']);
                    });

                    Notification::make()
                        ->title("Ankündigung an {$total} Empfänger versendet")
                        ->success()->send();
                }),
        ];
    }

    /** Pestañas operativas: el embudo comercial de un vistazo. */
    public function getTabs(): array
    {
        $todos = Tenant::query()->get();
        $porEstado = $todos->groupBy(fn (Tenant $t): string => $t->estadoSuscripcion());

        return [
            'alle' => Tab::make('Alle')
                ->badge($todos->count()),

            'zahlend' => Tab::make('Zahlend')
                ->badge($porEstado->get('activa')?->count() ?? 0)
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('id', ($porEstado->get('activa') ?? collect())->pluck('id'))),

            'trial' => Tab::make('Testphase')
                ->badge($porEstado->get('trial')?->count() ?? 0)
                ->badgeColor('info')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('id', ($porEstado->get('trial') ?? collect())->pluck('id'))),

            'abgelaufen' => Tab::make('Abgelaufen')
                ->badge($porEstado->get('vencido')?->count() ?? 0)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('id', ($porEstado->get('vencido') ?? collect())->pluck('id'))),

            'gesperrt' => Tab::make('Gesperrt')
                ->badge($porEstado->get('suspendido')?->count() ?? 0)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->where('activo', false)),
        ];
    }
}
