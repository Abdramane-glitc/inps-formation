<?php

namespace App\Console\Commands;

use App\Enums\StatutBesoin;
use App\Enums\StatutSession;
use App\Models\AnneeReference;
use App\Models\BesoinFormation;
use App\Models\Parametre;
use App\Models\RappelEnvoye;
use App\Models\SessionFormation;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Rappels automatiques d'échéances (exécution quotidienne par le planificateur).
 * Chaque rappel n'est émis qu'une fois (table rappels_envoyes).
 */
class Echeances extends Command
{
    protected $signature = 'inps:echeances';

    protected $description = 'Générer les notifications d\'échéances (sessions, convocations, évaluations, dossiers, clôtures)';

    private int $total = 0;

    public function handle(NotificationService $n): int
    {
        cache()->forever('inps.planificateur', now()->toIso8601String());
        $auj = now()->startOfDay();
        $delai = (int) Parametre::valeur('notifications.delai_rappel_session_jours', 7);

        foreach (SessionFormation::with('action')->whereIn('statut', [StatutSession::Planifiee->value, StatutSession::Confirmee->value])
            ->whereDate('date_debut', '>=', $auj)->whereDate('date_debut', '<=', $auj->copy()->addDays($delai))->get() as $s) {
            $url = route('formations.show', $s->action_id);
            if ($s->date_debut->lte($auj->copy()->addDays($delai))) {
                $this->une("session_avenir:{$s->id}:{$s->date_debut->toDateString()}", fn () => $n->notifierPermission('actions.gerer', 'session_a_venir', 'Formation à venir',
                    "La formation {$s->code} « {$s->action->intitule} » débute le {$s->date_debut->format('d/m/Y')}.", $url));
            }
        }

        foreach (SessionFormation::with('action')->whereNotIn('statut', [StatutSession::Terminee->value, StatutSession::Annulee->value])
            ->whereDate('date_fin', '<', $auj)->get() as $s) {
            $this->une("session_non_cloturee:{$s->id}", fn () => $n->notifierPermission('actions.gerer', 'dossier_incomplet', 'Dossier incomplet',
                "La formation {$s->code} s'est achevée le {$s->date_fin->format('d/m/Y')} : indiquez qui l'a suivie et marquez-la terminée.", route('formations.show', $s->action_id)));
        }

        // 1.9.0 : évaluation de la mise en pratique (3 à 6 mois après) — rappel au responsable de structure des agents concernés
        $plusAncienne = $auj->copy()->subMonthsNoOverflow(min(SessionFormation::DELAIS_FROID));
        foreach (SessionFormation::with('action')->where('statut', StatutSession::Terminee->value)->whereDate('date_fin', '<=', $plusAncienne)->get() as $s) {
            if ($s->dateEvaluationFroid()->gt($auj)) {
                continue;
            }
            $services = $s->inscriptions()->where('resultat', 'achevee')->whereDoesntHave('evaluationsImpact')
                ->join('agents', 'agents.id', '=', 'inscriptions.agent_id')->pluck('agents.service_id')->unique();
            foreach ($services as $serviceId) {
                $this->une("eval_froid:{$s->id}:".($serviceId ?? 0), function () use ($n, $s, $serviceId) {
                    $titre = 'Évaluation de la mise en pratique';
                    $message = "Les agents formés à « {$s->action->intitule} » ({$s->action->reference}) peuvent être évalués : appliquent-ils ce qu'ils ont appris ?";
                    $envoyes = $serviceId ? $n->notifierPermission('evaluations.froid', 'evaluation_a_realiser', $titre, $message, route('evaluations.index'), $serviceId) : 0;

                    return $envoyes ?: $n->notifierPermission('evaluations.gerer', 'evaluation_a_realiser', $titre, $message, route('evaluations.index'));
                });
            }
        }

        foreach (BesoinFormation::with('auteur')->where('statut', StatutBesoin::ACompleter->value)->where('updated_at', '<', now()->subDays(7))->get() as $b) {
            $this->une("besoin_relance:{$b->id}:".$b->updated_at->toDateString(), fn () => $n->notifier($b->auteur, 'besoin_a_completer', 'Demande toujours à compléter',
                "La demande {$b->reference} attend vos précisions depuis plus de 7 jours.", route('besoins.show', $b)));
        }

        foreach (AnneeReference::where('statut', 'ouverte')->get() as $a) {
            if ($a->date_limite_besoins && $a->date_limite_besoins->between($auj, $auj->copy()->addDays(15))) {
                $this->une("limite_besoins:{$a->annee}", fn () => $n->notifierPermission('besoins.creer', 'cloture_annuelle', 'Date limite de recensement',
                    "Les besoins de formation {$a->annee} doivent être soumis avant le {$a->date_limite_besoins->format('d/m/Y')}.", route('besoins.index')));
            }
            if ($a->date_cloture && $a->date_cloture->between($auj, $auj->copy()->addDays(30))) {
                $this->une("cloture:{$a->annee}", fn () => $n->notifierPermission('besoins.instruire', 'cloture_annuelle', 'Échéance de clôture annuelle',
                    "La clôture de l'exercice {$a->annee} est prévue le {$a->date_cloture->format('d/m/Y')}.", route('besoins.index')));
            }
        }

        $jourRapport = (string) Parametre::valeur('notifications.date_bilan_annuel', '01-15'); // MM-JJ
        if ($auj->format('m-d') === $jourRapport) {
            $annee = $auj->year - 1;
            $this->une("bilan:$annee", fn () => $n->notifierPermission('rapports.voir', 'rapport_a_produire', 'Bilan annuel à produire',
                "Le bilan des formations $annee peut être produit depuis le module Rapports.", route('rapports.index', ['annee' => $annee])));
        }

        $this->info("{$this->total} rappel(s) émis.");

        return self::SUCCESS;
    }

    private function une(string $cle, callable $envoi): void
    {
        try {
            RappelEnvoye::create(['cle' => mb_substr($cle, 0, 190)]);
        } catch (QueryException) {
            return; // déjà envoyé
        }
        $this->total += (int) $envoi();
    }
}
