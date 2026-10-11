<?php

namespace App\Policies;

use App\Models\Pago;
use App\Models\User;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 */
class PagoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }

    public function view(User $user, Pago $pago): bool
    {
        return $user->activo && $pago->tenant_id === $user->tenant_id
            && in_array($user->rol, ['propietario', 'admin']);
    }

    public function create(User $user): bool
    {
        return $user->activo && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function update(User $user, Pago $pago): bool
    {
        return $this->view($user, $pago) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }

    public function delete(User $user, Pago $pago): bool
    {
        // Pagos: solo el propietario puede borrar (pista de auditoría financiera)
        return $this->view($user, $pago) && $user->rol === 'propietario';
    }
}
