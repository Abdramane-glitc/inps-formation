<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stockage sécurisé des pièces jointes :
 *  - disque « prive » hors racine web (storage/app/private) ;
 *  - type vérifié par contenu (finfo/MIME réel) ET extension, taille plafonnée ;
 *  - nom de stockage aléatoire non prévisible (UUID + année/mois) ;
 *  - empreinte SHA-256 conservée pour contrôle d'intégrité.
 */
class DocumentService
{
    public const TYPES_AUTORISES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
    ];

    public static function tailleMaxKo(): int
    {
        return (int) Parametre::valeur('documents.taille_max_ko', 10240);
    }

    public function deposer(Model $objet, UploadedFile $fichier, array $meta, User $user): Document
    {
        $ext = strtolower($fichier->getClientOriginalExtension());
        $mimeReel = (new \finfo(FILEINFO_MIME_TYPE))->file($fichier->getRealPath()) ?: 'application/octet-stream';
        if (! isset(self::TYPES_AUTORISES[$ext]) || ! in_array($mimeReel, self::TYPES_AUTORISES[$ext], true)) {
            throw new RegleMetierException("Type de fichier refusé (extension « {$ext} », contenu détecté « {$mimeReel} »).");
        }
        if ($fichier->getSize() > self::tailleMaxKo() * 1024) {
            throw new RegleMetierException('Fichier trop volumineux (maximum '.self::tailleMaxKo().' Ko).');
        }
        $chemin = 'documents/'.now()->format('Y/m').'/'.Str::uuid()->toString().'.'.$ext;
        Storage::disk('prive')->putFileAs(dirname($chemin), $fichier, basename($chemin));

        try {
            return DB::transaction(function () use ($objet, $fichier, $meta, $user, $chemin, $mimeReel) {
                $d = new Document([
                    'titre' => $meta['titre'] ?: $fichier->getClientOriginalName(),
                    'categorie_id' => $meta['categorie_id'] ?? null,
                    'confidentiel' => (bool) ($meta['confidentiel'] ?? false),
                ]);
                $d->documentable()->associate($objet);
                $d->nom_original = mb_substr(basename($fichier->getClientOriginalName()), 0, 255);
                $d->chemin = $chemin;
                $d->mime = $mimeReel;
                $d->taille = $fichier->getSize();
                $d->sha256 = hash_file('sha256', Storage::disk('prive')->path($chemin));
                $d->depose_par = $user->id;
                $d->save();
                Audit::enregistrer('document.depot', $d, "Dépôt du document « {$d->titre} »", ['rattachement' => $objet->getMorphClass().'#'.$objet->getKey(), 'confidentiel' => $d->confidentiel]);

                return $d;
            });
        } catch (\Throwable $e) {
            Storage::disk('prive')->delete($chemin); // pas de fichier orphelin

            throw $e;
        }
    }

    public function retirer(Document $d): void
    {
        // Retrait logique : le fichier est conservé jusqu'à application de la politique de conservation validée.
        $d->delete();
        Audit::enregistrer('document.retrait', $d, "Retrait du document « {$d->titre} »");
    }
}
