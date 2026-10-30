<?php

namespace App\Services;

use App\Enums\StatutAction;
use App\Enums\StatutInscription;
use App\Enums\StatutSession;
use App\Exceptions\RegleMetierException;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\Domaine;
use App\Models\Import;
use App\Models\Inscription;
use App\Models\Nomenclature;
use App\Models\Organisme;
use App\Models\Service;
use App\Models\SessionFormation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Importation contrôlée (agents, historique des formations) en deux temps :
 *  1. analyser() : lecture, contrôle des colonnes, validation ligne à ligne, détection des doublons
 *     (dans le fichier et en base) et des références inconnues ; rien n'est écrit dans le référentiel ;
 *  2. confirmer() : écriture des seules lignes valides dans UNE transaction (tout ou rien).
 * Aucune donnée existante n'est modifiée ni supprimée. Le fichier téléversé n'est pas conservé.
 */
class ImportService
{
    public const COLONNES = [
        'agents' => [
            'obligatoires' => ['matricule', 'nom', 'prenom'],
            'facultatives' => ['direction_code', 'service_code', 'fonction', 'statut_professionnel', 'email_pro', 'telephone_pro'],
        ],
        'historique' => [
            'obligatoires' => ['matricule', 'intitule', 'type', 'date_debut', 'date_fin', 'resultat'],
            'facultatives' => ['domaine_code', 'organisme', 'lieu', 'duree_heures'],
        ],
        // Structure de l'INPS (1.6.1) : une ligne par service avec sa direction ; directions et services créés s'ils n'existent pas.
        // Depuis le 29/10/2026, une ligne sans service crée seulement la direction (liste des directions seule).
        'structure' => [
            'obligatoires' => ['direction_code', 'direction_nom'],
            'facultatives' => ['direction_sigle', 'service_code', 'service_nom', 'origine', 'remarque'],
        ],
    ];

    /**
     * Noms de colonnes des extractions du système du personnel de l'INPS, reconnus tels quels (29/10/2026) :
     * liste des directions (UA_CODE, DESIGNATION, SIGLE) et liste du personnel (UACODE_DIRECTION).
     * Les autres colonnes de ces fichiers (n° de compte, banque, n° INPS…) sont ignorées et ne sont jamais enregistrées.
     */
    public const ALIAS = [
        'ua_code' => 'direction_code',
        'uacode_direction' => 'direction_code',
        'designation' => 'direction_nom',
        'sigle' => 'direction_sigle',
    ];

    public const LIBELLES = ['agents' => 'Référentiel du personnel (agents)', 'historique' => 'Historique des formations suivies',
        'structure' => 'Structure de l\'INPS (directions et services)'];

    public const MAX_LIGNES = 5000;

    public function analyser(UploadedFile $fichier, string $type, User $user): Import
    {
        if (! isset(self::COLONNES[$type])) {
            throw new RegleMetierException('Type d\'import inconnu.');
        }
        $lignes = $this->lire($fichier);
        if (count($lignes) < 2) {
            throw new RegleMetierException('Le fichier ne contient aucune ligne de données.');
        }
        if (count($lignes) - 1 > self::MAX_LIGNES) {
            throw new RegleMetierException('Le fichier dépasse '.self::MAX_LIGNES.' lignes : découpez-le en plusieurs fichiers.');
        }
        $entetes = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->ascii()->replace([' ', '-'], '_')->toString(), $lignes[0]);
        foreach ($entetes as $i => $h) {
            // Alias : seulement si la colonne attendue n'est pas déjà présente sous son vrai nom
            if (isset(self::ALIAS[$h]) && ! in_array(self::ALIAS[$h], $entetes, true)) {
                $entetes[$i] = self::ALIAS[$h];
            }
        }
        $manquantes = array_diff(self::COLONNES[$type]['obligatoires'], $entetes);
        if ($manquantes) {
            throw new RegleMetierException('Colonnes obligatoires absentes : '.implode(', ', $manquantes).'. Utilisez le modèle fourni.');
        }
        $connues = array_merge(self::COLONNES[$type]['obligatoires'], self::COLONNES[$type]['facultatives']);
        $this->delaiLong();

