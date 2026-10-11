<?php

namespace App\Policies;

use App\Models\Servicio;
use App\Models\User;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 */
class ServicioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && ! is_null($user->tenant_id);
    }

    public function view(User $user, Servicio $servicio): bool
    {
        return $user->activo && $servicio->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->activo && in_array($user->rol, ['propietario', 'admin']);
    }

    public function update(User $user, Servicio $servicio): bool
    {
        return $this->view($user, $servicio) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }

    public function delete(User $user, Servicio $servicio): bool
    {
        return $this->view($user, $servicio) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }
}
