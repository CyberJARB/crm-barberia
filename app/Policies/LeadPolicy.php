<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 * Controla quién puede gestionar los leads del embudo.
 * Roles: propietario > admin > recepcionista > readonly
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->esDelTenant($user) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista', 'readonly',
        ]);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->esDelTenant($user) && $lead->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->esDelTenant($user) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead) && in_array($user->rol, [
            'propietario', 'admin', 'recepcionista',
        ]);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead) && in_array($user->rol, [
            'propietario', 'admin',
        ]);
    }

    private function esDelTenant(User $user): bool
    {
        return $user->activo && ! is_null($user->tenant_id);
    }
}
