<?php

namespace App\Services;

use App\Enums\StatutAction;
use App\Enums\StatutBesoin;
use App\Enums\StatutInscription;
use App\Enums\StatutSession as S;
use App\Exceptions\RegleMetierException;
use App\Models\ActionFormation;
use App\Models\BesoinFormation;
use App\Models\Formateur;
use App\Models\Seance;
use App\Models\SessionFormation;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Formations (parcours simplifié 1.5.0 : formation → participants → attestations). Depuis la version 1.3.0, une formation = une action (ce qui est enseigné, par qui, à quel coût)
 * et UNE session (quand, où, avec qui). Les deux sont créées et modifiées ensemble ; le statut de l'action
 * suit automatiquement celui de sa session. Pour un second groupe, on crée une autre formation.
 *
 * Aucun historique n'est effacé : chaque changement de dates, report ou annulation est consigné dans session_changements.
 */
class ActionService
{
    /** Champs saisis dans le formulaire « Formation » et appartenant à l'action. */
    public const CHAMPS_ACTION = ['intitule', 'description', 'domaine_id', 'objectifs_pedagogiques', 'type', 'modalite', 'organisme_id', 'public_cible'];

    /** Champs saisis dans le formulaire « Formation » et appartenant à la session. */
    public const CHAMPS_SESSION = ['date_debut', 'date_fin', 'heure_debut', 'heure_fin', 'duree_heures', 'lieu', 'places'];

    public function __construct(private BesoinService $besoins, private InscriptionService $inscriptions) {}

    /**
     * Crée la formation (action + session + séances). Si elle répond à une demande acceptée,
     * la demande passe au statut « Formation organisée » et son auteur est notifié.
     */
    public function creerFormation(array $donnees, User $user, ?BesoinFormation $besoin = null): ActionFormation
    {
        if ($besoin) {
            if (! in_array($besoin->statut, [StatutBesoin::Acceptee, StatutBesoin::AuPlan], true)) {
                throw new RegleMetierException("Seule une demande acceptée peut être organisée (la demande {$besoin->reference} est « {$besoin->statut->libelle()} »).");
            }
            if (ActionFormation::where('besoin_id', $besoin->id)->exists()) {
                throw new RegleMetierException("Une formation est déjà organisée pour la demande {$besoin->reference}.");
            }
        }

        return DB::transaction(function () use ($donnees, $user, $besoin) {
            $champs = array_intersect_key($donnees, array_flip(self::CHAMPS_ACTION));
            $champs['modalite'] = ($champs['modalite'] ?? null) ?: 'presentiel';
            $a = new ActionFormation($champs);
            $a->annee = (int) substr((string) $donnees['date_debut'], 0, 4);
            $a->reference = GenerateurReference::suivante('actions_formation', 'reference', 'ACT', $a->annee);
            $a->statut = StatutAction::Planifiee;
            $a->besoin_id = $besoin?->id;
            $a->ligne_plan_id = $donnees['ligne_plan_id'] ?? null;
            $a->cree_par = $user->id;
            $a->save();
            Audit::enregistrer('action.creation', $a, "Création de la formation {$a->reference}", $besoin ? ['demande' => $besoin->reference] : []);
            $s = $this->creerSession($a, array_filter(array_intersect_key($donnees, array_flip(self::CHAMPS_SESSION)), fn ($v) => $v !== null) + ['places' => 1], $user);
            if (! empty($donnees['formateurs'])) {
                $this->affecterFormateurs($s, $donnees['formateurs']);
            }
            if ($besoin) {
                $this->besoins->transition($besoin, 'organisation', $user,
                    "Formation {$a->reference} du {$s->date_debut->format('d/m/Y')} au {$s->date_fin->format('d/m/Y')}.");
            }

            return $a;
        });
    }

