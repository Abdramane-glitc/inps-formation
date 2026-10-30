<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->aUneDesPermissions('agents.voir_tous', 'agents.voir_service');
    }

    public function view(User $u, Agent $a): bool
    {
        return $u->aPermission('agents.voir_tous')
            || ($u->aPermission('agents.voir_service') && $u->service_id && $u->service_id === $a->service_id)
            || ($u->aPermission('espace.personnel') && $u->agent_id === $a->id);
    }

    public function create(User $u): bool
    {
        return $u->aPermission('agents.gerer');
    }

    public function update(User $u, Agent $a): bool
    {
        return $u->aPermission('agents.gerer');
    }
}
