<?php

namespace Database\Seeders;

use App\Models\Nomenclature;
use App\Models\Parametre;
use App\Models\Permission;
use App\Models\Role;
use App\Support\CataloguePermissions;
use Illuminate\Database\Seeder;

/**
 * Référentiel initial — utilisable EN PRODUCTION (aucune donnée de personnel, aucun compte).
 * Idempotent : peut être relancé après une mise à jour pour ajouter les nouvelles permissions
 * sans écraser les attributions modifiées par l'administrateur (rôles existants conservés).
 */
class ReferentielInitialSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CataloguePermissions::PERMISSIONS as $code => [$module, $libelle, $sensible]) {
            Permission::updateOrCreate(['code' => $code], ['module' => $module, 'libelle' => $libelle, 'sensible' => $sensible]);
        }
        foreach (CataloguePermissions::ROLES as $code => [$nom, $desc, $perms]) {
            $role = Role::where('code', $code)->first();
            if (! $role) {
                $role = new Role(['code' => $code, 'nom' => $nom, 'description' => $desc]);
                $role->systeme = true;
                $role->save();
                $role->permissions()->sync(Permission::whereIn('code', $perms)->pluck('id'));
            }
        }

        // Nomenclatures génériques : libellés proposés, à valider/adapter par l'INPS.
        $nomenclatures = [
            'statut_professionnel' => ['titulaire' => 'Titulaire', 'contractuel' => 'Contractuel', 'stagiaire' => 'Stagiaire'],
            'categorie_document' => ['programme' => 'Programme pédagogique', 'convocation' => 'Convocation', 'contrat' => 'Contrat / convention', 'presence' => 'Liste de présence', 'facture' => 'Facture / justificatif', 'rapport' => 'Rapport', 'evaluation' => 'Évaluation', 'attestation' => 'Attestation', 'justificatif' => 'Pièce justificative', 'autre' => 'Autre'],
        ];
        foreach ($nomenclatures as $type => $valeurs) {
            $o = 1;
            foreach ($valeurs as $code => $libelle) {
                Nomenclature::firstOrCreate(['type' => $type, 'code' => $code], ['libelle' => $libelle, 'ordre' => $o++, 'actif' => true]);
            }
        }

        // [clé, valeur par défaut, type, groupe, libellé, aide, marqueur « à valider » (plus utilisé depuis le 30/10/2026)]
        $parametres = [
            ['institution.nom', 'Institut National de Prévoyance Sociale', 'string', 'institution', 'Nom de l\'institution', null, false],
            ['institution.sigle', 'INPS', 'string', 'institution', 'Sigle', null, false],
            ['institution.direction_rh', 'Direction des Ressources humaines', 'string', 'institution', 'Intitulé de la direction gestionnaire', 'Affichée en bas des pages et sur les documents exportés.', false],
            ['institution.adresse', null, 'text', 'institution', 'Adresse officielle', 'Facultatif : imprimée en en-tête des documents exportés si elle est renseignée.', false],
            ['institution.telephone', null, 'string', 'institution', 'Téléphone officiel', 'Facultatif : imprimé en en-tête des documents exportés s\'il est renseigné.', false],
            ['institution.email', null, 'string', 'institution', 'Adresse e-mail officielle', 'Facultatif.', false],
            ['institution.couleur_principale', '#003B73', 'string', 'institution', 'Couleur principale de l\'interface (code hexadécimal)', 'Bleu institutionnel INPS : #003B73.', false],
            ['institution.logo', null, 'file', 'institution', 'Logo officiel (PNG/JPG)', 'Logo officiel de l\'INPS (PNG ou JPG).', false],
            ['institution.photo_connexion', null, 'file', 'institution', 'Photographie du siège (page de connexion)', 'Photographie officielle du siège de l\'INPS, format paysage, 1600 px de large minimum (JPG/PNG/WebP). Ne pas utiliser l\'image d\'un autre bâtiment.', false],
            ['institution.signataire_attestation', null, 'string', 'institution', 'Qualité du signataire des attestations', 'Exemple : « Le Directeur des Ressources humaines ». Imprimé sur les convocations et les attestations.', false],

            ['notifications.email_actif', '0', 'bool', 'notifications', 'Envoyer aussi les notifications par e-mail', 'Nécessite un serveur SMTP configuré dans .env. Les notifications internes restent actives dans tous les cas.', false],
            ['notifications.delai_rappel_session_jours', '7', 'int', 'notifications', 'Rappel « formation à venir » (jours avant le début)', null, false],
            ['notifications.date_bilan_annuel', '01-15', 'string', 'notifications', 'Date du rappel « bilan annuel à produire » (MM-JJ)', null, false],

            ['exports.pied_de_page', null, 'string', 'exports', 'Mention de pied de page des documents exportés', 'Exemple : « Document interne — diffusion restreinte ».', false],
            ['documents.taille_max_ko', '10240', 'int', 'exports', 'Taille maximale d\'un document déposé (Ko)', null, false],

            ['conservation.dossiers_formation_ans', null, 'int', 'conservation', 'Durée de conservation des dossiers de formation (années)', 'Facultatif, à titre indicatif : aucune suppression automatique n\'est faite.', false],
            ['conservation.documents_ans', null, 'int', 'conservation', 'Durée de conservation des pièces jointes (années)', 'Facultatif, à titre indicatif : aucune suppression automatique n\'est faite.', false],
            ['conservation.journal_audit_ans', null, 'int', 'conservation', 'Durée de conservation du journal d\'audit (années)', 'Facultatif, à titre indicatif : aucune suppression automatique n\'est faite.', false],
            ['conservation.agents_inactifs_ans', null, 'int', 'conservation', 'Durée de conservation des fiches d\'agents désactivés (années)', 'Facultatif, à titre indicatif : aucune suppression automatique n\'est faite.', false],
            ['conservation.sauvegardes_jours', null, 'int', 'conservation', 'Durée de conservation des sauvegardes (jours)', 'Facultatif, à titre indicatif : la suppression des anciennes sauvegardes est réglée par BACKUP_RETENTION (14 jours par défaut).', false],
        ];
        foreach ($parametres as [$cle, $val, $type, $groupe, $libelle, $aide, $valider]) {
            Parametre::firstOrCreate(['cle' => $cle], ['valeur' => $val, 'type' => $type, 'groupe' => $groupe, 'libelle' => $libelle, 'aide' => $aide, 'a_valider_inps' => $valider]);
        }
        Parametre::viderCache();
    }
}
