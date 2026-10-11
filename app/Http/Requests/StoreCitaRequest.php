<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OWASP A03 - Injection / CWE-20 Improper Input Validation
 */
class StoreCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'personal_id' => ['required', 'integer', 'exists:personals,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'estado' => ['required', Rule::in([
                'pendiente', 'confirmada', 'completada', 'cancelada', 'no_presentado',
            ])],
            'total' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'servicios' => ['nullable', 'array'],
            'servicios.*' => ['integer', 'exists:servicios,id'],
        ];
    }
}
