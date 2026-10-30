<?php

namespace App\Services;

use App\Enums\StatutBesoin;
use App\Enums\StatutSession;
use App\Models\BesoinFormation;
use App\Models\EvaluationImpact;
use App\Models\Inscription;
use App\Models\SessionFormation;
use App\Support\Similarite;

/**
 * Aide à la décision de la RH sur une demande (2.1.0), calculée à partir des données de l'application :
 * diagnostic du service, agents déjà formés à un sujet proche, demandes semblables d'autres services,
 * résultat (à 3–6 mois) des formations semblables déjà réalisées. Rien n'est décidé automatiquement.
 */
class AideDecisionService
{
    /** Ancienneté maximale des formations prises en compte. */
    public const ANNEES = 3;

    /** @return list<array{niveau:string, titre:string, details:list<string>}> niveau : ok | info | alerte */
    public function signaux(BesoinFormation $b): array
    {
        $s = [];
        if ($c = $b->conclusionDiagnostic()) {
            $s[] = ['niveau' => ['vert' => 'ok', 'orange' => 'alerte', 'rouge' => 'alerte'][$c[0]], 'titre' => $c[1], 'details' => []];
        } elseif ($b->causesHorsCompetences()) {
            $s[] = ['niveau' => 'alerte', 'titre' => 'Le service n\'indique aucun manque de compétences : une formation n\'est peut-être pas la bonne réponse.', 'details' => []];
        }
        if ($b->gravite === 'risque') {
            $s[] = ['niveau' => 'info', 'titre' => 'Problème signalé comme un risque pour les assurés ou pour l\'Institut.', 'details' => []];
        }
        if ($b->solutions_essayees === ['aucune'] && $b->gravite === 'legere') {
            $s[] = ['niveau' => 'info', 'titre' => 'Gêne légère et aucune solution essayée : un accompagnement par un collègue ou une procédure écrite pourrait suffire.', 'details' => []];
        }

        // Formations semblables terminées ces dernières années
        $semblables = SessionFormation::with('action')->where('statut', StatutSession::Terminee->value)
            ->whereDate('date_fin', '>=', now()->subYears(self::ANNEES))->get()
            ->filter(fn ($x) => $x->action && Similarite::proches($b->intitule, $x->action->intitule));

        // Agents de la demande qui ont déjà suivi une formation proche
        $agents = $b->agents()->pluck('agents.id');
        if ($agents->isNotEmpty() && $semblables->isNotEmpty()) {
            $deja = Inscription::with(['agent', 'session.action'])->whereIn('agent_id', $agents)->whereIn('session_id', $semblables->pluck('id'))
                ->where('resultat', 'achevee')->get();
            if ($deja->isNotEmpty()) {
                $s[] = ['niveau' => 'alerte', 'titre' => $deja->pluck('agent_id')->unique()->count().' agent(s) de la demande ont déjà suivi une formation proche.',
                    'details' => $deja->map(fn ($i) => "{$i->agent->nom_complet} : « {$i->session->action->intitule} », terminée le {$i->session->date_fin->format('d/m/Y')}")->values()->all()];
            }
        }

        // Résultat de la mise en pratique des formations semblables
        foreach ($semblables->take(3) as $x) {
            $evals = EvaluationImpact::whereHas('inscription', fn ($q) => $q->where('session_id', $x->id))->whereNotNull('resultat_atteint')->get();
            if ($evals->isNotEmpty()) {
                $oui = $evals->where('resultat_atteint', 'oui')->count();
                $s[] = ['niveau' => $oui * 2 < $evals->count() ? 'alerte' : 'info', 'titre' => "Formation semblable « {$x->action->intitule} » ({$x->date_fin->format('m/Y')}) : "
                    ."{$oui} agent(s) sur {$evals->count()} appliquent ce qu'ils ont appris (en partie : {$evals->where('resultat_atteint', 'partiel')->count()}, non : {$evals->where('resultat_atteint', 'non')->count()}).", 'details' => []];
            }
        }

        // Demandes semblables d'autres services, en attente ou à venir : regrouper les agents
        $autres = BesoinFormation::with('service')->whereKeyNot($b->id)->where('service_id', '!=', $b->service_id)
            ->whereIn('statut', [StatutBesoin::Soumise->value, StatutBesoin::ACompleter->value, StatutBesoin::Acceptee->value, StatutBesoin::AuPlan->value])
            ->get()->filter(fn ($x) => Similarite::proches($b->intitule, $x->intitule));
        if ($autres->isNotEmpty()) {
            $s[] = ['niveau' => 'info', 'titre' => $autres->count().' autre(s) service(s) demandent une formation proche : regrouper les agents dans une même formation réduirait les coûts.',
                'details' => $autres->map(fn ($x) => "{$x->reference} — {$x->service?->nom} : « {$x->intitule} » ({$x->statutAffiche()[0]})")->values()->all()];
        }

        return $s;
    }
}
