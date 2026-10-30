<?php

namespace App\Services;

use App\Enums\StatutInscription as I;
use App\Enums\StatutSession;
use App\Exceptions\RegleMetierException;
use App\Models\Inscription;
use App\Models\SessionFormation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Convocation des participants (2.3.0). Un seul bouton fait tout :
 *  - chaque participant est marqué « convoqué » (date enregistrée) et sa convocation est disponible en PDF ;
 *  - le responsable de structure de chaque service concerné reçoit la liste de ses agents convoqués ;
 *  - l'agent qui a un accès reçoit une notification et retrouve sa convocation dans « Mon espace » ;
 *  - chaque agent qui a une adresse (celle de son compte, sinon celle de sa fiche) reçoit sa convocation en PDF par
 *    e-mail, qu'il ait un compte ou non (28/10/2026), si la messagerie de l'INPS est configurée.
 * Depuis le 28/10/2026, la convocation part aussi automatiquement à l'acceptation d'une demande (case « Convoquer
 * aussitôt les agents ») et pour un participant ajouté après la convocation.
 * Si les dates, horaires ou lieu changent après la convocation, une convocation rectificative est proposée.
 */
class ConvocationService
{
    public function __construct(private NotificationService $notifications, private ExportService $export) {}

    /** Détails repris dans la convocation : s'ils changent, il faut reconvoquer. */
    public function details(SessionFormation $s): array
    {
        return ['date_debut' => $s->date_debut?->toDateString(), 'date_fin' => $s->date_fin?->toDateString(),
            'heure_debut' => $s->heure_debut ? substr((string) $s->heure_debut, 0, 5) : null, 'heure_fin' => $s->heure_fin ? substr((string) $s->heure_fin, 0, 5) : null,
            'lieu' => $s->lieu ?: null];
    }

    /** La base peut réordonner les clés JSON : on compare des tableaux triés. */
    private function trie(array $d): array
    {
        ksort($d);

        return $d;
    }

    /** @return array{convoquee:bool, le:?Carbon, a_reconvoquer:bool, non_convoques:int, participants:int} */
    public function etat(SessionFormation $s): array
    {
        $participants = $s->participantsActifs()->get(['id', 'convoque_le']);

        return ['convoquee' => $s->convoquee_le !== null, 'le' => $s->convoquee_le,
            'a_reconvoquer' => $s->convoquee_le !== null && $this->trie((array) $s->convocation_details) !== $this->trie($this->details($s)),
            'non_convoques' => $participants->whereNull('convoque_le')->count(), 'participants' => $participants->count()];
    }

