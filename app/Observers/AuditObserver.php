<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * UA1 T02 / NIST CSF Detect / ISO 27001 A.12.4
 * Registra automáticamente CREATE, UPDATE, DELETE en todas las entidades.
 * Principio de No Repudio: registro de quién hizo qué y cuándo.
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLog::registrar(
            'CREATE',
            class_basename($model),
            $model->getKey(),
            null,
            $model->getAttributes()
        );
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        if (empty($dirty)) {
            return;
        }

        AuditLog::registrar(
            'UPDATE',
            class_basename($model),
            $model->getKey(),
            array_intersect_key($model->getOriginal(), $dirty),
            $dirty
        );
    }

    public function deleted(Model $model): void
    {
        AuditLog::registrar(
            'DELETE',
            class_basename($model),
            $model->getKey(),
            $model->getAttributes(),
            null
        );
    }
}
