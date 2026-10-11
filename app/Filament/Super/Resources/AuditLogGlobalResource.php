<?php

namespace App\Filament\Super\Resources;

use App\Filament\Super\Resources\AuditLogGlobalResource\Pages;
use App\Models\AuditLog;
use App\Models\Tenant;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Auditoría GLOBAL del SaaS (solo lectura): todas las acciones de todos
 * los tenants + las acciones SAAS_* del propio operador.
 * ISO 27001 A.12.4 — el log no se puede editar ni borrar desde la UI.
 */
class AuditLogGlobalResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Audit-Log (global)';

    protected static ?string $modelLabel = 'Audit-Eintrag';

    protected static ?string $pluralModelLabel = 'Audit-Log';

    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('tenant.nombre')->label('Geschäft')
                    ->placeholder('— System —')
                    ->searchable(),
                Tables\Columns\TextColumn::make('accion')->label('Aktion')
                    ->badge()
                    ->searchable()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'SAAS_') => 'primary',
                        str_contains($state, 'FALLO')
                            || str_contains($state, 'BLOQUEADA') => 'danger',
                        str_starts_with($state, 'GDPR') => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('entidad')->label('Entität'),
                Tables\Columns\TextColumn::make('entidad_id')->label('ID'),
                Tables\Columns\TextColumn::make('resultado')->label('Ergebnis')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ok' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('ip')->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Geschäft')
                    ->options(fn () => Tenant::query()->orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('solo_saas')
                    ->label('Nur Betreiber-Aktionen (SAAS_*)')
                    ->query(fn ($query) => $query->where('accion', 'like', 'SAAS\_%')),
                Tables\Filters\Filter::make('solo_fallos')
                    ->label('Nur Fehler/Sperrungen')
                    ->query(fn ($query) => $query->where('resultado', '!=', 'ok')),
            ])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogsGlobal::route('/'),
        ];
    }
}