        return DB::transaction(function () use ($fichier, $type, $user, $lignes, $entetes, $connues) {
            $import = Import::create(['type' => $type, 'nom_fichier' => mb_substr($fichier->getClientOriginalName(), 0, 255), 'statut' => 'analyse', 'user_id' => $user->id]);
            $vus = [];
            $stats = ['valide' => 0, 'erreur' => 0, 'doublon' => 0];
            foreach (array_slice($lignes, 1) as $k => $brute) {
                if (count(array_filter($brute, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue; // ligne vide
                }
                $d = [];
                foreach ($entetes as $i => $h) {
                    if (in_array($h, $connues, true)) {
                        $d[$h] = trim((string) ($brute[$i] ?? ''));
                    }
                }
                [$statut, $messages, $d] = match ($type) {
                    'agents' => $this->validerAgent($d, $vus),
                    'structure' => $this->validerStructure($d, $vus),
                    default => $this->validerHistorique($d, $vus),
                };
                $stats[$statut]++;
                $import->lignes()->create(['numero' => $k + 2, 'donnees' => $d, 'statut' => $statut, 'messages' => $messages]);
            }
            $import->update(['total_lignes' => array_sum($stats), 'lignes_valides' => $stats['valide'], 'lignes_erreur' => $stats['erreur'], 'lignes_doublon' => $stats['doublon']]);
            Audit::enregistrer('import.analyse', $import, "Analyse d'un import {$type}", $stats);

            return $import;
        });
    }

    public function confirmer(Import $import, User $user): Import
    {
        if ($import->statut !== 'analyse') {
            throw new RegleMetierException('Cet import a déjà été traité.');
        }
        if ($import->lignes_valides === 0) {
            throw new RegleMetierException('Aucune ligne valide à importer.');
        }
        $this->delaiLong();
        try {
            DB::transaction(function () use ($import, $user) {
                $n = 0;
                $lignes = $import->lignes()->where('statut', 'valide')->get();
                if ($import->type === 'agents' || $import->type === 'structure') {
                    foreach ($lignes as $l) {
                        $import->type === 'agents' ? $this->creerAgent($l->donnees) : $this->creerService($l->donnees);
                        $l->update(['statut' => 'importee']);
                        $n++;
                    }
                } else {
                    $n = $this->creerHistorique($lignes, $user);
                }
                $import->update(['statut' => 'termine', 'lignes_importees' => $n, 'termine_le' => now(), 'message' => "$n ligne(s) importée(s)."]);
                Audit::enregistrer('import.confirmation', $import, "Import {$import->type} confirmé", ['importees' => $n]);
            });
        } catch (Throwable $e) {
            // Transaction annulée : aucune ligne n'a été écrite. L'utilisateur corrige le fichier puis relance.
            report($e);
            $import->update(['statut' => 'echoue', 'message' => 'Échec — aucune donnée écrite (annulation complète). Détail : '.Str::limit($e->getMessage(), 300)]);
            Audit::enregistrer('import.echec', $import, "Échec de l'import {$import->type}");
        }

        return $import->fresh();
    }

    /** Un fichier de plusieurs milliers d'agents peut dépasser le délai PHP habituel (30 s) sur un poste lent. */
    private function delaiLong(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
    }

    /** @var array<string, Direction|null> directions déjà lues, par code (évite une requête par ligne) */
    private array $directions = [];

    private function direction(string $code): ?Direction
    {
        return array_key_exists($code, $this->directions) ? $this->directions[$code] : $this->directions[$code] = Direction::where('code', $code)->first();
    }

    /** @return list<list<string>> */
    private function lire(UploadedFile $f): array
    {
        $ext = strtolower($f->getClientOriginalExtension());
        if (in_array($ext, ['xlsx', 'xls', 'ods'], true)) {
            try {
                // Les formats de cellule sont lus pour reconnaître les vraies dates Excel (stockées comme des nombres).
                // Lecteurs limités aux classeurs : un fichier HTML ou XML renommé en .xlsx est refusé (sécurité 2.5.1).
                $lecteur = IOFactory::createReaderForFile($f->getRealPath(), [IOFactory::READER_XLSX, IOFactory::READER_XLS, IOFactory::READER_ODS]);
                $feuille = $lecteur->load($f->getRealPath())->getActiveSheet();
                $lignes = [];
                foreach ($feuille->getRowIterator() as $ligne) {
                    $cellules = $ligne->getCellIterator();
                    $cellules->setIterateOnlyExistingCells(false);
                    $valeurs = [];
                    foreach ($cellules as $cellule) {
                        $v = $cellule->getCalculatedValue();
                        $valeurs[] = is_numeric($v) && ExcelDate::isDateTime($cellule)
                            ? ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d')
                            : (string) $v;
                    }
                    $lignes[] = $valeurs;
                }

                return $lignes;
            } catch (Throwable) {
                throw new RegleMetierException('Fichier tableur illisible.');
            }
        }
        if ($ext !== 'csv' && $ext !== 'txt') {
            throw new RegleMetierException('Format non reconnu : utilisez CSV (séparateur « ; » ou « , ») ou Excel (.xlsx).');
        }
        $contenu = (string) file_get_contents($f->getRealPath());
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu); // BOM UTF-8
        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252'); // fichiers Excel « CSV (séparateur : point-virgule) »
        }
        $premiere = strtok($contenu, "\n");
        $sep = substr_count((string) $premiere, ';') >= substr_count((string) $premiere, ',') ? ';' : ',';
        $h = fopen('php://memory', 'r+');
        fwrite($h, $contenu);
        rewind($h);
        $res = [];
        while (($r = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            $res[] = $r;
        }
        fclose($h);

        return $res;
    }

