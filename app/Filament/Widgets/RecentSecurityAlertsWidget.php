<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * OWASP A09 / NIST CSF Detect
 * Tabla con las últimas 10 alertas de seguridad del tenant.
 * Solo visible para propietario/admin.
 */
class RecentSecurityAlertsWidget extends BaseWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->esAdmin();
    }

    public function table(Table $table): Table
    {
        $tenantId = auth()->user()?->tenant_id;

        return $table
            ->heading('Letzte Sicherheitswarnungen')
            ->query(
                AuditLog::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('accion', [
                        'CUENTA_BLOQUEADA', 'BOT_DETECTADO', 'GDPR_BORRADO',
                        'GDPR_EXPORTAR', 'ROL_CAMBIADO', 'LOCKDOWN',
                        'LOGIN_BLOQUEADO', 'PASSWORD_CAMBIADO',
                    ])
                    ->latest('created_at')
            )
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('accion')
                    ->label('Ereignis')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'CUENTA_BLOQUEADA', 'BOT_DETECTADO', 'LOCKDOWN' => 'danger',
                        'GDPR_BORRADO', 'ROL_CAMBIADO' => 'warning',
                        'GDPR_EXPORTAR', 'PASSWORD_CAMBIADO' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('user.name')->label('Benutzer'),
                Tables\Columns\TextColumn::make('ip')->label('IP'),
                Tables\Columns\TextColumn::make('resultado')->label('Ergebnis')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'bloqueado' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
