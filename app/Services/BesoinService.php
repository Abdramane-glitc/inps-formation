<?php

namespace App\Services;

use App\Enums\StatutBesoin as S;
use App\Exceptions\RegleMetierException;
use App\Models\AnneeReference;
use App\Models\BesoinFormation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie des demandes de formation (parcours simplifié, version 1.4.0).
 *
 *   envoyée ─accepter et programmer (dates, lieu)→ formation programmée (acceptée puis organisée dans la même opération)
 *      │  └─refuser (motif)→ refusée
 *      └─demander des précisions (motif)→ à compléter ─renvoyer→ envoyée
 *
 * Une demande est envoyée à la RH dès sa création (pas de brouillon). L'année de la demande est déterminée
 * automatiquement (année en cours, ou prochaine année ouverte si l'année en cours est clôturée).
 */
class BesoinService
{
    /** action => [statuts de départ autorisés, statut d'arrivée, commentaire obligatoire] */
    private const TRANSITIONS = [
        'soumission' => [[S::Brouillon, S::ACompleter], S::Soumise, false],
        'demande_complement' => [[S::Soumise], S::ACompleter, true],
        'acceptation' => [[S::Soumise], S::Acceptee, false],
        'rejet' => [[S::Soumise], S::Rejetee, true],
        'inscription_plan' => [[S::Soumise], S::AuPlan, false],
        'retrait_plan' => [[S::AuPlan], S::Soumise, true],
        'organisation' => [[S::Acceptee, S::AuPlan], S::Organisee, false],
    ];

    public function __construct(private NotificationService $notifications) {}

    /** Crée la demande et l'envoie aussitôt à la RH. */
    public function creer(array $donnees, User $auteur, array $agentIds = []): BesoinFormation
    {
        $donnees['service_id'] ??= $auteur->service_id;
        $this->verifierService($donnees['service_id'] ?? null, $auteur);
        $donnees['annee'] ??= self::anneeDeLaDemande();
        $donnees['nb_participants'] = count(array_unique($agentIds)) ?: null; // le nombre est celui des agents cochés

        $b = DB::transaction(function () use ($donnees, $auteur, $agentIds) {
            $b = new BesoinFormation($donnees);
            $b->reference = GenerateurReference::suivante('besoins_formation', 'reference', 'BES', (int) $donnees['annee']);
            $b->statut = S::Soumise;
            $b->soumis_le = now();
            $b->auteur_id = $auteur->id;
            $b->save();
            $b->agents()->sync($agentIds);
            $b->decisions()->create(['action' => 'soumission', 'statut_apres' => S::Soumise->value, 'user_id' => $auteur->id]);
            Audit::enregistrer('besoin.creation', $b, "Demande {$b->reference} envoyée");

            return $b;
        });
        $this->notifierTransition($b, 'soumission');

        return $b;
    }

    /**
     * Année d'une nouvelle demande : l'année en cours si elle est ouverte (créée ouverte si elle n'existe pas encore),
     * sinon la prochaine année ouverte.
     */
    public static function anneeDeLaDemande(): int
    {
        $courante = (int) now()->year;
        $ref = AnneeReference::firstOrCreate(['annee' => $courante], ['statut' => 'ouverte']);
        if ($ref->estOuverte()) {
            return $courante;
        }
        $suivante = AnneeReference::where('statut', 'ouverte')->where('annee', '>', $courante)->orderBy('annee')->value('annee');
        if (! $suivante) {
            throw new RegleMetierException("L'année $courante est clôturée et aucune année suivante n'est ouverte : la RH doit en ouvrir une (Administration › Référentiels › Années).");
        }

        return (int) $suivante;
    }

    public function modifier(BesoinFormation $b, array $donnees, User $user, array $agentIds = []): BesoinFormation
    {
        if (! $b->estModifiable()) {
            throw new RegleMetierException('Cette demande ne peut plus être modifiée dans son état actuel ('.$b->statut->libelle().').');
        }
        if (empty($donnees['service_id'])) {
            unset($donnees['service_id']); // le service d'une demande ne change pas sans demande explicite de la RH
        }
        $this->verifierService($donnees['service_id'] ?? $b->service_id, $user);

        $donnees['nb_participants'] = count(array_unique($agentIds)) ?: null;

        return DB::transaction(function () use ($b, $donnees, $agentIds) {
            $b->fill($donnees);
            $champs = array_keys($b->getDirty());
            $b->save();
            $b->agents()->sync($agentIds);
            Audit::enregistrer('besoin.modification', $b, "Modification du besoin {$b->reference}", ['champs' => $champs]);

            return $b;
        });
    }

    public function transition(BesoinFormation $b, string $action, User $user, ?string $commentaire = null): BesoinFormation
    {
        if (! isset(self::TRANSITIONS[$action])) {
            throw new RegleMetierException('Action inconnue.');
        }
        [$depart, $arrivee, $motifObligatoire] = self::TRANSITIONS[$action];

        if ($action === 'soumission' && $b->annee) {
            $annee = AnneeReference::where('annee', $b->annee)->first();
            if ($annee && ! $annee->estOuverte()) {
                throw new RegleMetierException("L'année {$b->annee} est clôturée : aucune nouvelle soumission n'est possible.");
            }
        }
        if ($motifObligatoire && trim((string) $commentaire) === '') {
            throw new RegleMetierException('Un commentaire motivé est obligatoire pour cette décision.');
        }

        return DB::transaction(function () use ($b, $action, $depart, $arrivee, $user, $commentaire) {
            $b = BesoinFormation::whereKey($b->id)->lockForUpdate()->firstOrFail();
            if (! in_array($b->statut, $depart, true)) {
                throw new RegleMetierException("Action impossible : la demande est « {$b->statut->libelle()} ».");
            }
            $avant = $b->statut;
            $b->statut = $arrivee;
            if ($action === 'soumission') {
                $b->soumis_le = now();
            }
            $b->save();
            $b->decisions()->create([
                'action' => $action, 'statut_avant' => $avant->value, 'statut_apres' => $arrivee->value,
                'commentaire' => $commentaire, 'user_id' => $user->id,
            ]);
            Audit::enregistrer('besoin.'.$action, $b, "{$b->reference} : {$avant->libelle()} → {$arrivee->libelle()}");
            $this->notifierTransition($b, $action, $commentaire);

            return $b;
        });
    }

    private function verifierService(?int $serviceId, User $user): void
    {
        if (! $serviceId) {
            throw new RegleMetierException('Le service demandeur est obligatoire.');
        }
        if (! $user->aPermission('besoins.creer_tous_services') && (int) $serviceId !== (int) $user->service_id) {
            throw new RegleMetierException('Vous ne pouvez déclarer des besoins que pour votre propre service.');
        }
    }

    private function notifierTransition(BesoinFormation $b, string $action, ?string $commentaireRh = null): void
    {
        $url = route('besoins.show', $b);
        match ($action) {
            'soumission' => $this->notifications->notifierPermission('besoins.instruire', 'besoin_soumis', 'Nouvelle demande de formation', "Le {$b->service->nom} demande « {$b->intitule} » ({$b->reference}).", $url),
            'demande_complement' => $this->notifications->notifier($b->auteur, 'besoin_a_completer', 'Précisions demandées par la RH', "La demande {$b->reference} est à compléter : {$commentaireRh}", $url),
            'rejet' => $this->notifications->notifier($b->auteur, 'besoin_decision', 'Décision sur votre demande', "La demande {$b->reference} est désormais : {$b->statut->libelle()}.", $url),
            'inscription_plan' => $this->notifications->notifier($b->auteur, 'besoin_decision', 'Demande acceptée : inscrite au plan annuel', "La formation demandée ({$b->reference}) est inscrite au plan annuel. ".($commentaireRh ? $commentaireRh.' ' : '').'Les dates seront fixées plus tard.', $url),
            'organisation' => $this->notifications->notifier($b->auteur, 'besoin_decision', 'Demande acceptée : formation programmée', "La formation demandée ({$b->reference}) est programmée : consultez les dates sur la demande.", $url),
            default => null,
        };
    }
}
