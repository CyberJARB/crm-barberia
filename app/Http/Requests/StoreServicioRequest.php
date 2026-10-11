<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OWASP A03 - Injection / CWE-20 Improper Input Validation
 */
class StoreServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'precio' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nombre')) {
            $this->merge(['nombre' => strip_tags(trim($this->nombre))]);
        }
        if ($this->has('descripcion')) {
            $this->merge(['descripcion' => strip_tags($this->descripcion)]);
        }
    }
}