    /**
     * La RH accepte une demande en programmant la formation (dates, lieu, organisme) :
     * la demande est acceptée puis « formation organisée », la formation est créée et les agents de la demande y sont inscrits.
     * Une demande déjà acceptée (avant la version 1.5.0) est simplement programmée.
     */
    public function accepterEtProgrammer(BesoinFormation $b, array $donnees, User $user, ?string $commentaire = null): ActionFormation
    {
        return DB::transaction(function () use ($b, $donnees, $user, $commentaire) {
            if ($b->statut !== StatutBesoin::Acceptee) {
                $b = $this->besoins->transition($b, 'acceptation', $user, $commentaire);
            }
            $b->loadMissing('agents');
            $a = $this->creerFormation([
                'intitule' => $b->intitule,
                'description' => $b->contexte,
                'type' => empty($donnees['organisme_id']) ? 'interne' : 'externe',
                'organisme_id' => $donnees['organisme_id'] ?? null,
                'date_debut' => $donnees['date_debut'],
                'date_fin' => $donnees['date_fin'],
                'lieu' => $donnees['lieu'] ?? null,
                'places' => max(1, $b->agents->count()),
            ], $user, $b);
            if ($b->agents->isNotEmpty()) {
                // Les agents refusés (inactif, déjà inscrit ailleurs aux mêmes dates) sont signalés, jamais ignorés en silence
                $a->setAttribute('refus_inscription', $this->inscriptions->inscrire($a->session()->firstOrFail(), $b->agents->pluck('id')->all(), $user)['refus']);
            }

            return $a;
        });
    }

    /**
     * Modifie la formation. Les dates, horaires, lieu et places ne sont modifiables que tant que la session
     * n'est ni terminée ni annulée ; une formation sans session (donnée antérieure) reçoit ici ses dates.
     */
    public function modifierFormation(ActionFormation $a, array $donnees, User $user, ?string $motif = null): ActionFormation
    {
        return DB::transaction(function () use ($a, $donnees, $user, $motif) {
            $a->fill(array_filter(array_intersect_key($donnees, array_flip(self::CHAMPS_ACTION)), fn ($v, $k) => $k !== 'modalite' || $v, ARRAY_FILTER_USE_BOTH));
            $diff = Audit::differences($a, ['intitule', 'type', 'modalite', 'organisme_id']);
            $a->save();
            Audit::enregistrer('action.modification', $a, "Modification de la formation {$a->reference}", $diff);

            $champsSession = array_intersect_key($donnees, array_flip(self::CHAMPS_SESSION));
            $s = $a->session()->first();
            if ($s === null && isset($champsSession['date_debut'])) {
                $s = $this->creerSession($a, $champsSession, $user);
            } elseif ($s !== null && $champsSession !== [] && ! in_array($s->statut, [S::Annulee, S::Terminee], true)) {
                $this->modifierSession($s, $champsSession, $user, $motif);
            }
            // Les formateurs restent modifiables après la formation (rapport de réalisation, attestation du prestataire)
            if ($s !== null && array_key_exists('formateurs', $donnees)) {
                $this->affecterFormateurs($s, (array) $donnees['formateurs']);
            }

            return $a->fresh();
        });
    }

    /** Remplace la liste des formateurs de la session (internes et externes) ; le changement est journalisé. */
    public function affecterFormateurs(SessionFormation $s, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $avant = $s->formateurs()->pluck('formateurs.id')->sort()->values()->all();
        if ($avant === collect($ids)->sort()->values()->all()) {
            return;
        }
        $s->formateurs()->sync($ids);
        $noms = fn ($liste) => Formateur::whereIn('id', $liste)->get()->map(fn ($f) => $f->nom_complet)->implode(', ') ?: 'aucun';
        Audit::enregistrer('action.formateurs', $s->action, "Formateurs de la formation {$s->action->reference} : {$noms($ids)}", ['avant' => $noms($avant)]);
    }

