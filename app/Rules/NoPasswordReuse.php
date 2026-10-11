<?php

namespace App\Rules;

use App\Models\PasswordHistory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * CWE-521 / NIST SP 800-63B — Reutilización de contraseña
 * Impide que el usuario reutilice alguna de sus últimas 5 contraseñas.
 */
class NoPasswordReuse implements ValidationRule
{
    private const HISTORIAL = 5;

    public function __construct(private readonly int $userId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $historial = PasswordHistory::where('user_id', $this->userId)
            ->latest('created_at')
            ->limit(self::HISTORIAL)
            ->pluck('password');

        foreach ($historial as $hash) {
            if (Hash::check($value, $hash)) {
                $fail('Sie dürfen keines Ihrer letzten '.self::HISTORIAL.' Passwörter wiederverwenden.');

                return;
            }
        }
    }
}
