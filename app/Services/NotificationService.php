<?php

namespace App\Services;

use App\Models\EnvoiEmail;
use App\Models\Parametre;
use App\Models\User;
use App\Notifications\NotificationMetier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifications internes systématiques + e-mail optionnel (paramètre notifications.email_actif).
 * Un e-mail n'est noté « envoye » que si le transport n'a levé aucune erreur.
 */
class NotificationService
{
    public const CATEGORIES = [
        'besoin_soumis' => 'Nouvelle demande',
        'besoin_a_completer' => 'Précisions demandées',
        'besoin_decision' => 'Décision sur une demande',
        'session_a_venir' => 'Formation à venir',
        'convocation_a_preparer' => 'Convocations à préparer',
        'dossier_incomplet' => 'Dossier incomplet',
        'evaluation_a_realiser' => 'Évaluation à réaliser',
        'rapport_a_produire' => 'Rapport à produire',
        'cloture_annuelle' => 'Échéance de clôture annuelle',
        'convocation' => 'Convocation',
        // Catégories des versions antérieures à 1.3.0 (plan annuel), conservées pour l'affichage des anciennes notifications
        'validation_attente' => 'Validation en attente',
        'plan_decision' => 'Décision sur le plan',
    ];

    /**
     * @param  iterable<User>|User  $destinataires
     * @param  bool  $avecEmail  faux quand l'e-mail part autrement (convocation avec la pièce jointe)
     */
    public function notifier(iterable|User $destinataires, string $categorie, string $titre, string $message, ?string $url = null, bool $avecEmail = true): int
    {
        $liste = $destinataires instanceof User ? collect([$destinataires]) : collect($destinataires);
        $liste = $liste->filter(fn (User $u) => $u->actif)->unique('id');
        $n = new NotificationMetier($categorie, $titre, $message, $url);
        foreach ($liste as $u) {
            $u->notify($n);
            if ($avecEmail) {
                $this->envoyerEmail($u, $titre, $message, $url);
            }
        }

        return $liste->count();
    }

    /** Destinataires = utilisateurs actifs détenant la permission (optionnellement limités à un service). */
    public function utilisateursAvecPermission(string $permission, ?int $serviceId = null): Collection
    {
        // Droits effectifs (profil + personnalisation, 2.0.0)
        return User::query()->where('actif', true)
            ->when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
            ->get()->filter(fn (User $u) => $u->aPermission($permission))->values();
    }

    public function notifierPermission(string $permission, string $categorie, string $titre, string $message, ?string $url = null, ?int $serviceId = null): int
    {
        return $this->notifier($this->utilisateursAvecPermission($permission, $serviceId), $categorie, $titre, $message, $url);
    }

    /**
     * E-mail vers une adresse, avec une pièce jointe éventuelle [nom, contenu] (2.3.0 : convocation d'un agent).
     * Rien n'est envoyé si la messagerie n'est pas activée ; vrai seulement si le message est réellement parti.
     */
    public function envoyerEmailAdresse(string $adresse, string $titre, string $message, ?array $pieceJointe = null, ?int $userId = null): bool
    {
        if (! Parametre::valeur('notifications.email_actif', false) || ! filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $sujet = '['.Parametre::valeur('institution.sigle', 'INPS').'] '.$titre;
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            EnvoiEmail::create(['user_id' => $userId, 'sujet' => mb_substr($sujet, 0, 255), 'statut' => 'non_remis', 'erreur' => 'Transport « '.config('mail.default').' » : aucun serveur SMTP configuré.']);

            return false;
        }
        try {
            Mail::raw($message, function ($m) use ($adresse, $sujet, $pieceJointe) {
                $m->to($adresse)->subject($sujet);
                if ($pieceJointe) {
                    $m->attachData($pieceJointe[1], $pieceJointe[0], ['mime' => 'application/pdf']);
                }
            });
            EnvoiEmail::create(['user_id' => $userId, 'sujet' => mb_substr($sujet, 0, 255), 'statut' => 'envoye']);

            return true;
        } catch (Throwable $e) {
            EnvoiEmail::create(['user_id' => $userId, 'sujet' => mb_substr($sujet, 0, 255), 'statut' => 'echec', 'erreur' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }
    }

    /** Messagerie réellement utilisable : envoi activé dans les paramètres et serveur SMTP configuré. */
    public static function messagerieActive(): bool
    {
        return (bool) Parametre::valeur('notifications.email_actif', false) && ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    private function envoyerEmail(User $u, string $titre, string $message, ?string $url): void
    {
        if (! Parametre::valeur('notifications.email_actif', false) || ! $u->email) { // compte d'agent sans e-mail (2.3.0)
            return;
        }
        $sujet = '['.Parametre::valeur('institution.sigle', 'INPS').'] '.$titre;
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            // Transport de développement : le message n'est PAS remis à un destinataire.
            EnvoiEmail::create(['user_id' => $u->id, 'sujet' => mb_substr($sujet, 0, 255), 'statut' => 'non_remis', 'erreur' => 'Transport « '.config('mail.default').' » : aucun serveur SMTP configuré.']);

            return;
        }
        try {
            Mail::raw($message.($url ? "\n\n".$url : ''), function ($m) use ($u, $sujet) {
                $m->to($u->email)->subject($sujet);
            });
            EnvoiEmail::create(['user_id' => $u->id, 'sujet' => mb_substr($sujet, 0, 255), 'statut' => 'envoye']);
        } catch (Throwable $e) {
            // L'échec est tracé : aucun envoi n'est présenté comme réussi.
            Log::warning('Échec d\'envoi e-mail de notification', ['user_id' => $u->id, 'erreur' => $e->getMessage()]);
            EnvoiEmail::create(['user_id' => $u->id, 'sujet' => mb_substr($sujet, 0, 255), 'statut' => 'echec', 'erreur' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
