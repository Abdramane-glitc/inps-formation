<?php

namespace App\Services;

use App\Enums\StatutSession;
use App\Exceptions\RegleMetierException;
use App\Models\AttestationIntervenant;
use App\Models\EvaluationAcquis;
use App\Models\EvaluationImpact;
use App\Models\Formateur;
use App\Models\Inscription;
use App\Models\SessionFormation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Après la formation (version 1.9.0) :
 *  - évaluation à chaud (2.2.0) : la RH indique pour chaque participant s'il a compris la formation (oui / en partie / non) ;
 *  - évaluation à 3–6 mois (2.2.0) : le responsable de structure indique pour chacun de ses agents formés s'il applique ce qu'il
 *    a appris (oui / en partie / non), à partir de la date « fin de la formation + délai choisi par la RH » ;
 *  - rapport de réalisation : trois zones de texte rédigées par la RH, le reste est calculé ;
 *  - attestation d'intervention de chaque formateur externe.
 */
class EvaluationService
{
    // ---- Évaluation à chaud (RH) : « le participant a-t-il compris ? » ---------------------------------------------

    /** Participants concernés : ceux qui ont suivi la formation une fois terminée, sinon les participants inscrits. */
    public function participantsChaud(SessionFormation $s)
    {
        $q = $s->statut === StatutSession::Terminee ? $s->inscriptions()->where('resultat', 'achevee') : $s->participantsActifs();

        return $q->with(['agent.service', 'evaluationAcquis'])->get()->sortBy(fn ($i) => $i->agent->nom.' '.$i->agent->prenom)->values();
    }

    /**
     * Enregistre les réponses : [inscription_id => [resultat (oui|partiel|non), note (sur 20, facultative), commentaire]].
     * Une ligne vide efface une réponse saisie par erreur.
     *
     * @return array{0:int,1:int} réponses enregistrées, réponses effacées
     */
    public function enregistrerChaud(SessionFormation $s, array $lignes, User $u): array
    {
        if ($s->statut !== StatutSession::Terminee) {
            throw new RegleMetierException('La vérification se fait une fois la formation terminée (« Formation terminée »).');
        }
        $permis = $this->participantsChaud($s)->keyBy('id');
        $n = 0;
        $eff = 0;
        DB::transaction(function () use ($lignes, $permis, $u, &$n, &$eff) {
            foreach ($lignes as $id => $l) {
                $i = $permis->get((int) $id);
                if (! $i) {
                    continue;
                }
                $resultat = $l['resultat'] ?? null;
                $note = isset($l['note']) && $l['note'] !== '' ? (float) str_replace(',', '.', (string) $l['note']) : null;
                $commentaire = trim((string) ($l['commentaire'] ?? '')) ?: null;
                if (! $resultat && $note === null && ! $commentaire) {
                    $eff += (int) (bool) $i->evaluationAcquis?->delete();

                    continue;
                }
                if (! $resultat) {
                    throw new RegleMetierException("{$i->agent->nom_complet} : indiquez s'il a compris (oui, en partie ou non).");
                }
                EvaluationAcquis::updateOrCreate(['inscription_id' => $i->id], ['methode' => 'verification', 'resultat' => $resultat, 'reussi' => $resultat === 'oui',
                    'score' => $note, 'score_max' => $note === null ? null : EvaluationAcquis::NOTE_MAX, 'appreciation' => $commentaire, 'evaluateur_id' => $u->id]);
                $n++;
            }
        });
        Audit::enregistrer('evaluation.chaud', $s->action, "Formation {$s->action->reference} : compréhension vérifiée pour {$n} participant(s)", ['effacees' => $eff]);

        return [$n, $eff];
    }

    /** @return array{faites:int, attendues:int, reponses:array<string,int>, note_moyenne:?float} */
    public function syntheseChaud(SessionFormation $s): array
    {
        $participants = $this->participantsChaud($s);
        $evals = $participants->pluck('evaluationAcquis')->filter();
        $notes = $evals->pluck('score')->filter(fn ($v) => $v !== null);

        return ['faites' => $evals->count(), 'attendues' => $participants->count(),
            'reponses' => collect(EvaluationAcquis::REPONSES)->map(fn ($l, $k) => $evals->where('resultat', $k)->count())->all(),
            'note_moyenne' => $notes->isEmpty() ? null : round($notes->avg(), 1)];
    }

    // ---- Évaluation à 3–6 mois (responsable de structure ; RH pour tous) : « applique-t-il ce qu'il a appris ? » ----------

    public function peutEvaluerFroid(User $u, Inscription $i): bool
    {
        if ($u->aPermission('evaluations.gerer')) {
            return true;
        }

        return $u->aPermission('evaluations.froid') && $u->service_id !== null && $i->agent?->service_id === $u->service_id;
    }

