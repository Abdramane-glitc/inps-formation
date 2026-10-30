<?php

namespace App\Support;

use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\BesoinFormation;
use App\Models\User;

/**
 * Présentation du journal d'audit en langage courant (version 1.6.0).
 * Le journal lui-même n'est pas modifié : seuls le regroupement, les libellés et les liens sont calculés à l'affichage.
 */
final class JournalLisible
{
    /** code => [libellé, préfixes d'action] */
    public const CATEGORIES = [
        'connexions' => ['Connexions et mots de passe', ['securite.']],
        'demandes' => ['Demandes', ['besoin.']],
        'formations' => ['Formations, participants et plan annuel', ['action.', 'session.', 'inscription.', 'plan.']],
        'attestations' => ['Attestations', ['attestation.']],
        'personnel' => ['Personnel et importations', ['agent.', 'import.', 'export.agents', 'export.historique_agent']],
        'comptes' => ['Comptes utilisateurs', ['utilisateur.']],
        'administration' => ['Administration (paramètres, sauvegardes, documents, organismes…)', ['parametres.', 'referentiel.', 'systeme.', 'audit.', 'migration.', 'document.', 'organisme.', 'formateur.', 'export.rapport', 'export.emargement', 'corbeille.']],
    ];

    private const LIBELLES = [
        'securite.connexion' => 'Connexion', 'securite.connexion_echec' => 'Échec de connexion', 'securite.deconnexion' => 'Déconnexion',
        'securite.changement_mot_de_passe' => 'Changement de mot de passe', 'securite.demande_reinitialisation' => 'Mot de passe oublié',
        'securite.reinitialisation_mot_de_passe' => 'Mot de passe réinitialisé', 'securite.session_compte_inactif' => 'Compte désactivé déconnecté',
        'besoin.creation' => 'Demande envoyée', 'besoin.modification' => 'Demande modifiée', 'besoin.acceptation' => 'Demande acceptée',
        'besoin.rejet' => 'Demande refusée', 'besoin.demande_complement' => 'Précisions demandées', 'besoin.soumission' => 'Demande envoyée',
        'besoin.organisation' => 'Formation programmée', 'besoin.suppression' => 'Demande supprimée', 'besoin.restauration' => 'Demande restaurée',
        'besoin.suppression_brouillon' => 'Demande supprimée',
        'action.creation' => 'Formation créée', 'action.modification' => 'Formation modifiée', 'action.suppression' => 'Formation supprimée',
        'action.restauration' => 'Formation restaurée', 'session.creation' => 'Dates fixées', 'session.modification' => 'Dates ou lieu modifiés',
        'session.annulation' => 'Formation annulée', 'session.terminee' => 'Formation terminée', 'session.correction_suivi' => 'Suivi corrigé',
        'inscription.ajout' => 'Participants ajoutés', 'inscription.retrait' => 'Participant retiré',
        'attestation.emission' => 'Attestation émise', 'attestation.annulation' => 'Attestation annulée', 'attestation.telechargement' => 'Attestation téléchargée',
        'attestation.telechargement_agent' => 'Attestation téléchargée par l\'agent',
        'agent.creation' => 'Agent ajouté', 'agent.modification' => 'Agent modifié', 'agent.suppression' => 'Agent supprimé',
        'import.analyse' => 'Fichier analysé', 'import.confirmation' => 'Import confirmé', 'import.abandon' => 'Import abandonné', 'import.echec' => 'Import en échec',
        'utilisateur.creation' => 'Compte créé', 'utilisateur.modification' => 'Compte modifié', 'utilisateur.droits' => 'Profil modifié',
        'utilisateur.activation' => 'Compte réactivé', 'utilisateur.desactivation' => 'Compte désactivé', 'utilisateur.suppression' => 'Compte supprimé',
        'utilisateur.reinitialisation_mot_de_passe' => 'Mot de passe temporaire', 'utilisateur.creation_admin_console' => 'Compte créé (console)',
        'parametres.modification' => 'Paramètres modifiés', 'systeme.sauvegarde' => 'Sauvegarde faite', 'systeme.sauvegarde_telechargement' => 'Sauvegarde téléchargée', 'audit.export' => 'Export du journal',
        'document.depot' => 'Document déposé', 'document.retrait' => 'Document retiré', 'document.telechargement_confidentiel' => 'Document confidentiel ouvert',
        'export.rapport' => 'Rapport exporté', 'export.plan' => 'Plan annuel exporté', 'plan.ajout' => 'Ajout au plan annuel', 'plan.modification' => 'Plan annuel modifié', 'plan.retrait' => 'Retrait du plan annuel', 'plan.programmation' => 'Formation du plan programmée', 'plan.validation' => 'Plan annuel validé', 'besoin.inscription_plan' => 'Demande inscrite au plan', 'besoin.retrait_plan' => 'Demande retirée du plan', 'export.emargement' => 'Liste des participants imprimée', 'export.agents' => 'Personnel exporté',
        'export.historique_agent' => 'Historique d\'un agent imprimé', 'corbeille.restauration' => 'Élément restauré',
    ];

    /** @return list<string> préfixes de la catégorie */
    public static function prefixes(string $categorie): array
    {
        return self::CATEGORIES[$categorie][1] ?? [];
    }

    public static function libelle(AuditLog $l): string
    {
        if (isset(self::LIBELLES[$l->action])) {
            return self::LIBELLES[$l->action];
        }
        if (str_starts_with($l->action, 'migration.')) {
            return 'Mise à jour de l\'application';
        }
        if (str_starts_with($l->action, 'referentiel.')) {
            return 'Référentiel modifié';
        }

        return ucfirst(str_replace(['.', '_'], [' — ', ' '], $l->action));
    }

    /** Lien vers l'élément concerné, si l'utilisateur peut y accéder ; sinon null. */
    public static function lien(AuditLog $l): ?string
    {
        if (! $l->objet_id) {
            return null;
        }
        $u = auth()->user();

        // Pas de lien vers un élément supprimé ou mis à la corbeille
        return match ($l->objet_type) {
            'besoin' => BesoinFormation::whereKey($l->objet_id)->exists() ? route('besoins.show', $l->objet_id) : null,
            'action' => ActionFormation::whereKey($l->objet_id)->exists() ? route('formations.show', $l->objet_id) : null,
            'session' => ActionFormation::whereHas('sessions', fn ($q) => $q->whereKey($l->objet_id))->exists() ? route('sessions.show', $l->objet_id) : null,
            'agent' => $u?->aPermission('agents.voir_tous') && Agent::whereKey($l->objet_id)->exists() ? route('agents.show', $l->objet_id) : null,
            'user' => $u?->aPermission('utilisateurs.gerer') && User::whereKey($l->objet_id)->exists() ? route('admin.utilisateurs.edit', $l->objet_id) : null,
            default => null,
        };
    }
}
