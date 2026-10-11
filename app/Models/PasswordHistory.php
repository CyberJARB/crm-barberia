<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CWE-521 / NIST SP 800-63B
 * Historial de contraseñas para impedir reutilización.
 */
class PasswordHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'password'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
