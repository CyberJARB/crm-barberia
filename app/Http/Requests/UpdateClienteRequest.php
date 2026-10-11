<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;

/**
 * OWASP A03 / CWE-20 — Validación en actualización de cliente.
 * La regla 'unique' ignora el propio registro para evitar falsos positivos.
 */
class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('record'));
    }

    public function rules(): array
    {
        $record = $this->route('record');
        $clienteId = $record instanceof Cliente ? $record->id : $record;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'min:2', 'max:255',
                'regex:/^[\p{L}\s\-\'.]+$/u'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30',
                'regex:/^\+?[0-9\s\-()\/.]{7,30}$/'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255',
                "unique:clientes,email,{$clienteId},id,deleted_at,NULL"],
            'fecha_nacimiento' => ['sometimes', 'nullable', 'date', 'before:today', 'after:1900-01-01'],
            'notas' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'alergias' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
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