    /** Programme la période de la formation (une seule session par formation). */
    public function creerSession(ActionFormation $a, array $donnees, User $user): SessionFormation
    {
        if (SessionFormation::where('action_id', $a->id)->exists()) {
            throw new RegleMetierException('Cette formation a déjà ses dates. Pour un autre groupe, créez une nouvelle formation (« Nouvelle formation similaire »).');
        }
        if (in_array($a->statut, [StatutAction::Annulee, StatutAction::Realisee], true)) {
            throw new RegleMetierException('Impossible de programmer une formation annulée ou réalisée.');
        }

        return DB::transaction(function () use ($a, $donnees) {
            $s = new SessionFormation($donnees);
            $s->action_id = $a->id;
            $s->statut = S::Planifiee;
            $rang = SessionFormation::where('action_id', $a->id)->lockForUpdate()->count() + 1;
            $s->code = $a->reference.'-S'.str_pad((string) $rang, 2, '0', STR_PAD_LEFT);
            $s->save();
            $this->genererSeances($s);
            $this->synchroniserAction($s);
            Audit::enregistrer('session.creation', $s, "Programmation de la formation {$s->code}", ['du' => $s->date_debut->toDateString(), 'au' => $s->date_fin->toDateString()]);

            return $s;
        });
    }

    public function modifierSession(SessionFormation $s, array $donnees, User $user, ?string $motif = null): SessionFormation
    {
        if (in_array($s->statut, [S::Annulee, S::Terminee], true)) {
            throw new RegleMetierException('Une formation annulée ou terminée n\'est plus modifiable.');
        }
        if (isset($donnees['places']) && (int) $donnees['places'] < $s->placesOccupees()) {
            throw new RegleMetierException("Le nombre de places ne peut pas être inférieur au nombre d'inscrits occupant une place ({$s->placesOccupees()}).");
        }

        return DB::transaction(function () use ($s, $donnees, $user, $motif) {
            $ancDebut = $s->date_debut->copy();
            $ancFin = $s->date_fin->copy();
            $s->fill($donnees);
            $datesChangees = $s->isDirty('date_debut') || $s->isDirty('date_fin');
            if ($datesChangees && $s->seances()->whereHas('presences')->exists()) {
                throw new RegleMetierException('Des présences ont été saisies sur cette formation avant la mise à jour : ses dates ne peuvent plus être modifiées. Annulez-la et créez-en une nouvelle si nécessaire.');
            }
            $s->save();
            if ($datesChangees) {
                $s->changements()->create(['type' => 'modification_dates', 'ancienne_date_debut' => $ancDebut, 'ancienne_date_fin' => $ancFin,
                    'nouvelle_date_debut' => $s->date_debut, 'nouvelle_date_fin' => $s->date_fin, 'motif' => $motif, 'user_id' => $user->id]);
                $s->seances()->delete();
                $this->genererSeances($s);
                $this->synchroniserAction($s);
            }
            Audit::enregistrer('session.modification', $s, "Modification des dates de la formation {$s->code}", ['dates_modifiees' => $datesChangees]);

            return $s;
        });
    }

    public function annuler(SessionFormation $s, string $motif, User $user): SessionFormation
    {
        if (trim($motif) === '') {
            throw new RegleMetierException('Le motif de l\'annulation est obligatoire.');
        }
        if ($s->statut === S::Terminee) {
            throw new RegleMetierException('Une formation terminée ne peut pas être annulée.');
        }

        return DB::transaction(function () use ($s, $motif, $user) {
            $s->changements()->create(['type' => 'annulation', 'ancien_statut' => $s->statut->value, 'nouveau_statut' => S::Annulee->value, 'motif' => $motif, 'user_id' => $user->id]);
            $s->statut = S::Annulee;
            $s->motif_statut = $motif;
            $s->save();
            // Les inscriptions sont conservées et marquées annulées (traçabilité)
            foreach ($s->inscriptions()->whereNotIn('statut', [StatutInscription::Desiste->value, StatutInscription::Remplace->value, StatutInscription::Annule->value])->get() as $i) {
                $i->statut = StatutInscription::Annule;
                $i->save();
                $i->evenements()->create(['evenement' => 'annulation_session', 'details' => $motif, 'user_id' => $user->id]);
            }
            $this->synchroniserAction($s);
            Audit::enregistrer('session.annulation', $s, "Annulation de la formation {$s->code}");

            return $s;
        });
    }

