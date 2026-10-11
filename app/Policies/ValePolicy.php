<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vale;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 * La venta de vales es trabajo de recepción; anular/borrar es de gestión.
 */
class ValePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && ! is_null($user->tenant_id);
    }

    public function view(User $user, Vale $vale): bool
    {
        return $user->activo && $vale->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->activo && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function update(User $user, Vale $vale): bool
    {
        return $this->view($user, $vale) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function delete(User $user, Vale $vale): bool
    {
        return $this->view($user, $vale) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }
}
