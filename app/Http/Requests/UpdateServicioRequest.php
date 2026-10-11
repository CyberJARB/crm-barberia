<?php

namespace App\Http\Requests;

use App\Models\Servicio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * OWASP A03 / CWE-20 — Validación al actualizar un servicio.
 */
class UpdateServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->esAdmin();
    }

    public function rules(): array
    {
        $record = $this->route('record');
        $servicioId = $record instanceof Servicio ? $record->id : $record;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'min:2', 'max:255',
                "unique:servicios,nombre,{$servicioId},id,deleted_at,NULL"],
            'precio' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999.99'],
            'duracion' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => ['sometimes', 'boolean'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