    /**
     * Fin de la formation : la RH indique qui l'a suivie. Les autres participants sont « non achevés ».
     * La formation passe à « Terminée » (l'action à « Réalisée ») ; les attestations deviennent possibles.
     * Peut être relancé sur une formation terminée pour corriger le suivi, sauf pour un participant
     * dont l'attestation est déjà émise.
     *
     * @param  list<int>  $suivies  identifiants des inscriptions des participants ayant suivi la formation
     */
    public function terminer(SessionFormation $s, array $suivies, User $user): SessionFormation
    {
        if ($s->statut === S::Annulee) {
            throw new RegleMetierException('Une formation annulée ne peut pas être terminée.');
        }
        $participants = $s->participantsActifs()->with('attestation')->get();
        if ($participants->isEmpty()) {
            throw new RegleMetierException('Ajoutez au moins un participant avant de terminer la formation.');
        }
        $suivies = array_map('intval', $suivies);
        foreach ($participants as $i) {
            if (! in_array($i->id, $suivies, true) && $i->attestation?->estValide()) {
                throw new RegleMetierException("L'attestation de {$i->agent->nom_complet} est déjà émise : annulez-la avant d'indiquer qu'il n'a pas suivi la formation.");
            }
        }

        return DB::transaction(function () use ($s, $participants, $suivies, $user) {
            $correction = $s->statut === S::Terminee;
            foreach ($participants as $i) {
                $suivi = in_array($i->id, $suivies, true);
                if ($correction && ($i->resultat === 'achevee') === $suivi) {
                    continue;
                }
                $i->resultat = $suivi ? 'achevee' : 'non_achevee';
                $i->taux_presence = $suivi ? 100 : 0;
                $i->resultat_calcule_le = now();
                $i->save();
                $i->evenements()->create(['evenement' => $correction ? 'correction_suivi' : 'suivi', 'details' => $suivi ? 'A suivi la formation' : 'N\'a pas suivi la formation', 'user_id' => $user->id]);
            }
            if (! $correction) {
                $s->changements()->create(['type' => 'changement_statut', 'ancien_statut' => $s->statut->value, 'nouveau_statut' => S::Terminee->value, 'user_id' => $user->id]);
                $s->statut = S::Terminee;
                $s->presences_validees_le = now(); // date d'enregistrement du suivi
                $s->presences_validees_par = $user->id;
                $s->save();
                $this->synchroniserAction($s);
            }
            Audit::enregistrer($correction ? 'session.correction_suivi' : 'session.terminee', $s, ($correction ? 'Correction du suivi de ' : 'Formation terminée : ').$s->code,
                ['suivie_par' => count($suivies), 'participants' => $participants->count()]);

            return $s->fresh();
        });
    }

    /** Une séance par jour ouvré (lundi–vendredi) entre les dates ; ajustable ensuite manuellement. */
    public function genererSeances(SessionFormation $s): void
    {
        $jours = collect(CarbonPeriod::create($s->date_debut, $s->date_fin))->filter(fn ($d) => ! $d->isWeekend());
        if ($jours->isEmpty()) {
            $jours = collect([$s->date_debut->copy()]);
        }
        $i = 1;
        foreach ($jours as $d) {
            Seance::create(['session_id' => $s->id, 'date' => $d->toDateString(), 'libelle' => 'Jour '.$i++, 'heure_debut' => $s->heure_debut, 'heure_fin' => $s->heure_fin]);
        }
    }

    /**
     * Le statut de l'action suit celui de sa session : programmée/confirmée → planifiée, en cours → en cours,
     * terminée → réalisée, annulée → annulée. L'année suit la date de début.
     */
    private function synchroniserAction(SessionFormation $s): void
    {
        $a = ActionFormation::whereKey($s->action_id)->firstOrFail();
        $statut = match ($s->statut) {
            S::EnCours => StatutAction::EnCours,
            S::Terminee => StatutAction::Realisee,
            S::Annulee => StatutAction::Annulee,
            S::Reportee => StatutAction::Reportee,
            default => StatutAction::Planifiee,
        };
        $a->statut = $statut;
        $a->motif_statut = in_array($statut, [StatutAction::Annulee, StatutAction::Reportee], true) ? $s->motif_statut : null;
        $a->annee = (int) $s->date_debut->format('Y');
        $a->save();
    }
}
