<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OWASP A03 / CWE-20 — Validación al actualizar una cita.
 */
class UpdateCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->esRecepcionista();
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['sometimes', 'required', 'integer', 'exists:clientes,id'],
            'personal_id' => ['sometimes', 'required', 'integer', 'exists:personal,id'],
            'inicio' => ['sometimes', 'required', 'date'],
            'fin' => ['sometimes', 'required', 'date', 'after:inicio'],
            'estado' => ['sometimes', 'required', Rule::in([
                'pendiente', 'confirmada', 'completada', 'cancelada', 'no_show',
            ])],
            'notas' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'servicios' => ['sometimes', 'array', 'min:1'],
            'servicios.*' => ['integer', 'exists:servicios,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notas')) {
            $this->merge(['notas' => strip_tags($this->notas)]);
        }
    }
}