    /** Agents formés (formation terminée, « a suivi ») que l'utilisateur peut évaluer : son service, ou tous pour la RH. */
    public function inscriptionsFroid(User $u): Builder
    {
        return Inscription::query()->where('resultat', 'achevee')
            ->whereHas('session', fn ($q) => $q->where('statut', StatutSession::Terminee->value))
            ->when(! $u->aPermission('evaluations.gerer'), fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('service_id', $u->service_id ?? 0)))
            ->with(['agent.service', 'session.action.besoin', 'evaluationsImpact']);
    }

    /** Les agents de cette formation que l'utilisateur évalue. */
    public function agentsFroid(SessionFormation $s, User $u)
    {
        return $this->inscriptionsFroid($u)->where('session_id', $s->id)->get()->sortBy(fn ($i) => $i->agent->nom.' '.$i->agent->prenom)->values();
    }

    /**
     * Enregistre les réponses du responsable de structure : [inscription_id => [resultat, commentaire]].
     * Possible à partir de la date « fin de la formation + délai » ; une ligne vide est ignorée.
     */
    public function enregistrerFroid(SessionFormation $s, array $lignes, User $u): int
    {
        if ($s->statut !== StatutSession::Terminee) {
            throw new RegleMetierException('Seule une formation terminée peut être évaluée.');
        }
        if (today()->lt($s->dateEvaluationFroid())) {
            throw new RegleMetierException('L\'évaluation de la mise en pratique se fait à partir du '.$s->dateEvaluationFroid()->format('d/m/Y').'.');
        }
        $permis = $this->agentsFroid($s, $u)->keyBy('id');
        $n = 0;
        DB::transaction(function () use ($lignes, $permis, $u, &$n) {
            foreach ($lignes as $id => $l) {
                $i = $permis->get((int) $id);
                if (! $i || empty($l['resultat'])) {
                    continue; // agent d'un autre service, ou ligne non remplie
                }
                $e = $i->evaluationsImpact->first() ?? new EvaluationImpact(['inscription_id' => $i->id]);
                $e->fill(['date_evaluation' => today(), 'resultat_atteint' => $l['resultat'], 'commentaire' => trim((string) ($l['commentaire'] ?? '')) ?: null, 'evaluateur_id' => $u->id]);
                $e->save();
                $n++;
            }
        });
        Audit::enregistrer('evaluation.froid', $s->action, "Formation {$s->action->reference} : mise en pratique évaluée pour {$n} agent(s) ({$s->delaiFroid()} mois après)");

        return $n;
    }

    /** @return array{faites:int, attendues:int, reponses:array<string,int>} */
    public function syntheseFroid(SessionFormation $s): array
    {
        $suivis = $s->inscriptions()->where('resultat', 'achevee')->with('evaluationsImpact')->get();
        $evals = $suivis->map(fn ($i) => $i->evaluationsImpact->first())->filter(fn ($e) => $e && $e->resultat_atteint);

        return ['faites' => $evals->count(), 'attendues' => $suivis->count(),
            'reponses' => collect(EvaluationAcquis::REPONSES)->map(fn ($l, $k) => $evals->where('resultat_atteint', $k)->count())->all()];
    }

    public function changerDelaiFroid(SessionFormation $s, int $mois, User $u): void
    {
        if (! in_array($mois, SessionFormation::DELAIS_FROID, true)) {
            throw new RegleMetierException('Délai attendu : 3, 4, 5 ou 6 mois.');
        }
        $avant = $s->delaiFroid();
        $s->evaluation_froid_mois = $mois;
        $s->save();
        Audit::enregistrer('evaluation.delai', $s->action, "Évaluation de la mise en pratique de la formation {$s->action->reference} : {$mois} mois après (au lieu de {$avant})");
    }

    // ---- Rapport de réalisation ---------------------------------------------------------------------------------

    public function enregistrerRapport(SessionFormation $s, array $d, User $u): void
    {
        if ($s->statut !== StatutSession::Terminee) {
            throw new RegleMetierException('Le rapport de réalisation se rédige une fois la formation terminée.');
        }
        $s->forceFill(['rapport_deroulement' => $d['rapport_deroulement'] ?? null, 'rapport_difficultes' => $d['rapport_difficultes'] ?? null,
            'rapport_recommandations' => $d['rapport_recommandations'] ?? null, 'rapport_redige_le' => now(), 'rapport_redige_par' => $u->id])->save();
        Audit::enregistrer('rapport.realisation', $s->action, "Rapport de réalisation de la formation {$s->action->reference} enregistré");
    }

    // ---- Attestation d'intervention (formateur externe) ---------------------------------------------------------

    public function attesterIntervenant(SessionFormation $s, Formateur $f, User $u): AttestationIntervenant
    {
        if ($s->statut !== StatutSession::Terminee) {
            throw new RegleMetierException('L\'attestation d\'intervention s\'émet une fois la formation terminée.');
        }
        if (! $f->estExterne() || ! $s->formateurs()->whereKey($f->id)->exists()) {
            throw new RegleMetierException('Seul un formateur externe de cette formation peut recevoir une attestation d\'intervention.');
        }

        return DB::transaction(function () use ($s, $f, $u) {
            $a = AttestationIntervenant::where('session_id', $s->id)->where('formateur_id', $f->id)->lockForUpdate()->first();
            if ($a) {
                return $a; // déjà émise : la même attestation est redonnée
            }
            $a = new AttestationIntervenant;
            $a->reference = GenerateurReference::suivante('attestations_intervenants', 'reference', 'ATI', (int) $s->date_fin->format('Y'));
            $a->session_id = $s->id;
            $a->formateur_id = $f->id;
            $a->emise_le = now();
            $a->emise_par = $u->id;
            $a->save();
            Audit::enregistrer('attestation.intervenant', $a, "Attestation d'intervention {$a->reference} : {$f->nom_complet}, formation {$s->action->reference}");

            return $a;
        });
    }
}