    /**
     * Convoque les participants : tous (première convocation ou rectificative), ou seulement ceux qui ne l'ont pas encore été.
     *
     * @return array{convoques:int, rectificative:bool, chefs:int, agents:int, emails:int, sans_adresse:int, non_envoyes:int, messagerie:bool}
     */
    public function convoquer(SessionFormation $s, User $u, bool $seulementNouveaux = false, ?int $sansPrevenir = null): array
    {
        if (in_array($s->statut, [StatutSession::Terminee, StatutSession::Annulee], true)) {
            throw new RegleMetierException('La formation est '.mb_strtolower($s->statut->libelle()).' : il n\'y a plus de convocation à envoyer.');
        }
        $etat = $this->etat($s);
        $rectificative = $etat['a_reconvoquer'];
        $q = $s->participantsActifs()->with(['agent.service', 'agent.user']);
        if ($seulementNouveaux && ! $rectificative) {
            $q->whereNull('convoque_le');
        }
        $liste = $q->get();
        if ($liste->isEmpty()) {
            throw new RegleMetierException($etat['participants'] ? 'Tous les participants sont déjà convoqués.' : 'Ajoutez d\'abord les participants.');
        }

        DB::transaction(function () use ($s, $u, $liste, $rectificative) {
            foreach ($liste as $i) {
                $i->convoque_le = now();
                if ($i->statut === I::Inscrit) {
                    $i->statut = I::Convoque;
                }
                $i->save();
                $i->evenements()->create(['evenement' => $rectificative ? 'convocation_rectificative' : 'convocation', 'user_id' => $u->id]);
            }
            $s->forceFill(['convoquee_le' => now(), 'convocation_details' => $this->details($s)])->save();
        });

        $s->loadMissing('action');
        $objet = ($rectificative ? 'Convocation rectificative' : 'Convocation').' : '.$s->action->intitule;
        $quand = 'du '.$s->date_debut->format('d/m/Y').' au '.$s->date_fin->format('d/m/Y').($s->lieu ? ', '.$s->lieu : '');

        // Responsables de structure : la liste de leurs agents convoqués, service par service
        $chefs = 0;
        foreach ($liste->groupBy(fn ($i) => $i->agent->service_id) as $serviceId => $groupe) {
            if ($serviceId) {
                $noms = $groupe->map(fn ($i) => $i->agent->nom_complet)->implode(', ');
                // $sansPrevenir : auteur de la demande, déjà informé de l'acceptation (pas de seconde notification)
                $destinataires = $this->notifications->utilisateursAvecPermission('agents.voir_service', (int) $serviceId)->reject(fn ($c) => $c->id === $sansPrevenir);
                $chefs += $this->notifications->notifier($destinataires, 'convocation', $objet,
                    "Agents de votre service convoqués à la formation « {$s->action->intitule} » ($quand) : $noms. Merci de les libérer.", route('formations.show', $s->action_id));
            }
        }
        // Agents : notification dans « Mon espace » s'ils ont un accès ; e-mail avec la convocation jointe pour tous ceux qui ont une adresse
        $agents = 0;
        $emails = 0;
        $sansAdresse = 0;
        $nonEnvoyes = 0;
        foreach ($liste as $i) {
            $compte = $i->agent->user?->actif ? $i->agent->user : null;
            $message = "Vous êtes convoqué(e) à la formation « {$s->action->intitule} » $quand."
                .($compte ? ' Votre convocation est jointe ; vous la retrouvez aussi dans « Mon espace ».' : ' Votre convocation est jointe.');
            if ($compte) {
                // Notification sans l'e-mail générique : l'e-mail part ci-dessous, avec la convocation en pièce jointe
                $agents += $this->notifications->notifier($compte, 'convocation', $objet, $message, route('espace.index'), avecEmail: false);
            }
            $adresse = $compte?->email ?: $i->agent->email_pro;
            if (! $adresse || ! filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
                $sansAdresse++;

                continue;
            }
            $this->notifications->envoyerEmailAdresse($adresse, $objet, $message, ['convocation-'.$i->agent->matricule.'.pdf', $this->pdf($s, collect([$i]), $rectificative)], $compte?->id)
                ? $emails++ : $nonEnvoyes++;
        }
        Audit::enregistrer('formation.convocation', $s->action, ($rectificative ? 'Convocation rectificative' : 'Convocation')." de {$liste->count()} participant(s) à la formation {$s->action->reference}",
            ['chefs_notifies' => $chefs, 'agents_notifies' => $agents, 'emails' => $emails, 'sans_adresse' => $sansAdresse, 'emails_non_partis' => $nonEnvoyes]);

        return ['convoques' => $liste->count(), 'rectificative' => $rectificative, 'chefs' => $chefs, 'agents' => $agents, 'emails' => $emails,
            'sans_adresse' => $sansAdresse, 'non_envoyes' => $nonEnvoyes, 'messagerie' => NotificationService::messagerieActive()];
    }

    /** Compte rendu lisible d'une convocation, pour le message affiché à la RH. */
    public function resume(array $res): string
    {
        $detail = collect([
            $res['emails'] ? "{$res['emails']} e-mail(s) envoyé(s) avec la convocation" : null,
            $res['agents'] ? "{$res['agents']} agent(s) prévenu(s) dans « Mon espace »" : null,
            $res['chefs'] ? "{$res['chefs']} responsable(s) de structure prévenu(s)" : null,
        ])->filter()->implode(', ');
        $texte = ($res['rectificative'] ? 'Convocation rectificative envoyée' : 'Participants convoqués')." : {$res['convoques']} participant(s)".($detail ? " ($detail)" : '').'.';
        if (($res['non_envoyes'] ?? 0) > 0) {
            $texte .= ($res['messagerie'] ?? false)
                ? " {$res['non_envoyes']} e-mail(s) n'ont pas pu partir (détail : Administration › Sauvegardes, suivi des e-mails)."
                : " Aucun e-mail n'est parti : la messagerie n'est pas configurée ({$res['non_envoyes']} convocation(s) à imprimer).";
        }
        if (($res['sans_adresse'] ?? 0) > 0) {
            $texte .= " {$res['sans_adresse']} participant(s) sans adresse e-mail : imprimez leur convocation.";
        }

        return $texte.' Toutes les convocations : « Convocations (PDF) ».';
    }

    /**
     * Convocation automatique (28/10/2026) : après l'acceptation d'une demande, la programmation d'une ligne du plan
     * ou l'ajout de participants à une formation déjà convoquée. Renvoie le compte rendu, ou null s'il n'y a rien à envoyer.
     */
    public function convoquerAussitot(SessionFormation $s, User $u, bool $seulementNouveaux = false, ?int $sansPrevenir = null): ?string
    {
        try {
            return $this->resume($this->convoquer($s, $u, $seulementNouveaux, $sansPrevenir));
        } catch (RegleMetierException) {
            return null; // aucun participant, ou formation terminée : rien à envoyer
        }
    }

    /** PDF des convocations (une page par participant). */
    public function pdf(SessionFormation $s, Collection $inscriptions, bool $rectificative = false): string
    {
        $s->loadMissing(['action', 'formateurs']);

        return $this->export->pdfBinaire('pdf.convocation', ['s' => $s, 'inscriptions' => $inscriptions->each(fn (Inscription $i) => $i->loadMissing('agent.service')), 'rectificative' => $rectificative]);
    }
}
