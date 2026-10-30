<?php

namespace App\Services;

use App\Enums\StatutBesoin;
use App\Enums\StatutInscription as I;
use App\Enums\StatutSession;
use App\Exceptions\RegleMetierException;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\Attestation;
use App\Models\BesoinFormation;
use App\Models\Formateur;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Suppression des éléments saisis par erreur (version 1.6.0).
 *
 *  - Demande : tant qu'aucune formation n'y répond ; suppression logique, restaurable depuis la corbeille.
 *  - Formation : tant qu'elle n'est pas terminée et qu'aucune attestation n'est émise ; suppression logique,
 *    participants annulés (ils ne bloquent plus leurs dates), demande d'origine remise « Envoyée » ; restaurable.
 *  - Agent : définitive, seulement s'il n'est inscrit à aucune formation, cité dans aucune demande, sans compte ni rôle de formateur.
 *  - Compte : définitive, seulement s'il ne s'est jamais connecté.
 * Dans tous les autres cas, la raison est donnée et l'action habituelle est proposée (annuler, retirer, désactiver).
 * Chaque suppression et restauration est inscrite au journal d'audit.
 */
class SuppressionService
{
    public function __construct(private InscriptionService $inscriptions) {}

    // ---- Demandes -------------------------------------------------------------------------------------------

    /** Raison pour laquelle la demande ne peut pas être supprimée par cet utilisateur, ou null. */
    public function obstacleBesoin(BesoinFormation $b, User $u): ?string
    {
        if ($b->statut === StatutBesoin::Organisee || $b->formation()->exists()) {
            return 'une formation est programmée pour cette demande (supprimez d\'abord la formation)';
        }
        if ($b->statut === StatutBesoin::AuPlan) {
            return 'elle est inscrite au plan annuel (retirez-la d\'abord du plan)';
        }
        if ($u->aPermission('besoins.instruire') && $u->can('view', $b)) {
            return null; // la RH peut supprimer toute demande sans formation
        }
        $auteur = $b->auteur_id === $u->id || ($u->aPermission('besoins.creer') && $u->service_id === $b->service_id);
        if (! $auteur || ! in_array($b->statut, [StatutBesoin::Brouillon, StatutBesoin::Soumise, StatutBesoin::ACompleter], true)) {
            return 'la RH a déjà décidé de cette demande';
        }

        return null;
    }

    public function supprimerBesoin(BesoinFormation $b, User $u): void
    {
        if ($raison = $this->obstacleBesoin($b, $u)) {
            throw new RegleMetierException("La demande {$b->reference} ne peut pas être supprimée : $raison.");
        }
        DB::transaction(function () use ($b, $u) {
            $b->decisions()->create(['action' => 'suppression', 'statut_avant' => $b->statut->value, 'statut_apres' => $b->statut->value, 'user_id' => $u->id]);
            $b->delete();
            Audit::enregistrer('besoin.suppression', $b, "Demande {$b->reference} supprimée (corbeille)", ['intitule' => $b->intitule]);
        });
    }

    public function restaurerBesoin(BesoinFormation $b, User $u): void
    {
        DB::transaction(function () use ($b, $u) {
            $b->restore();
            $b->decisions()->create(['action' => 'restauration', 'statut_avant' => $b->statut->value, 'statut_apres' => $b->statut->value, 'user_id' => $u->id]);
            Audit::enregistrer('besoin.restauration', $b, "Demande {$b->reference} restaurée depuis la corbeille");
        });
    }

    // ---- Formations -----------------------------------------------------------------------------------------

    public function obstacleFormation(ActionFormation $a): ?string
    {
        $s = $a->session()->first();
        if ($s?->statut === StatutSession::Terminee) {
            return 'elle est terminée (son historique doit être conservé)';
        }
        if ($s && Attestation::whereHas('inscription', fn ($q) => $q->where('session_id', $s->id))->exists()) {
            return 'des attestations ont été émises';
        }
        if ($a->historique_importe) {
            return 'elle provient de l\'import de l\'historique';
        }

        return null;
    }

