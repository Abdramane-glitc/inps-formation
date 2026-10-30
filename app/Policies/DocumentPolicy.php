<?php

namespace App\Policies;

use App\Models\ActionFormation;
use App\Models\BesoinFormation;
use App\Models\Document;
use App\Models\FormationDiplomante;
use App\Models\Organisme;
use App\Models\SessionFormation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Accès aux documents : droit de consulter l'objet de rattachement
 * + permission « documents.confidentiels » pour un document confidentiel.
 */
class DocumentPolicy
{
    public function view(User $u, Document $d): bool
    {
        if ($d->confidentiel && ! $u->aPermission('documents.confidentiels')) {
            return false;
        }
        // Rattachement à un type d'objet retiré (plan annuel avant 1.3.0, dépense avant 1.4.0) : plus accessible depuis l'application.
        if (! array_key_exists($d->documentable_type, Document::RATTACHEMENTS)) {
            return false;
        }
        $objet = $d->documentable;

        return $objet && $this->peutVoirObjet($u, $objet);
    }

    public function delete(User $u, Document $d): bool
    {
        return $this->view($u, $d) && $d->documentable && $this->peutGererObjet($u, $d->documentable);
    }

    public function peutVoirObjet(User $u, Model $o): bool
    {
        return match (true) {
            $o instanceof BesoinFormation => $u->can('view', $o),
            $o instanceof ActionFormation => $u->aPermission('actions.voir') && $o->estVisiblePar($u),
            $o instanceof SessionFormation => $u->aPermission('actions.voir') && $o->action?->estVisiblePar($u),
            $o instanceof Organisme => $u->aPermission('organismes.contrats'),
            $o instanceof FormationDiplomante => $u->aPermission('diplomantes.gerer'),
            default => false,
        };
    }

    public function peutGererObjet(User $u, Model $o): bool
    {
        return match (true) {
            $o instanceof BesoinFormation => $u->can('joindre', $o) || $u->can('instruire', $o),
            $o instanceof ActionFormation, $o instanceof SessionFormation => $u->aPermission('actions.gerer'),
            $o instanceof Organisme => $u->aPermission('organismes.contrats'),
            $o instanceof FormationDiplomante => $u->aPermission('diplomantes.gerer'),
            default => false,
        };
    }
}