    private function validerAgent(array $d, array &$vus): array
    {
        $m = [];
        $d['matricule'] = mb_strtoupper($d['matricule'] ?? '');
        if ($d['matricule'] === '' || ! preg_match('/^[A-Z0-9\-\/\.]{2,30}$/', $d['matricule'])) {
            $m[] = 'Matricule absent ou invalide (2 à 30 caractères : lettres, chiffres, - / .).';
        }
        foreach (['nom' => 100, 'prenom' => 150] as $c => $max) {
            if (($d[$c] ?? '') === '' || mb_strlen($d[$c]) > $max) {
                $m[] = "Champ « $c » obligatoire (max $max caractères).";
            }
        }
        if (! empty($d['email_pro']) && ! filter_var($d['email_pro'], FILTER_VALIDATE_EMAIL)) {
            $m[] = 'Adresse e-mail professionnelle invalide.';
        }
        $dir = null;
        if (! empty($d['direction_code']) && ! ($dir = $this->direction($d['direction_code']))) {
            $m[] = "Direction inconnue : « {$d['direction_code']} ».";
        }
        if (! empty($d['service_code'])) {
            $sv = Service::where('code', $d['service_code'])->first();
            if (! $sv) {
                $m[] = "Service inconnu : « {$d['service_code']} ».";
            } elseif ($dir && $sv->direction_id !== $dir->id) {
                $m[] = 'Le service n\'appartient pas à la direction indiquée.';
            }
        }
        if (! empty($d['statut_professionnel']) && ! Nomenclature::where('type', 'statut_professionnel')->where('code', $d['statut_professionnel'])->exists()) {
            $m[] = "Statut professionnel inconnu : « {$d['statut_professionnel']} ».";
        }
        if ($m) {
            return ['erreur', $m, $d];
        }
        if (isset($vus[$d['matricule']])) {
            return ['doublon', ["Matricule présent plusieurs fois dans le fichier (ligne {$vus[$d['matricule']]})."], $d];
        }
        $vus[$d['matricule']] = count($vus) + 2;
        if (Agent::where('matricule', $d['matricule'])->exists()) {
            return ['doublon', ['Matricule déjà présent dans le référentiel : ligne ignorée (aucune mise à jour automatique).'], $d];
        }

        return ['valide', [], $d];
    }