    public function supprimerFormation(ActionFormation $a, User $u): void
    {
        if ($raison = $this->obstacleFormation($a)) {
            $conseil = $a->session()->value('statut') === StatutSession::Terminee->value ? '' : ' Vous pouvez l\'annuler.';
            throw new RegleMetierException("La formation {$a->reference} ne peut pas être supprimée : $raison.$conseil");
        }
        DB::transaction(function () use ($a, $u) {
            $s = $a->session()->lockForUpdate()->first();
            $participants = 0;
            if ($s) {
                foreach ($s->participantsActifs()->get() as $i) {
                    $i->statut = I::Annule;
                    $i->save();
                    $i->evenements()->create(['evenement' => 'formation_supprimee', 'user_id' => $u->id]);
                    $participants++;
                }
                $s->changements()->create(['type' => 'suppression', 'ancien_statut' => $s->statut->value, 'nouveau_statut' => StatutSession::Annulee->value,
                    'motif' => 'Formation supprimée', 'user_id' => $u->id]);
                $s->statut = StatutSession::Annulee;
                $s->motif_statut = 'Formation supprimée';
                $s->save();
            }
            // La demande d'origine redevient « Envoyée » : la RH peut à nouveau l'accepter (avec d'autres dates) ou la refuser
            if ($b = $a->besoin) {
                // Formation issue du plan annuel : la demande reste au plan (ligne à reprogrammer) ; sinon elle attend une nouvelle décision
                $retour = $a->ligne_plan_id ? StatutBesoin::AuPlan : StatutBesoin::Soumise;
                $b->decisions()->create(['action' => 'formation_supprimee', 'statut_avant' => $b->statut->value, 'statut_apres' => $retour->value,
                    'commentaire' => "Formation {$a->reference} supprimée", 'user_id' => $u->id]);
                $b->statut = $retour;
                $b->save();
                $a->besoin_id_origine = $b->id;
                $a->besoin_id = null;
                $a->save();
            }
            $a->delete();
            Audit::enregistrer('action.suppression', $a, "Formation {$a->reference} supprimée (corbeille)",
                ['intitule' => $a->intitule, 'participants_annules' => $participants, 'demande' => $a->besoin_id_origine ? BesoinFormation::withTrashed()->find($a->besoin_id_origine)?->reference : null]);
        });
    }

    /** @return list<string> participants qui n'ont pas pu être réinscrits */
    public function restaurerFormation(ActionFormation $a, User $u): array
    {
        return DB::transaction(function () use ($a, $u) {
            $a->restore();
            $refus = [];
            $s = $a->session()->first();
            if ($s && $s->statut === StatutSession::Annulee && $s->changements()->latest('id')->value('type') === 'suppression') {
                $s->changements()->create(['type' => 'restauration', 'ancien_statut' => StatutSession::Annulee->value, 'nouveau_statut' => StatutSession::Planifiee->value, 'user_id' => $u->id]);
                $s->statut = StatutSession::Planifiee;
                $s->motif_statut = null;
                $s->save();
                foreach ($s->inscriptions()->with('agent')->where('statut', I::Annule->value)->get() as $i) {
                    if ($i->evenements()->latest('id')->value('evenement') !== 'formation_supprimee') {
                        continue;
                    }
                    if ($code = $this->inscriptions->chevauchement($i->agent_id, $s)) {
                        $refus[] = "{$i->agent->matricule} {$i->agent->nom_complet} : déjà inscrit à la formation $code sur des dates qui se chevauchent.";

                        continue;
                    }
                    $i->statut = I::Inscrit;
                    $i->save();
                    $i->evenements()->create(['evenement' => 'formation_restauree', 'user_id' => $u->id]);
                }
            }
            // Rattacher à nouveau la demande d'origine si elle attend toujours une décision
            if ($a->besoin_id_origine && ($b = BesoinFormation::find($a->besoin_id_origine))
                && in_array($b->statut, [StatutBesoin::Soumise, StatutBesoin::AuPlan], true) && ! $b->formation()->exists()) {
                $a->besoin_id = $b->id;
                $a->besoin_id_origine = null;
                $a->save();
                $b->decisions()->create(['action' => 'organisation', 'statut_avant' => $b->statut->value, 'statut_apres' => StatutBesoin::Organisee->value,
                    'commentaire' => "Formation {$a->reference} restaurée", 'user_id' => $u->id]);
                $b->statut = StatutBesoin::Organisee;
                $b->save();
            }
            Audit::enregistrer('action.restauration', $a, "Formation {$a->reference} restaurée depuis la corbeille", ['participants_non_reinscrits' => count($refus)]);

            return $refus;
        });
    }

