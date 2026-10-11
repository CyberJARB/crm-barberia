<?php

namespace App\Http\Requests;

use App\Models\Personal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * OWASP A03 / CWE-20 â€” ValidaciÃ³n al actualizar personal.
 */
class UpdatePersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->esAdmin();
    }

    public function rules(): array
    {
        $record = $this->route('record');
        $personalId = $record instanceof Personal ? $record->id : $record;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'min:2', 'max:255',
                'regex:/^[\p{L}\s\-\'.]+$/u'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30',
                'regex:/^\+?[0-9\s\-()\/.]{7,30}$/'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255',
                "unique:personal,email,{$personalId},id,deleted_at,NULL"],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => ['sometimes', 'boolean'],
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
