<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\User;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 */
class CitaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && ! is_null($user->tenant_id);
    }

    public function view(User $user, Cita $cita): bool
    {
        return $user->activo && $cita->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->activo && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function update(User $user, Cita $cita): bool
    {
        return $this->view($user, $cita) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function delete(User $user, Cita $cita): bool
    {
        return $this->view($user, $cita) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }
}