    // ---- Agents et comptes (suppression définitive, seulement s'ils ne sont référencés nulle part) -------------

    public function obstacleAgent(Agent $ag): ?string
    {
        if ($ag->inscriptions()->exists()) {
            return 'il a déjà été inscrit à une formation (désactivez-le plutôt)';
        }
        if (DB::table('besoin_agent')->where('agent_id', $ag->id)->exists()) {
            return 'il est cité dans une demande de formation (désactivez-le plutôt)';
        }
        if (User::where('agent_id', $ag->id)->exists()) {
            return 'un compte utilisateur lui est associé';
        }
        if (Formateur::where('agent_id', $ag->id)->exists()) {
            return 'il est enregistré comme formateur';
        }
        if ($ag->formationsDiplomantes()->exists()) {
            return 'une formation diplômante est enregistrée à son nom';
        }

        return null;
    }

    public function supprimerAgent(Agent $ag): void
    {
        if ($raison = $this->obstacleAgent($ag)) {
            throw new RegleMetierException("L'agent {$ag->matricule} ne peut pas être supprimé : $raison.");
        }
        $this->definitif(fn () => $ag->delete(), "L'agent {$ag->matricule}");
        Audit::enregistrer('agent.suppression', 'agent', "Agent {$ag->matricule} {$ag->nom_complet} supprimé définitivement",
            ['id' => $ag->id, 'matricule' => $ag->matricule, 'nom' => $ag->nom, 'prenom' => $ag->prenom, 'service_id' => $ag->service_id]);
    }

    public function obstacleCompte(User $cible, User $acteur): ?string
    {
        if ($cible->id === $acteur->id) {
            return 'c\'est votre propre compte';
        }
        if ($cible->derniere_connexion_le) {
            return 'il a déjà été utilisé (désactivez-le plutôt)';
        }
        if (BesoinFormation::withTrashed()->where('auteur_id', $cible->id)->exists()) {
            return 'il est l\'auteur de demandes';
        }
        $admin = Role::where('code', 'admin_technique')->first();
        if ($admin && $cible->roles()->whereKey($admin->id)->exists()
            && $admin->users()->where('users.actif', true)->where('users.id', '!=', $cible->id)->doesntExist()) {
            return 'c\'est le dernier compte d\'administration';
        }

        return null;
    }

    public function supprimerCompte(User $cible, User $acteur): void
    {
        if ($raison = $this->obstacleCompte($cible, $acteur)) {
            throw new RegleMetierException("Le compte {$cible->identifiant} ne peut pas être supprimé : $raison.");
        }
        $this->definitif(function () use ($cible) {
            DB::table('notifications')->where('notifiable_type', $cible->getMorphClass())->where('notifiable_id', $cible->id)->delete();
            DB::table('sessions')->where('user_id', $cible->id)->delete();
            $cible->roles()->detach();
            $cible->delete();
        }, "Le compte {$cible->identifiant}");
        Audit::enregistrer('utilisateur.suppression', 'user', "Compte {$cible->identifiant} supprimé définitivement (jamais utilisé)", ['id' => $cible->id, 'nom' => $cible->name]);
    }

    /** Suppression définitive protégée par les contraintes de la base : en cas de référence imprévue, rien n'est supprimé. */
    private function definitif(callable $suppression, string $quoi): void
    {
        try {
            DB::transaction($suppression);
        } catch (QueryException) {
            throw new RegleMetierException("$quoi est utilisé ailleurs dans l'application et ne peut pas être supprimé : désactivez-le plutôt.");
        }
    }
}
