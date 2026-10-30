<?php

namespace App\Services;

use App\Enums\StatutBesoin;
use App\Exceptions\RegleMetierException;
use App\Models\ActionFormation;
use App\Models\BesoinFormation;
use App\Models\PlanAnnuel;
use App\Models\PlanLigne;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Plan annuel simple (version 1.7.0) :
 *  - la RH inscrit des formations prévues (intitulé, période, service, nombre d'agents) ou y inscrit une demande acceptée ;
 *  - « Programmer » une ligne fixe les dates : la formation est créée (et les agents de la demande inscrits) ;
 *  - « Valider le plan » enregistre la date et l'auteur ; le plan peut encore être complété ensuite.
 */
class PlanService
{
    public const CHAMPS = ['intitule', 'periode', 'service_id', 'nb_agents', 'type', 'organisme_id', 'observations'];

    public function __construct(private BesoinService $besoins, private ActionService $actions, private InscriptionService $inscriptions) {}

    public function plan(int $annee): PlanAnnuel
    {
        return PlanAnnuel::firstOrCreate(['annee' => $annee]);
    }

    public function ajouterLigne(int $annee, array $donnees, User $u, ?BesoinFormation $besoin = null): PlanLigne
    {
        return DB::transaction(function () use ($annee, $donnees, $u, $besoin) {
            $plan = $this->plan($annee);
            $l = new PlanLigne(array_intersect_key($donnees, array_flip(self::CHAMPS)));
            $l->plan_id = $plan->id;
            $l->besoin_id = $besoin?->id;
            $l->cree_par = $u->id;
            $l->save();
            Audit::enregistrer('plan.ajout', 'plan', "Plan {$annee} : « {$l->intitule} » prévue ({$l->libellePeriode()})",
                ['ligne' => $l->id, 'demande' => $besoin?->reference, 'apres_validation' => $plan->estValide()]);

            return $l;
        });
    }

    public function modifierLigne(PlanLigne $l, array $donnees): PlanLigne
    {
        $l->fill(array_intersect_key($donnees, array_flip(self::CHAMPS)));
        $diff = Audit::differences($l, ['intitule', 'periode', 'service_id', 'nb_agents']);
        $l->save();
        Audit::enregistrer('plan.modification', 'plan', "Plan {$l->plan->annee} : « {$l->intitule} » modifiée", ['ligne' => $l->id] + $diff);

        return $l;
    }

    /** Retire une ligne qui n'a pas encore de formation ; une demande liée redevient « Envoyée » (motif obligatoire). */
    public function retirerLigne(PlanLigne $l, User $u, ?string $motif): void
    {
        if ($l->formation()->exists()) {
            throw new RegleMetierException('Cette ligne est déjà programmée : annulez ou supprimez d\'abord la formation.');
        }
        DB::transaction(function () use ($l, $u, $motif) {
            if ($l->besoin && $l->besoin->statut === StatutBesoin::AuPlan) {
                $this->besoins->transition($l->besoin, 'retrait_plan', $u, $motif ?: null);
            }
            $l->besoin_id = null; // libère la demande (clé unique)
            $l->save();
            $l->delete();
            Audit::enregistrer('plan.retrait', 'plan', "Plan {$l->plan->annee} : « {$l->intitule} » retirée", ['ligne' => $l->id, 'motif' => $motif]);
            $this->annulerValidationSiVide($l->plan);
        });
    }

    /** La RH accepte une demande en l'inscrivant au plan (dates fixées plus tard). */
    public function inscrireDemande(BesoinFormation $b, int $annee, string $periode, User $u, ?string $commentaire = null): PlanLigne
    {
        return DB::transaction(function () use ($b, $annee, $periode, $u, $commentaire) {
            $libelle = PlanLigne::PERIODES[$periode] ?? $periode;
            $this->besoins->transition($b, 'inscription_plan', $u, trim("Plan {$annee}, période prévue : {$libelle}. ".($commentaire ?? '')));

            return $this->ajouterLigne($annee, ['intitule' => $b->intitule, 'periode' => $periode, 'service_id' => $b->service_id,
                'nb_agents' => $b->agents()->count() ?: $b->nb_participants, 'observations' => $b->contexte], $u, $b);
        });
    }

    /**
     * Programme une ligne : crée la formation aux dates indiquées ; si la ligne vient d'une demande, la demande passe
     * « Formation programmée » et ses agents sont inscrits. Renvoie la formation et les agents non inscrits.
     *
     * @return array{0: ActionFormation, 1: list<string>}
     */
    public function programmer(PlanLigne $l, array $d, User $u): array
    {
        if ($l->formation()->whereHas('session', fn ($q) => $q->where('statut', '!=', 'annulee'))->exists()) {
            throw new RegleMetierException('Cette ligne du plan est déjà programmée.');
        }

        return DB::transaction(function () use ($l, $d, $u) {
            $b = $l->besoin;
            $avecDemande = $b && in_array($b->statut, [StatutBesoin::AuPlan, StatutBesoin::Acceptee], true);
            $organisme = $d['organisme_id'] ?? $l->organisme_id;
            $a = $this->actions->creerFormation([
                'intitule' => $l->intitule, 'description' => $l->observations,
                'type' => $organisme ? 'externe' : ($l->type ?: 'interne'), 'organisme_id' => $organisme,
                'date_debut' => $d['date_debut'], 'date_fin' => $d['date_fin'], 'lieu' => $d['lieu'] ?? null,
                'places' => max(1, $b?->agents()->count() ?? 0, (int) $l->nb_agents), 'ligne_plan_id' => $l->id,
            ], $u, $avecDemande ? $b : null);
            $refus = [];
            if ($avecDemande && $b->agents()->exists()) {
                $refus = $this->inscriptions->inscrire($a->session()->firstOrFail(), $b->agents()->pluck('agents.id')->all(), $u)['refus'];
            }
            Audit::enregistrer('plan.programmation', 'plan', "Plan {$l->plan->annee} : « {$l->intitule} » programmée ({$a->reference})", ['ligne' => $l->id]);

            return [$a, $refus];
        });
    }

    /**
     * Un plan validé dont on a retiré toutes les formations n'est plus un plan validé (2.5.1) : sans cela, l'écran
     * affichait « Plan validé le … » au-dessus d'un plan vide. La validation est effacée et l'effacement journalisé.
     */
    public function annulerValidationSiVide(PlanAnnuel $p): void
    {
        if (! $p->estValide() || $p->lignes()->exists()) {
            return;
        }
        $avant = $p->valide_le->format('d/m/Y');
        $p->forceFill(['valide_le' => null, 'valide_par' => null, 'commentaire_validation' => null])->save();
        Audit::enregistrer('plan.validation_annulee', 'plan', "Plan annuel {$p->annee} : validation du {$avant} effacée (plus aucune formation au plan)");
    }

    public function valider(PlanAnnuel $p, User $u, ?string $commentaire): PlanAnnuel
    {
        if ($p->lignes()->doesntExist()) {
            throw new RegleMetierException('Le plan est vide : ajoutez au moins une formation avant de le valider.');
        }
        $p->valide_le = now();
        $p->valide_par = $u->id;
        $p->commentaire_validation = $commentaire ? mb_substr($commentaire, 0, 1000) : null;
        $p->save();
        Audit::enregistrer('plan.validation', 'plan', "Plan annuel {$p->annee} validé", ['lignes' => $p->lignes()->count()]);

        return $p;
    }
}
