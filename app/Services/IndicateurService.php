<?php

namespace App\Services;

use App\Enums\StatutAction;
use App\Enums\StatutBesoin;
use App\Enums\StatutSession;
use App\Models\ActionFormation;
use App\Models\BesoinFormation;
use App\Models\Inscription;
use App\Models\Parametre;
use App\Models\SessionFormation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du tableau de bord, calculés exclusivement à partir de la base.
 *
 * Définitions (voir docs/GUIDE_UTILISATEUR.md § Indicateurs) :
 *  - inscription retenue : inscription au statut inscrit, convoqué ou confirmé (hors attente, désistement, remplacement, annulation) ;
 *  - participation : inscription retenue marquée « a suivi la formation » (ou, avant 1.5.0, ayant au moins une présence) ;
 *  - agent formé : agent UNIQUE ayant au moins une inscription au résultat « achevée » ;
 *  - taux de participation = participations / inscriptions retenues des sessions dont le relevé est validé ;
 *  - taux d'achèvement = inscriptions achevées / inscriptions retenues des sessions dont le relevé est validé ;
 *  - demandes à organiser = demandes acceptées pour lesquelles aucune formation n'est encore créée ;
 */
class IndicateurService
{
    public const RETENUS = ['inscrit', 'convoque', 'confirme'];

    /** Filtres : annee, direction_id, service_id, domaine_id, type */
    public function actions(array $f): Builder
    {
        return ActionFormation::query()
            ->when($f['annee'] ?? null, fn ($q, $a) => $q->where('annee', $a))
            ->when($f['domaine_id'] ?? null, fn ($q, $d) => $q->where('domaine_id', $d))
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when(($f['direction_id'] ?? null) || ($f['service_id'] ?? null), fn ($q) => $q->whereHas('sessions.inscriptions.agent', fn ($a) => $this->filtreAgent($a, $f)));
    }

