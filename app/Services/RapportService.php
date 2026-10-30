<?php

namespace App\Services;

use App\Enums\StatutAction;
use App\Enums\StatutBesoin;
use App\Enums\StatutSession;
use App\Models\ActionFormation;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;

/**
 * Rapports statistiques tabulaires (écran, impression, PDF, Excel).
 * Chaque rapport renvoie : titre, colonnes, lignes (tableaux de valeurs), note de calcul.
 * Les règles distinguent agents uniques, inscriptions et participations (aucun double comptage).
 */
class RapportService
{
    public const RAPPORTS = [
        'formations_annee' => 'Formations par année et par statut',
        'agents_formes' => 'Agents formés par direction et service',
        'agents_sans_formation' => 'Agents n\'ayant suivi aucune formation sur la période',
        'domaines' => 'Répartition par domaine de compétence',
        'interne_externe' => 'Formations internes et externes',
        'jours_formation' => 'Nombre de jours de formation',
        'suivi_demandes' => 'Suivi des demandes de formation',
        'presence_achevement' => 'Suivi des formations par les participants',
        'prestataires' => 'Prestataires sollicités',
        'reportees_annulees' => 'Formations reportées ou annulées',
    ];

    /** @return array{titre:string, colonnes:list<string>, lignes:list<list<mixed>>, note:string, montants?:list<int>} */
    public function generer(string $code, array $f): array
    {
        $annee = $f['annee'] ?? null;
        $r = match ($code) {
            'formations_annee' => $this->formationsAnnee($f),
            'agents_formes' => $this->agentsFormes($f),
            'agents_sans_formation' => $this->agentsSansFormation($f),
            'domaines' => $this->domaines($f),
            'interne_externe' => $this->interneExterne($f),
            'jours_formation' => $this->joursFormation($f),
            'suivi_demandes' => $this->suiviDemandes($f),
            'presence_achevement' => $this->presenceAchevement($f),
            'prestataires' => $this->prestataires($f),
            'reportees_annulees' => $this->reporteesAnnulees($f),
            default => abort(404),
        };
        $r['titre'] = self::RAPPORTS[$code].($annee ? " — $annee" : '');

        return $r + ['montants' => []];
    }

    private function baseActions(array $f)
    {
        return DB::table('actions_formation as a')->whereNull('a.deleted_at') // formations à la corbeille exclues
            ->when($f['annee'] ?? null, fn ($q, $v) => $q->where('a.annee', $v))
            ->when($f['domaine_id'] ?? null, fn ($q, $v) => $q->where('a.domaine_id', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('a.type', $v));
    }

    /** Jointure inscriptions retenues + agents avec filtres direction / service. */
    private function baseInscriptions(array $f)
    {
        return $this->baseActions($f)
            ->join('sessions_formation as s', 's.action_id', '=', 'a.id')
            ->join('inscriptions as i', 'i.session_id', '=', 's.id')
            ->join('agents as ag', 'ag.id', '=', 'i.agent_id')
            ->whereIn('i.statut', IndicateurService::RETENUS)
            ->when($f['direction_id'] ?? null, fn ($q, $v) => $q->where('ag.direction_id', $v))
            ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('ag.service_id', $v));
    }

    private function formationsAnnee(array $f): array
    {
        $rows = $this->baseActions($f)->select('a.annee', 'a.statut', DB::raw('COUNT(*) n'))->groupBy('a.annee', 'a.statut')->orderBy('a.annee')->get();
        $statuts = StatutAction::options();
        $par = [];
        foreach ($rows as $r) {
            $par[$r->annee][$r->statut] = $r->n;
        }
        $lignes = [];
        foreach ($par as $annee => $v) {
            $l = [$annee];
            foreach (array_keys($statuts) as $s) {
                $l[] = (int) ($v[$s] ?? 0);
            }
            $l[] = array_sum($v);
            $lignes[] = $l;
        }

        return ['colonnes' => array_merge(['Année'], array_values($statuts), ['Total']), 'lignes' => $lignes,
            'note' => 'Nombre de formations par année (année de la date de début) et statut de réalisation.'];
    }

    private function agentsFormes(array $f): array
    {
        $rows = $this->baseInscriptions($f)
            ->leftJoin('directions as d', 'd.id', '=', 'ag.direction_id')
            ->leftJoin('services as sv', 'sv.id', '=', 'ag.service_id')
            ->groupBy('d.nom', 'sv.nom')->orderBy('d.nom')->orderBy('sv.nom')
            ->select(DB::raw("COALESCE(d.nom,'—') d"), DB::raw("COALESCE(sv.nom,'—') s"),
                DB::raw('COUNT(DISTINCT CASE WHEN i.resultat = \'achevee\' THEN i.agent_id END) formes'),
                DB::raw('COUNT(DISTINCT i.id) inscriptions'),
                DB::raw('SUM(CASE WHEN i.resultat = \'achevee\' THEN 1 ELSE 0 END) achevees'))
            ->get();

        return ['colonnes' => ['Direction', 'Service', 'Agents formés (uniques)', 'Inscriptions retenues', 'Inscriptions achevées'],
            'lignes' => $rows->map(fn ($r) => [$r->d, $r->s, (int) $r->formes, (int) $r->inscriptions, (int) $r->achevees])->all(),
            'note' => 'Agents formés = agents distincts ayant au moins une formation achevée. Un agent formé plusieurs fois n\'est compté qu\'une fois par ligne ; les inscriptions sont comptées séparément.'];
    }

