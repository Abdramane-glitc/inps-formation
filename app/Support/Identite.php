<?php

namespace App\Support;

use App\Models\Parametre;
use Illuminate\Support\Facades\Storage;

/**
 * Ressources institutionnelles (logo officiel, photographie du siège).
 *
 * Aucune ressource officielle n'est fournie avec le logiciel : l'INPS les dépose soit
 *  - depuis Administration > Paramètres (prioritaire, stockage privé),
 *  - soit en copiant les fichiers dans public/images/institution/ :
 *      logo.svg | logo.png              → logo officiel
 *      siege.jpg | siege.webp | siege.png → photographie du siège (page de connexion)
 */
final class Identite
{
    public const TYPES = [
        'logo' => ['parametre' => 'institution.logo', 'fichiers' => ['logo.svg', 'logo.png', 'logo.jpg']],
        'photo' => ['parametre' => 'institution.photo_connexion', 'fichiers' => ['siege.jpg', 'siege.jpeg', 'siege.webp', 'siege.png']],
    ];

    /** Dossier des fichiers déposés sur le serveur (modifiable dans les tests). */
    public static ?string $dossierPublic = null;

    public static function dossierPublic(): string
    {
        return self::$dossierPublic ?? public_path('images/institution');
    }

    /** Chemin absolu du fichier, ou null s'il n'a pas été fourni. */
    public static function chemin(string $type): ?string
    {
        $def = self::TYPES[$type] ?? null;
        if (! $def) {
            return null;
        }
        $nom = Parametre::valeur($def['parametre']);
        if ($nom && Storage::disk('prive')->exists('identite/'.basename($nom))) {
            return Storage::disk('prive')->path('identite/'.basename($nom));
        }
        foreach ($def['fichiers'] as $f) {
            if (is_file(self::dossierPublic().'/'.$f)) {
                return self::dossierPublic().'/'.$f;
            }
        }

        return null;
    }

    public static function existe(string $type): bool
    {
        return self::chemin($type) !== null;
    }

    /** URL versionnée (le cache du navigateur est invalidé quand le fichier change). */
    public static function url(string $type): ?string
    {
        $c = self::chemin($type);

        return $c ? route('identite.fichier', ['type' => $type, 'v' => substr(md5((string) filemtime($c).$c), 0, 8)]) : null;
    }

    public static function dataUri(string $type): ?string
    {
        $c = self::chemin($type);

        return $c ? 'data:'.(mime_content_type($c) ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($c)) : null;
    }
}
