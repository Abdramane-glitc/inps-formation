<?php

namespace App\Services;

use App\Enums\StatutInscription as I;
use App\Enums\StatutSession;
use App\Exceptions\RegleMetierException;
use App\Models\Agent;
use App\Models\Inscription;
use App\Models\SessionFormation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Participants d'une formation (version 1.5.0, parcours simplifié) : ajout et retrait.
 * Contrôles conservés : agent inactif, doublon, chevauchement de dates avec une autre formation.
 * Il n'y a plus de capacité limitée, de liste d'attente, de convocation ni de remplacement :
 * le nombre de places suit le nombre de participants.
 */
class InscriptionService
{
    /**
     * @return array{inscrits: list<string>, refus: list<string>}
     */
    public function inscrire(SessionFormation $session, array $agentIds, User $user): array
    {
        $r = ['inscrits' => [], 'refus' => []];

        DB::transaction(function () use ($session, $agentIds, $user, &$r) {
            $s = SessionFormation::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->verifierFormationOuverte($s);

            foreach (Agent::whereIn('id', array_unique($agentIds))->orderBy('nom')->get() as $agent) {
                $nom = "{$agent->matricule} {$agent->nom_complet}";
                if (! $agent->actif) {
                    $r['refus'][] = "$nom : agent inactif.";

                    continue;
                }
                $existante = Inscription::where('session_id', $s->id)->where('agent_id', $agent->id)->first();
                if ($existante && $existante->occupePlace()) {
                    $r['refus'][] = "$nom : déjà inscrit.";

                    continue;
                }
                if ($conflit = $this->chevauchement($agent->id, $s)) {
                    $r['refus'][] = "$nom : déjà inscrit à la formation {$conflit} sur des dates qui se chevauchent.";

                    continue;
                }
                $i = $existante ?? new Inscription;
                $i->session_id = $s->id;
                $i->agent_id = $agent->id;
                $i->inscrit_par = $user->id;
                $i->statut = I::Inscrit;
                $i->rang_attente = null;
                $i->desiste_le = null;
                $i->motif_desistement = null;
                $i->save();
                $i->evenements()->create(['evenement' => $existante ? 'reinscription' : 'inscription', 'user_id' => $user->id]);
                $r['inscrits'][] = $nom;
            }
            $s->places = max($s->places, $s->placesOccupees());
            $s->save();
            Audit::enregistrer('inscription.ajout', $s, "Participants ajoutés à {$s->code}", ['inscrits' => count($r['inscrits']), 'refus' => count($r['refus'])]);
        });

        return $r;
    }

    /** Retire un participant tant que la formation n'est pas terminée (l'inscription reste dans l'historique). */
    public function retirer(Inscription $i, User $user, string $motif = 'Retiré de la formation'): Inscription
    {
        if (! $i->occupePlace()) {
            throw new RegleMetierException('Ce participant a déjà été retiré.');
        }
        $this->verifierFormationOuverte($i->session);

        return DB::transaction(function () use ($i, $user, $motif) {
            $i->statut = I::Desiste;
            $i->desiste_le = now();
            $i->motif_desistement = mb_substr($motif, 0, 500);
            $i->save();
            $i->evenements()->create(['evenement' => 'retrait', 'details' => mb_substr($motif, 0, 500), 'user_id' => $user->id]);
            Audit::enregistrer('inscription.retrait', $i, "Participant retiré de {$i->session->code}");

            return $i;
        });
    }

    private function verifierFormationOuverte(SessionFormation $s): void
    {
        if (in_array($s->statut, [StatutSession::Annulee, StatutSession::Terminee], true)) {
            throw new RegleMetierException("La formation est « {$s->statut->libelle()} » : la liste des participants ne peut plus changer.");
        }
    }

    /** Code de la formation en conflit (inscription active sur des dates qui se chevauchent), sinon null. */
    public function chevauchement(int $agentId, SessionFormation $s): ?string
    {
        return Inscription::where('agent_id', $agentId)->where('session_id', '!=', $s->id)
            ->whereIn('statut', SessionFormation::STATUTS_OCCUPANT_PLACE)
            ->whereHas('session', fn ($q) => $q->whereNotIn('statut', [StatutSession::Annulee->value])
                ->whereDate('date_debut', '<=', $s->date_fin)->whereDate('date_fin', '>=', $s->date_debut))
            ->with('session:id,code')->first()?->session?->code;
    }
}
