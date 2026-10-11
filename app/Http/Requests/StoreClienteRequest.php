<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OWASP A03 - Injection / CWE-20 Improper Input Validation
 * Valida y sanitiza todos los datos del cliente antes de persistirlos.
 */
class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:255',
                'regex:/^[\p{L}\s\-\'.]+$/u'],
            'telefono' => ['nullable', 'string', 'max:30',
                'regex:/^\+?[0-9\s\-()\/.]{7,30}$/'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'notas' => ['nullable', 'string', 'max:5000'],
            'alergias' => ['nullable', 'string', 'max:1000'],
            'activo' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sanitizar: strip tags y normalizar espacios
        if ($this->has('nombre')) {
            $this->merge(['nombre' => strip_tags(trim($this->nombre))]);
        }
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
        if ($this->has('notas')) {
            $this->merge(['notas' => strip_tags($this->notas)]);
        }
    }
}