    private function validerHistorique(array $d, array &$vus): array
    {
        $m = [];
        $d['matricule'] = mb_strtoupper($d['matricule'] ?? '');
        $agent = Agent::where('matricule', $d['matricule'])->first();
        if (! $agent) {
            $m[] = "Agent inconnu (matricule « {$d['matricule']} ») : importez d'abord le référentiel du personnel.";
        }
        if (($d['intitule'] ?? '') === '' || mb_strlen($d['intitule']) > 255) {
            $m[] = 'Intitulé obligatoire (max 255 caractères).';
        }
        $d['type'] = mb_strtolower($d['type'] ?? '');
        if (! in_array($d['type'], ['interne', 'externe', 'mixte'], true)) {
            $m[] = 'Type attendu : interne, externe ou mixte.';
        }
        foreach (['date_debut', 'date_fin'] as $c) {
            $date = $this->date($d[$c] ?? '');
            $date ? $d[$c] = $date : $m[] = "Date « $c » invalide (format JJ/MM/AAAA ou AAAA-MM-JJ).";
        }
        if (empty(array_filter($m, fn ($x) => str_contains($x, 'Date'))) && $d['date_fin'] < $d['date_debut']) {
            $m[] = 'La date de fin précède la date de début.';
        }
        $d['resultat'] = Str::of($d['resultat'] ?? '')->lower()->ascii()->toString();
        if (! in_array($d['resultat'], ['achevee', 'non_achevee'], true)) {
            $m[] = 'Résultat attendu : achevee ou non_achevee.';
        }
        if (! empty($d['domaine_code']) && ! Domaine::where('code', $d['domaine_code'])->exists()) {
            $m[] = "Domaine inconnu : « {$d['domaine_code']} ».";
        }
        if (! empty($d['duree_heures']) && ! is_numeric(str_replace(',', '.', $d['duree_heures']))) {
            $m[] = 'Durée en heures non numérique.';
        }
        if ($m) {
            return ['erreur', $m, $d];
        }
        $cle = $d['matricule'].'|'.Str::lower($d['intitule']).'|'.$d['date_debut'];
        if (isset($vus[$cle])) {
            return ['doublon', ['Ligne en double dans le fichier.'], $d];
        }
        $vus[$cle] = true;
        $existe = Inscription::where('agent_id', $agent->id)->whereHas('session', fn ($q) => $q->whereDate('date_debut', $d['date_debut'])
            ->whereHas('action', fn ($a) => $a->where('intitule', $d['intitule'])))->exists();
        if ($existe) {
            return ['doublon', ['Cette formation figure déjà dans l\'historique de l\'agent.'], $d];
        }
        $msg = [];
        if (! empty($d['organisme']) && ! Organisme::where('raison_sociale', $d['organisme'])->exists()) {
            $msg[] = "Organisme « {$d['organisme']} » non référencé : il sera créé.";
        }

        return ['valide', $msg, $d];
    }

