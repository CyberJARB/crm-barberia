<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UA1 T02 / NIST CSF Detect / ISO 27001 A.12.4
 * Registro inmutable de todas las acciones sobre datos sensibles.
 * NUNCA se debe borrar ni modificar un registro de auditoría.
 */
/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $tenant_id
 * @property string $accion
 * @property string $entidad
 * @property int|null $entidad_id
 * @property array|null $valores_anteriores
 * @property array|null $valores_nuevos
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $resultado
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'tenant_id', 'accion', 'entidad', 'entidad_id',
        'valores_anteriores', 'valores_nuevos', 'ip', 'user_agent', 'resultado',
    ];

    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function registrar(
        string $accion,
        string $entidad,
        ?int $entidadId = null,
        ?array $anterior = null,
        ?array $nuevo = null,
        string $resultado = 'ok'
    ): void {
        $request = request();

        static::create([
            'user_id' => auth()->id(),
            'tenant_id' => app()->bound('current_tenant_id') ? app('current_tenant_id') : null,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'valores_anteriores' => $anterior,
            'valores_nuevos' => $nuevo ? self::filtrarCamposSensibles($nuevo) : null,
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            'resultado' => $resultado,
        ]);
    }

    // Nunca guardar contraseñas ni tokens en el audit log
    private static function filtrarCamposSensibles(array $datos): array
    {
        $sensibles = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

        return array_diff_key($datos, array_flip($sensibles));
    }
}
