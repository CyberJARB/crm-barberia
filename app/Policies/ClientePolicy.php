<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 * Controla quién puede hacer qué con los clientes.
 * Roles: propietario > admin > recepcionista > readonly
 */
class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->esDelTenant($user) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista', 'readonly',
        ]);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $this->esDelTenant($user) && $cliente->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->esDelTenant($user) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $this->view($user, $cliente) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $this->view($user, $cliente) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }

    private function esDelTenant(User $user): bool
    {
        return $user->activo && ! is_null($user->tenant_id);
    }
}
