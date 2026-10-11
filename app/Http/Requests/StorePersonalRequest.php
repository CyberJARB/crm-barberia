<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OWASP A03 / CWE-20 — Validación al crear miembro del personal.
 */
class StorePersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->esAdmin();
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:255',
                'regex:/^[\p{L}\s\-\'.]+$/u'],
            'telefono' => ['nullable', 'string', 'max:30',
                'regex:/^\+?[0-9\s\-()\/.]{7,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nombre')) {
            $this->merge(['nombre' => strip_tags(trim($this->nombre))]);
        }
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email ?? ''))]);
        }
    }
}
