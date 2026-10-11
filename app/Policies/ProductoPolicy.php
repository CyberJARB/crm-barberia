<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 * Vender (update) es trabajo de recepción; crear/borrar productos es de gestión.
 */
class ProductoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && ! is_null($user->tenant_id);
    }

    public function view(User $user, Producto $producto): bool
    {
        return $user->activo && $producto->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->activo && in_array($user->rol, ['propietario', 'admin']);
    }

    public function update(User $user, Producto $producto): bool
    {
        return $this->view($user, $producto) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function delete(User $user, Producto $producto): bool
    {
        return $this->view($user, $producto) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }
}