    public function inscriptions(array $f): Builder
    {
        return Inscription::query()
            ->whereHas('session.action', function ($q) use ($f) {
                $q->when($f['annee'] ?? null, fn ($q, $a) => $q->where('annee', $a))
                    ->when($f['domaine_id'] ?? null, fn ($q, $d) => $q->where('domaine_id', $d))
                    ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t));
            })
            ->when(($f['direction_id'] ?? null) || ($f['service_id'] ?? null), fn ($q) => $q->whereHas('agent', fn ($a) => $this->filtreAgent($a, $f)));
    }

    private function filtreAgent(Builder $a, array $f): Builder
    {
        return $a->when($f['direction_id'] ?? null, fn ($q, $d) => $q->where('direction_id', $d))
            ->when($f['service_id'] ?? null, fn ($q, $s) => $q->where('service_id', $s));
    }

    public function tableauDeBord(array $f): array
    {
        $parStatut = $this->actions($f)->select('statut', DB::raw('COUNT(*) n'))->groupBy('statut')->pluck('n', 'statut');
        $parType = $this->actions($f)->select('type', DB::raw('COUNT(*) n'))->groupBy('type')->pluck('n', 'type');

        $retenues = $this->inscriptions($f)->whereIn('statut', self::RETENUS);
        $validees = (clone $retenues)->whereHas('session', fn ($q) => $q->whereNotNull('presences_validees_le'));
        $nbValidees = (clone $validees)->count();
        $nbParticipations = (clone $validees)->where(fn ($q) => $q->where('resultat', 'achevee')->orWhereHas('presences', fn ($p) => $p->whereIn('statut', ['present', 'retard', 'partiel'])))->count();
        $nbAchevees = (clone $validees)->where('resultat', 'achevee')->count();

        return [
            'actions' => [
                'planifiees' => (int) ($parStatut[StatutAction::Planifiee->value] ?? 0),
                'en_cours' => (int) ($parStatut[StatutAction::EnCours->value] ?? 0),
                'realisees' => (int) ($parStatut[StatutAction::Realisee->value] ?? 0),
                'reportees' => (int) ($parStatut[StatutAction::Reportee->value] ?? 0),
                'annulees' => (int) ($parStatut[StatutAction::Annulee->value] ?? 0),
                'internes' => (int) ($parType['interne'] ?? 0),
                'externes' => (int) ($parType['externe'] ?? 0),
                'mixtes' => (int) ($parType['mixte'] ?? 0),
            ],
            'agents_formes' => (clone $this->inscriptions($f))->where('resultat', 'achevee')->distinct()->count('agent_id'),
            'inscriptions_retenues' => (clone $retenues)->count(),
            'demandes_en_attente' => BesoinFormation::query()
                ->where('statut', StatutBesoin::Soumise->value)
                ->when($f['annee'] ?? null, fn ($q, $a) => $q->where('annee', $a))
                ->when($f['service_id'] ?? null, fn ($q, $s) => $q->where('service_id', $s))
                ->when($f['direction_id'] ?? null, fn ($q, $d) => $q->whereHas('service', fn ($s) => $s->where('direction_id', $d)))
                ->count(),
            'taux_participation' => $nbValidees ? round(100 * $nbParticipations / $nbValidees, 1) : null,
            'taux_achevement' => $nbValidees ? round(100 * $nbAchevees / $nbValidees, 1) : null,
            'base_taux' => $nbValidees,
            'demandes_a_organiser' => BesoinFormation::query()
                ->where('statut', StatutBesoin::Acceptee->value)
                ->when($f['annee'] ?? null, fn ($q, $a) => $q->where('annee', $a))
                ->when($f['service_id'] ?? null, fn ($q, $s) => $q->where('service_id', $s))
                ->when($f['direction_id'] ?? null, fn ($q, $d) => $q->whereHas('service', fn ($s) => $s->where('direction_id', $d)))
                ->count(),
            'par_direction' => $this->formationsParDirection($f),
            'alertes' => $this->alertes(),
        ];
    }

    /** Nombre d'actions distinctes ayant au moins un participant retenu de la direction / du service. */
    public function formationsParDirection(array $f): array
    {
        return DB::table('inscriptions')
            ->join('sessions_formation as s', 's.id', '=', 'inscriptions.session_id')
            ->join('actions_formation as a', fn ($j) => $j->on('a.id', '=', 's.action_id')->whereNull('a.deleted_at'))
            ->join('agents as ag', 'ag.id', '=', 'inscriptions.agent_id')
            ->leftJoin('directions as d', 'd.id', '=', 'ag.direction_id')
            ->leftJoin('services as sv', 'sv.id', '=', 'ag.service_id')
            ->whereIn('inscriptions.statut', self::RETENUS)
            ->when($f['annee'] ?? null, fn ($q, $a) => $q->where('a.annee', $a))
            ->when($f['domaine_id'] ?? null, fn ($q, $d) => $q->where('a.domaine_id', $d))
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('a.type', $t))
            ->when($f['direction_id'] ?? null, fn ($q, $d) => $q->where('ag.direction_id', $d))
            ->when($f['service_id'] ?? null, fn ($q, $s) => $q->where('ag.service_id', $s))
            ->groupBy('d.nom', 'sv.nom')
            ->orderBy('d.nom')->orderBy('sv.nom')
            ->select(DB::raw("COALESCE(d.nom, 'Non renseignée') direction"), DB::raw("COALESCE(sv.nom, 'Non renseigné') service"),
                DB::raw('COUNT(DISTINCT a.id) actions'), DB::raw('COUNT(DISTINCT inscriptions.agent_id) agents'))
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    /** Alertes opérationnelles (non filtrées par année : elles concernent l'activité courante). */
    public function alertes(): array
    {
        $delai = (int) Parametre::valeur('notifications.delai_rappel_session_jours', 7);
        $aujourdhui = now()->startOfDay();

        return [
            'sessions_a_venir' => SessionFormation::with('action')->whereIn('statut', [StatutSession::Planifiee->value, StatutSession::Confirmee->value])
                ->whereDate('date_debut', '>=', $aujourdhui)->whereDate('date_debut', '<=', $aujourdhui->copy()->addDays($delai))
                ->orderBy('date_debut')->limit(10)->get(),
            'sessions_non_cloturees' => SessionFormation::with('action')->whereNotIn('statut', [StatutSession::Terminee->value, StatutSession::Annulee->value])
                ->whereDate('date_fin', '<', $aujourdhui)->orderBy('date_fin')->limit(10)->get(),
            'besoins_a_completer' => BesoinFormation::where('statut', StatutBesoin::ACompleter->value)->count(),
            'attestations_a_emettre' => Inscription::where('resultat', 'achevee')->whereDoesntHave('attestation', fn ($q) => $q->whereNull('annulee_le'))->count(),
        ];
    }

    /**
     * Activité mois par mois de l'exercice (2.6.0), pour les graphiques du tableau de bord.
     * Une formation est rattachée au mois de son début. Participants = inscriptions retenues.
     *
     * @return list<array{mois:int, terminees:int, programmees:int, annulees:int, participants:int}>
     */
    public function parMois(array $f): array
    {
        $ids = $this->actions($f)->pluck('id');
        $mois = array_fill(1, 12, ['terminees' => 0, 'programmees' => 0, 'annulees' => 0, 'participants' => 0]);
        if ($ids->isNotEmpty()) {
            $sessions = SessionFormation::whereIn('action_id', $ids)->whereYear('date_debut', (int) $f['annee'])
                ->withCount(['inscriptions as retenues' => fn ($q) => $q->whereIn('statut', self::RETENUS)])->get(['id', 'date_debut', 'statut']);
            foreach ($sessions as $s) {
                $m = (int) $s->date_debut->format('n');
                $cle = match ($s->statut) {
                    StatutSession::Terminee => 'terminees',
                    StatutSession::Annulee => 'annulees',
                    default => 'programmees',
                };
                $mois[$m][$cle]++;
                if ($s->statut !== StatutSession::Annulee) {
                    $mois[$m]['participants'] += (int) $s->retenues;
                }
            }
        }

        return array_map(fn ($m, $v) => ['mois' => $m] + $v, array_keys($mois), $mois);
    }

    /**
     * Chiffres clés d'un exercice, pour les comparer à l'exercice précédent (2.6.0).
     *
     * @return array{formations:int, agents_formes:int, demandes:int}
     */
    public function resume(array $f): array
    {
        return [
            'formations' => $this->actions($f)->where('statut', '!=', StatutAction::Annulee->value)->count(),
            'agents_formes' => $this->inscriptions($f)->where('resultat', 'achevee')->distinct()->count('agent_id'),
            'demandes' => BesoinFormation::query()->where('statut', '!=', StatutBesoin::Brouillon->value)
                ->when($f['annee'] ?? null, fn ($q, $a) => $q->where('annee', $a))
                ->when($f['service_id'] ?? null, fn ($q, $s) => $q->where('service_id', $s))
                ->when($f['direction_id'] ?? null, fn ($q, $d) => $q->whereHas('service', fn ($s) => $s->where('direction_id', $d)))
                ->count(),
        ];
    }

    /** Variation en % (arrondie à l'unité) ; null si l'exercice précédent est à zéro. */
    public static function variation(int|float $actuel, int|float $precedent): ?float
    {
        return $precedent > 0 ? round(100 * ($actuel - $precedent) / $precedent) : null;
    }

    /** Comparatif annuel sur les N dernières années disposant de données. */
    public function comparatifAnnuel(int $nb = 4): array
    {
        $annees = ActionFormation::select('annee')->distinct()->orderByDesc('annee')->limit($nb)->pluck('annee')->sort()->values();
        $res = [];
        foreach ($annees as $a) {
            $f = ['annee' => $a];
            $ids = $this->actions($f)->pluck('id');
            $res[] = [
                'annee' => $a,
                'actions' => $ids->count(),
                'realisees' => $this->actions($f)->where('statut', StatutAction::Realisee->value)->count(),
                'agents_formes' => $this->inscriptions($f)->where('resultat', 'achevee')->distinct()->count('agent_id'),
            ];
        }

        return $res;
    }
}
