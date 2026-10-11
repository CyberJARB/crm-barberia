<?php

namespace App\Policies;

use App\Models\ListaEspera;
use App\Models\User;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 * La Warteliste es trabajo de recepción.
 */
class ListaEsperaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && ! is_null($user->tenant_id);
    }

    public function view(User $user, ListaEspera $entrada): bool
    {
        return $user->activo && $entrada->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->activo && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function update(User $user, ListaEspera $entrada): bool
    {
        return $this->view($user, $entrada) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function delete(User $user, ListaEspera $entrada): bool
    {
        return $this->update($user, $entrada);
    }
}
