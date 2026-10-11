<?php

namespace App\Policies;

use App\Models\Personal;
use App\Models\User;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 */
class PersonalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && ! is_null($user->tenant_id);
    }

    public function view(User $user, Personal $personal): bool
    {
        return $user->activo && $personal->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        // Gating por plan (OWASP A04): Basis 3, Professionell 10, Premium ∞
        return $user->activo
            && in_array($user->rol, ['propietario', 'admin'])
            && ($user->tenant?->puedeCrearPersonal() ?? false);
    }

    public function update(User $user, Personal $personal): bool
    {
        return $this->view($user, $personal) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }

    public function delete(User $user, Personal $personal): bool
    {
        return $this->view($user, $personal) && $user->rol === 'propietario';
    }
}
