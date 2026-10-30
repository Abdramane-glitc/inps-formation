<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Décision de l'utilisateur (30/10/2026) : les paramètres ne sont plus marqués « À valider par l'INPS » ;
 * ce sont des réglages ordinaires, renseignés au besoin. Seuls le marqueur et les textes d'aide changent :
 * les valeurs déjà saisies ne sont pas touchées. Un texte d'aide n'est remplacé que s'il est encore celui d'origine.
 */
return new class extends Migration
{
    /** clé => [aide d'origine, nouvelle aide] */
    public const AIDES = [
        'institution.direction_rh' => ['Intitulé officiel à confirmer.', 'Affichée en bas des pages et sur les documents exportés.'],
        'institution.adresse' => ['À renseigner par l\'INPS.', 'Facultatif : imprimée en en-tête des documents exportés si elle est renseignée.'],
        'institution.telephone' => [null, 'Facultatif : imprimé en en-tête des documents exportés s\'il est renseigné.'],
        'institution.email' => [null, 'Facultatif.'],
        'institution.couleur_principale' => ['Bleu institutionnel INPS (#003B73) — à confirmer avec la charte graphique officielle.', 'Bleu institutionnel INPS : #003B73.'],
        'institution.logo' => ['Aucun logo n\'est fourni : déposer le logo officiel validé.', 'Logo officiel de l\'INPS (PNG ou JPG).'],
        'institution.signataire_attestation' => ['Exemple : « Le Directeur des Ressources humaines » — à confirmer.', 'Exemple : « Le Directeur des Ressources humaines ». Imprimé sur les convocations et les attestations.'],
        'conservation.dossiers_formation_ans' => ['À fixer par l\'INPS conformément aux textes applicables. Aucune purge automatique n\'est réalisée.', 'Facultatif, à titre indicatif : aucune suppression automatique n\'est faite.'],
        'conservation.documents_ans' => ['Idem.', 'Facultatif, à titre indicatif : aucune suppression automatique n\'est faite.'],
        'conservation.journal_audit_ans' => ['Idem.', 'Facultatif, à titre indicatif : aucune suppression automatique n\'est faite.'],
        'conservation.agents_inactifs_ans' => ['Idem.', 'Facultatif, à titre indicatif : aucune suppression automatique n\'est faite.'],
        'conservation.sauvegardes_jours' => ['La rétention technique est réglée par BACKUP_RETENTION.', 'Facultatif, à titre indicatif : la suppression des anciennes sauvegardes est réglée par BACKUP_RETENTION (14 jours par défaut).'],
    ];

    public function up(): void
    {
        DB::table('parametres')->update(['a_valider_inps' => false]);
        foreach (self::AIDES as $cle => [$ancienne, $nouvelle]) {
            DB::table('parametres')->where('cle', $cle)
                ->where(fn ($q) => $ancienne === null ? $q->whereNull('aide') : $q->where('aide', $ancienne))
                ->update(['aide' => $nouvelle]);
        }
        Cache::forget('parametres.tous');
    }

    public function down(): void
    {
        // Retour en arrière : seuls les textes d'aide d'origine sont remis (le marqueur n'est plus affiché).
        foreach (self::AIDES as $cle => [$ancienne, $nouvelle]) {
            DB::table('parametres')->where('cle', $cle)->where('aide', $nouvelle)->update(['aide' => $ancienne]);
        }
    }
};
