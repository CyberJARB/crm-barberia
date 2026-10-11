<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Crypt;

/**
 * OWASP A02 - Cryptographic Failures / UA5 T03 - AES-256
 * Cifra campos sensibles (PII) en reposo usando AES-256-CBC de Laravel.
 * Declara $encryptedFields en el modelo hijo para activarlo.
 *
 * Ejemplo:
 *   protected array $encryptedFields = ['telefono', 'notas', 'alergias'];
 */
trait HasEncryptedFields
{
    public function setAttribute(mixed $key, mixed $value): mixed
    {
        if ($value !== null && in_array($key, $this->encryptedFields)) {
            $value = Crypt::encryptString((string) $value);
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute(mixed $key): mixed
    {
        $value = parent::getAttribute($key);

        if ($value !== null && in_array($key, $this->encryptedFields)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception) {
                // Si no está cifrado aún (datos antiguos), devolver tal cual
                return $value;
            }
        }

        return $value;
    }
}
