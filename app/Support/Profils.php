<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * Deux profils fixes (version 1.6.0, décision de l'INPS du 22/10/2026).
 * Un profil = un ensemble de rôles techniques attribués ensemble. Les autres rôles du catalogue
 * (administrateur fonctionnel, autorité de validation, auditeur, agent) restent en base, sans être proposés.
 */
final class Profils
{
    /** code => [libellé, rôles techniques, description, service obligatoire] */
    public const PROFILS = [
        'rh' => ['RH', ['responsable_drh', 'admin_technique'],
            'Gère tout : demandes, formations, participants, attestations, personnel, rapports, paramètres, comptes et sauvegardes.', false],
        'chef_service' => ['Responsable de structure', ['chef_service'],
            'Fait les demandes de formation de son service et suit les formations de ses agents.', true],
    ];

    /**
     * Profil « Agent » (2.3.0) : consultation de ses propres formations, convocations et attestations.
     * Les comptes d'agents se créent depuis Personnel › Accès des agents (identifiant : le matricule), pas depuis Utilisateurs.
     */
    public const AGENT = ['Agent', ['agent'], 'Consulte ses formations et ses convocations, et voit si son attestation est délivrée (remise par la RH).'];

    public static function libelle(string $code): string
    {
        return $code === 'agent' ? self::AGENT[0] : (self::PROFILS[$code][0] ?? $code);
    }

    /** Compte d'agent : uniquement le rôle « agent ». */
    public static function estAgent(User $u): bool
    {
        return $u->roles->pluck('code')->all() === ['agent'];
    }

    /** @return list<int> identifiants des rôles du profil */
    public static function roleIds(string $code): array
    {
        return Role::whereIn('code', self::PROFILS[$code][1] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Profil correspondant exactement aux rôles du compte, sinon null (combinaison antérieure à la version 1.6.0). */
    public static function de(User $u): ?string
    {
        $codes = $u->roles->pluck('code')->sort()->values()->all();
        foreach (self::PROFILS as $code => [, $roles]) {
            $attendus = collect($roles)->sort()->values()->all();
            if ($codes === $attendus) {
                return $code;
            }
        }

        return null;
    }

    /** Libellé affiché pour un compte : profil, ou liste de ses rôles s'il n'en a pas. */
    public static function affichage(User $u): string
    {
        $p = self::de($u) ?? (self::estAgent($u) ? 'agent' : null);

        $libelle = $p ? self::libelle($p) : ($u->roles->isEmpty() ? 'Aucun profil' : 'Autre : '.$u->roles->pluck('nom')->implode(', '));

        return $u->droitsPersonnalises() ? $libelle.' (droits personnalisés)' : $libelle;
    }

    /**
     * Droits proposés à la personnalisation (2.0.0), groupés par module : tout le catalogue,
     * sauf l'espace personnel des agents (les agents n'ont pas de compte).
     *
     * @return array<string, array<string, string>> module => [code => libellé]
     */
    public static function droitsProposes(): array
    {
        $groupes = [];
        foreach (CataloguePermissions::PERMISSIONS as $code => [$module, $libelle]) {
            if ($code !== 'espace.personnel') {
                $groupes[$module][$code] = $libelle;
            }
        }

        return $groupes;
    }

    /** Codes des droits inclus dans un profil. @return list<string> */
    public static function droitsDuProfil(string $code): array
    {
        return collect(self::PROFILS[$code][1] ?? [])->flatMap(fn ($r) => CataloguePermissions::ROLES[$r][2] ?? [])->unique()->values()->all();
    }
}