    private function agentsSansFormation(array $f): array
    {
        $du = $f['du'] ?? (($f['annee'] ?? now()->year).'-01-01');
        $au = $f['au'] ?? (($f['annee'] ?? now()->year).'-12-31');
        $agents = Agent::with(['direction', 'service'])->where('actif', true)
            ->when($f['direction_id'] ?? null, fn ($q, $v) => $q->where('direction_id', $v))
            ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))
            ->whereDoesntHave('inscriptions', fn ($q) => $q->where('resultat', 'achevee')
                ->whereHas('session', fn ($s) => $s->whereDate('date_debut', '<=', $au)->whereDate('date_fin', '>=', $du)))
            ->orderBy('nom')->orderBy('prenom')->get();

        return ['colonnes' => ['Matricule', 'Nom et prénom', 'Direction', 'Service', 'Fonction'],
            'lignes' => $agents->map(fn ($a) => [$a->matricule, $a->nom_complet, $a->direction?->nom, $a->service?->nom, $a->fonction])->all(),
            'note' => "Agents actifs sans aucune formation achevée se déroulant entre le $du et le $au."];
    }

    private function domaines(array $f): array
    {
        $rows = $this->baseActions($f)->leftJoin('domaines as dm', 'dm.id', '=', 'a.domaine_id')
            ->groupBy('dm.nom')->orderBy('dm.nom')
            ->select(DB::raw("COALESCE(dm.nom,'Non renseigné') nom"), DB::raw('COUNT(*) n'),
                DB::raw("SUM(CASE WHEN a.statut='realisee' THEN 1 ELSE 0 END) r"))->get();
        $formes = $this->baseInscriptions($f)->leftJoin('domaines as dm', 'dm.id', '=', 'a.domaine_id')->where('i.resultat', 'achevee')
            ->groupBy('dm.nom')->select(DB::raw("COALESCE(dm.nom,'Non renseigné') nom"), DB::raw('COUNT(DISTINCT i.agent_id) n'))->pluck('n', 'nom');

        return ['colonnes' => ['Domaine', 'Formations', 'Formations réalisées', 'Agents formés (uniques)'],
            'lignes' => $rows->map(fn ($r) => [$r->nom, (int) $r->n, (int) $r->r, (int) ($formes[$r->nom] ?? 0)])->all(),
            'note' => 'Répartition des formations par domaine de compétence ; agents uniques par domaine.'];
    }

    private function interneExterne(array $f): array
    {
        $rows = $this->baseActions($f)->groupBy('a.type')->select('a.type', DB::raw('COUNT(*) n'), DB::raw("SUM(CASE WHEN a.statut='realisee' THEN 1 ELSE 0 END) r"))->get();
        $total = max(1, $rows->sum('n'));

        return ['colonnes' => ['Type', 'Formations', 'Part (%)', 'Réalisées'],
            'lignes' => $rows->map(fn ($r) => [ActionFormation::TYPES[$r->type] ?? $r->type, (int) $r->n, round(100 * $r->n / $total, 1), (int) $r->r])->all(),
            'note' => 'Part calculée sur le nombre total de formations correspondant aux filtres.'];
    }

    private function joursFormation(array $f): array
    {
        $rows = $this->baseInscriptions($f)->where('i.resultat', 'achevee')
            ->groupBy('a.reference', 'a.intitule')
            ->select('a.reference', 'a.intitule', DB::raw('COUNT(DISTINCT s.id) sessions'),
                DB::raw('SUM((SELECT COUNT(*) FROM seances se WHERE se.session_id = s.id)) jours_agents'),
                DB::raw('COUNT(DISTINCT i.agent_id) agents'))
            ->orderBy('a.reference')->get();

        return ['colonnes' => ['Référence', 'Formation', 'Agents formés', 'Jours-agents de formation'],
            'lignes' => $rows->map(fn ($r) => [$r->reference, $r->intitule, (int) $r->agents, (int) $r->jours_agents])->all(),
            'note' => 'Jours-agents = somme, pour chaque inscription achevée, du nombre de journées de la formation.'];
    }

    /** Chaque demande de l'année, sa décision et la formation organisée en réponse (le cas échéant). */
    private function suiviDemandes(array $f): array
    {
        $rows = DB::table('besoins_formation as b')
            ->join('services as sv', 'sv.id', '=', 'b.service_id')
            ->leftJoin('actions_formation as a', fn ($j) => $j->on('a.besoin_id', '=', 'b.id')->whereNull('a.deleted_at'))
            ->leftJoin('sessions_formation as s', 's.action_id', '=', 'a.id')
            ->whereNull('b.deleted_at')->where('b.statut', '!=', StatutBesoin::Brouillon->value)
            ->when($f['annee'] ?? null, fn ($q, $v) => $q->where('b.annee', $v))
            ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('b.service_id', $v))
            ->when($f['direction_id'] ?? null, fn ($q, $v) => $q->where('sv.direction_id', $v))
            ->orderBy('b.reference')
            ->get(['b.reference', 'b.intitule', 'sv.nom as service', 'b.statut', 'a.reference as formation', 's.date_debut', 's.date_fin', 's.statut as statut_formation']);
        $acceptees = $rows->whereIn('statut', [StatutBesoin::Acceptee->value, StatutBesoin::AuPlan->value, StatutBesoin::Organisee->value])->count();
        $organisees = $rows->where('statut', StatutBesoin::Organisee->value)->count();

        return ['colonnes' => ['Référence', 'Demande', 'Service', 'Décision', 'Formation', 'Dates', 'Statut de la formation'],
            // « Décision » : la décision de la RH ; l'avancement de la formation est dans la dernière colonne
            'lignes' => $rows->map(fn ($r) => [$r->reference, $r->intitule, $r->service,
                match ($r->statut) {
                    StatutBesoin::Organisee->value => 'Acceptée', StatutBesoin::AuPlan->value => 'Acceptée (inscrite au plan)', default => StatutBesoin::tryFrom($r->statut)?->libelle() ?? $r->statut
                },
                $r->formation ?? '—',
                $r->date_debut ? date('d/m/Y', strtotime($r->date_debut)).' – '.date('d/m/Y', strtotime($r->date_fin)) : '—',
                $r->statut_formation ? (StatutSession::tryFrom($r->statut_formation)?->libelle() ?? $r->statut_formation) : '—'])->all(),
            'note' => "Demandes soumises (brouillons exclus). $acceptees demande(s) acceptée(s), dont $organisees avec une formation programmée."];
    }

    private function presenceAchevement(array $f): array
    {
        $rows = $this->baseInscriptions($f)->whereNotNull('s.presences_validees_le')
            ->groupBy('s.code', 'a.intitule')
            ->select('s.code', 'a.intitule', DB::raw('COUNT(*) n'), DB::raw("SUM(CASE WHEN i.resultat='achevee' THEN 1 ELSE 0 END) ach"))
            ->orderBy('s.code')->get();

        return ['colonnes' => ['Code', 'Formation', 'Participants', 'Ont suivi la formation', 'Taux de suivi (%)'],
            'lignes' => $rows->map(fn ($r) => [$r->code, $r->intitule, (int) $r->n, (int) $r->ach, $r->n ? round(100 * $r->ach / $r->n, 1) : null])->all(),
            'note' => 'Formations terminées uniquement. Un participant a suivi la formation si la RH l\'a coché à la fin de la formation.'];
    }

    private function prestataires(array $f): array
    {
        $rows = $this->baseActions($f)->join('organismes as o', 'o.id', '=', 'a.organisme_id')
            ->groupBy('o.raison_sociale')
            ->select('o.raison_sociale', DB::raw('COUNT(DISTINCT a.id) n'))
            ->orderByDesc('n')->get();
        $notes = DB::table('appreciations_prestataires as ap')->join('organismes as o', 'o.id', '=', 'ap.organisme_id')
            ->groupBy('o.raison_sociale')->select('o.raison_sociale', DB::raw('AVG(ap.note) m'))->pluck('m', 'raison_sociale');

        return ['colonnes' => ['Organisme', 'Formations', 'Appréciation moyenne /5'],
            'lignes' => $rows->map(fn ($r) => [$r->raison_sociale, (int) $r->n, isset($notes[$r->raison_sociale]) ? round((float) $notes[$r->raison_sociale], 2) : null])->all(),
            'note' => 'Organismes rattachés aux formations correspondant aux filtres.'];
    }

    private function reporteesAnnulees(array $f): array
    {
        $rows = $this->baseActions($f)->join('sessions_formation as s', 's.action_id', '=', 'a.id')
            ->join('session_changements as c', 'c.session_id', '=', 's.id')
            ->whereIn('c.type', ['report', 'annulation'])
            ->select('s.code', 'a.intitule', 'c.type', 'c.ancienne_date_debut', 'c.nouvelle_date_debut', 'c.motif', 'c.created_at')
            ->orderBy('c.created_at')->get();

        return ['colonnes' => ['Code', 'Formation', 'Événement', 'Ancienne date', 'Nouvelle date', 'Motif', 'Date de l\'opération'],
            'lignes' => $rows->map(fn ($r) => [$r->code, $r->intitule, $r->type === 'report' ? 'Report' : 'Annulation',
                $r->ancienne_date_debut ? date('d/m/Y', strtotime($r->ancienne_date_debut)) : '', $r->nouvelle_date_debut ? date('d/m/Y', strtotime($r->nouvelle_date_debut)) : '',
                $r->motif, date('d/m/Y H:i', strtotime($r->created_at))])->all(),
            'note' => 'Historique complet des reports et annulations (aucune donnée n\'est effacée).'];
    }
}
