<?php

namespace App\Policies;

use App\Enums\StatutBesoin;
use App\Models\BesoinFormation;
use App\Models\User;
use App\Services\SuppressionService;

/** Cloisonnement des besoins par service ; refus par défaut. */
class BesoinFormationPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->aUneDesPermissions('besoins.voir_tous', 'besoins.voir_service');
    }

    public function view(User $u, BesoinFormation $b): bool
    {
        return $u->aPermission('besoins.voir_tous')
            || ($u->aPermission('besoins.voir_service') && $u->service_id && $u->service_id === $b->service_id);
    }

    public function create(User $u): bool
    {
        return $u->aPermission('besoins.creer');
    }

    /** Modification / soumission : demande modifiable, et périmètre du service (ou tous services). */
    public function update(User $u, BesoinFormation $b): bool
    {
        return $b->estModifiable() && $u->aPermission('besoins.creer')
            && ($u->aPermission('besoins.creer_tous_services') || ($u->service_id && $u->service_id === $b->service_id));
    }

    public function delete(User $u, BesoinFormation $b): bool
    {
        // Version 1.6.0 : l'auteur tant que la RH n'a pas décidé ; la RH pour toute demande sans formation (voir SuppressionService)
        return $this->view($u, $b) && ($u->aPermission('besoins.creer') || $u->aPermission('besoins.instruire'))
            && app(SuppressionService::class)->obstacleBesoin($b, $u) === null;
    }

    /** Joindre un justificatif : le service demandeur, tant que la RH n'a pas décidé (ou a demandé des précisions). */
    public function joindre(User $u, BesoinFormation $b): bool
    {
        return in_array($b->statut, [StatutBesoin::Brouillon, StatutBesoin::Soumise, StatutBesoin::ACompleter], true)
            && $u->aPermission('besoins.creer') && $this->view($u, $b)
            && ($u->aPermission('besoins.creer_tous_services') || ($u->service_id && $u->service_id === $b->service_id));
    }

    public function instruire(User $u, BesoinFormation $b): bool
    {
        return $u->aPermission('besoins.instruire') && $this->view($u, $b);
    }
}