    private function date(string $v): ?string
    {
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $fmt) {
            try {
                $c = Carbon::createFromFormat('!'.$fmt, trim($v));
                if ($c && $c->format($fmt) === trim($v)) {
                    return $c->toDateString();
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    /**
     * Ligne « service » de la structure : codes et noms ; direction existante réutilisée telle quelle (jamais renommée).
     * Ligne sans service (ni code ni nom) : la direction seule.
     */
    private function validerStructure(array $d, array &$vus): array
    {
        $m = [];
        $d['direction_sigle'] = preg_replace('/\s+/u', ' ', trim($d['direction_sigle'] ?? ''));
        if (mb_strlen($d['direction_sigle']) > 30) {
            $m[] = 'Sigle de la direction trop long (30 caractères au plus).';
        }
        if (trim($d['service_code'] ?? '') === '' && trim($d['service_nom'] ?? '') === '') {
            return $this->validerDirectionSeule($d, $vus, $m);
        }
        foreach (['direction_code', 'service_code'] as $c) {
            $d[$c] = mb_strtoupper(trim($d[$c] ?? ''));
            if (! preg_match('/^[A-Z0-9][A-Z0-9\-_.]{0,29}$/', $d[$c])) {
                $m[] = "Code « $c » absent ou invalide (1 à 30 caractères : lettres, chiffres, - _ .).";
            }
        }
        foreach (['direction_nom', 'service_nom'] as $c) {
            $d[$c] = preg_replace('/\s+/u', ' ', trim($d[$c] ?? ''));
            if ($d[$c] === '' || mb_strlen($d[$c]) > 190) {
                $m[] = "Champ « $c » obligatoire (190 caractères au plus).";
            }
        }
        if ($m) {
            return ['erreur', $m, $d];
        }
        $vus['directions'] ??= [];
        if (isset($vus['directions'][$d['direction_code']]) && $vus['directions'][$d['direction_code']] !== $d['direction_nom']) {
            return ['erreur', ["Le code de direction « {$d['direction_code']} » porte deux noms différents dans le fichier."], $d];
        }
        $vus['directions'][$d['direction_code']] = $d['direction_nom'];
        if (isset($vus['services'][$d['service_code']])) {
            return ['doublon', ["Code de service présent plusieurs fois dans le fichier (ligne {$vus['services'][$d['service_code']]})."], $d];
        }
        $vus['services'][$d['service_code']] = count($vus['services'] ?? []) + 2;
        $existant = Service::with('direction')->where('code', $d['service_code'])->first();
        if ($existant) {
            return ['doublon', ["Service déjà présent (code {$d['service_code']} : « {$existant->nom} ») : ligne ignorée, rien n'est modifié."], $d];
        }
        $infos = [];
        $dir = Direction::where('code', $d['direction_code'])->first();
        if ($dir && $dir->nom !== $d['direction_nom']) {
            $infos[] = "La direction {$d['direction_code']} existe déjà sous le nom « {$dir->nom} » : ce nom est conservé.";
        }
        if (Service::where('nom', $d['service_nom'])->whereHas('direction', fn ($q) => $q->where('code', $d['direction_code']))->exists()) {
            return ['doublon', ['Un service de ce nom existe déjà dans cette direction (sous un autre code) : ligne ignorée.'], $d];
        }

        return ['valide', $infos, $d];
    }

    /** Ligne de la liste des directions seule : créée si son code n'existe pas ; une direction existante n'est jamais modifiée. */
    private function validerDirectionSeule(array $d, array &$vus, array $m): array
    {
        $d['direction_code'] = mb_strtoupper(trim($d['direction_code'] ?? ''));
        if (! preg_match('/^[A-Z0-9][A-Z0-9\-_.]{0,29}$/', $d['direction_code'])) {
            $m[] = 'Code de la direction absent ou invalide (1 à 30 caractères : lettres, chiffres, - _ .).';
        }
        $d['direction_nom'] = preg_replace('/\s+/u', ' ', trim($d['direction_nom'] ?? ''));
        if ($d['direction_nom'] === '' || mb_strlen($d['direction_nom']) > 190) {
            $m[] = 'Nom de la direction obligatoire (190 caractères au plus).';
        }
        if ($m) {
            return ['erreur', $m, $d];
        }
        $vus['directions_seules'] ??= [];
        if (isset($vus['directions_seules'][$d['direction_code']])) {
            return ['doublon', ["Code de direction présent plusieurs fois dans le fichier (ligne {$vus['directions_seules'][$d['direction_code']]})."], $d];
        }
        $vus['directions_seules'][$d['direction_code']] = count($vus['directions_seules']) + 2;
        if ($existante = Direction::where('code', $d['direction_code'])->first()) {
            return ['doublon', ["Direction déjà présente (code {$d['direction_code']} : « {$existante->nom} ») : ligne ignorée, rien n'est modifié."], $d];
        }

        return ['valide', [], $d];
    }

    private function creerService(array $d): ?Service
    {
        $dir = Direction::firstOrCreate(['code' => $d['direction_code']], ['nom' => $d['direction_nom'], 'sigle' => ($d['direction_sigle'] ?? '') ?: null, 'actif' => true]);
        if (($d['service_code'] ?? '') === '') {
            return null; // ligne de la liste des directions seule
        }

        return Service::create(['direction_id' => $dir->id, 'code' => $d['service_code'], 'nom' => $d['service_nom'], 'actif' => true]);
    }

    private function creerAgent(array $d): Agent
    {
        $dir = ! empty($d['direction_code']) ? $this->direction($d['direction_code'])?->id : null;
        $sv = ! empty($d['service_code']) ? Service::where('code', $d['service_code'])->first() : null;
        $a = new Agent([
            'matricule' => $d['matricule'], 'nom' => $d['nom'], 'prenom' => $d['prenom'],
            'direction_id' => $dir ?? $sv?->direction_id, 'service_id' => $sv?->id, 'fonction' => $d['fonction'] ?? null,
            'statut_professionnel_id' => ! empty($d['statut_professionnel']) ? Nomenclature::where('type', 'statut_professionnel')->where('code', $d['statut_professionnel'])->value('id') : null,
            'email_pro' => ($d['email_pro'] ?? '') ?: null, 'telephone_pro' => ($d['telephone_pro'] ?? '') ?: null, 'source' => 'import',
        ]);
        $a->save();

        return $a;
    }

    /** Regroupe les lignes en formations historiques (une action + une session terminée par formation). */
    private function creerHistorique($lignes, User $user): int
    {
        $groupes = $lignes->groupBy(fn ($l) => implode('|', [Str::lower($l->donnees['intitule']), $l->donnees['date_debut'], $l->donnees['date_fin'], $l->donnees['type'], $l->donnees['organisme'] ?? '']));
        $n = 0;
        foreach ($groupes as $groupe) {
            $d = $groupe->first()->donnees;
            $orgId = ! empty($d['organisme']) ? Organisme::firstOrCreate(['raison_sociale' => $d['organisme']])->id : null;
            $annee = (int) substr($d['date_debut'], 0, 4);
            $a = new ActionFormation(['intitule' => $d['intitule'], 'type' => $d['type'], 'organisme_id' => $orgId,
                'domaine_id' => ! empty($d['domaine_code']) ? Domaine::where('code', $d['domaine_code'])->value('id') : null,
                'description' => 'Formation reprise depuis l\'historique (import).']);
            $a->annee = $annee;
            $a->reference = GenerateurReference::suivante('actions_formation', 'reference', 'HIS', $annee);
            $a->statut = StatutAction::Realisee;
            $a->historique_importe = true;
            $a->cree_par = $user->id;
            $a->save();
            $s = new SessionFormation(['date_debut' => $d['date_debut'], 'date_fin' => $d['date_fin'], 'lieu' => ($d['lieu'] ?? '') ?: null,
                'duree_heures' => ! empty($d['duree_heures']) ? (float) str_replace(',', '.', $d['duree_heures']) : null, 'places' => max(1, $groupe->count())]);
            $s->action_id = $a->id;
            $s->code = $a->reference.'-S01';
            $s->statut = StatutSession::Terminee;
            $s->presences_validees_le = now(); // résultat repris tel quel du fichier source
            $s->presences_validees_par = $user->id;
            $s->save();
            app(ActionService::class)->genererSeances($s);
            foreach ($groupe as $l) {
                $i = new Inscription;
                $i->session_id = $s->id;
                $i->agent_id = Agent::where('matricule', $l->donnees['matricule'])->value('id');
                $i->statut = StatutInscription::Confirme;
                $i->resultat = $l->donnees['resultat'];
                $i->resultat_calcule_le = now();
                $i->inscrit_par = $user->id;
                $i->save();
                $i->evenements()->create(['evenement' => 'reprise_historique', 'details' => 'Import #'.$l->import_id, 'user_id' => $user->id]);
                $l->update(['statut' => 'importee']);
                $n++;
            }
        }

        return $n;
    }
}
