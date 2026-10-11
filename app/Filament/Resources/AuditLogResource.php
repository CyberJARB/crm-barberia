<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * OWASP A09 / ISO 27001 A.12.4
 * Vista de solo lectura del registro de auditoría.
 * Solo accesible por propietario/admin.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Audit-Protokoll';

    protected static ?string $modelLabel = 'Eintrag';

    protected static ?string $pluralModelLabel = 'Audit-Protokoll';

    protected static \UnitEnum|string|null $navigationGroup = 'Sicherheit';

    protected static ?int $navigationSort = 99;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    // OWASP A01 — Sin esto, ocultar la navegación NO impide el acceso directo por URL
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->esAdmin();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->esAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Propietarios/admins solo ven logs de su propio tenant (OWASP A01)
        if ($user && $user->tenant_id) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')->dateTime('d.m.Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('accion')
                    ->label('Aktion')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'CREATE' => 'success',
                        'UPDATE' => 'warning',
                        'DELETE' => 'danger',
                        'LOGIN_SUCCESS' => 'info',
                        'LOGIN_BLOQUEADO', 'CUENTA_BLOQUEADA' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('entidad')->label('Entität')->badge(),
                Tables\Columns\TextColumn::make('entidad_id')->label('ID'),
                Tables\Columns\TextColumn::make('user.name')->label('Benutzer')->searchable(),
                Tables\Columns\TextColumn::make('ip')->label('IP')->searchable(),
                Tables\Columns\TextColumn::make('resultado')->label('Ergebnis')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'error' => 'danger',
                        'bloqueado' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('accion')
                    ->label('Aktion')
                    ->options([
                        'CREATE' => 'Erstellung',
                        'UPDATE' => 'Aktualisierung',
                        'DELETE' => 'Löschung',
                        'LOGIN_SUCCESS' => 'Erfolgreiche Anmeldung',
                        'LOGIN_BLOQUEADO' => 'Blockierte Anmeldung',
                    ]),
                Tables\Filters\SelectFilter::make('entidad')
                    ->label('Entität')
                    ->options([
                        'Cliente' => 'Kunden',
                        'Cita' => 'Termine',
                        'Pago' => 'Zahlungen',
                        'Servicio' => 'Dienstleistungen',
                        'User' => 'Benutzer',
                    ]),
                Tables\Filters\Filter::make('hoy')
                    ->query(fn ($query) => $query->whereDate('created_at', today()))
                    ->label('Nur heute'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
