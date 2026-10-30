<?php

namespace App\Http\Controllers;

use App\Support\Identite;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Logo et photographie institutionnels : publics par nature (affichés sur la page de connexion),
 * servis sans authentification. Aucun autre fichier n'est accessible par cette route.
 */
class IdentiteController extends Controller
{
    public function fichier(string $type): BinaryFileResponse
    {
        $chemin = Identite::chemin($type);
        abort_unless($chemin, 404);
        $mime = mime_content_type($chemin) ?: 'application/octet-stream';
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'], true), 404);

        return response()->file($chemin, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            // Un SVG ne doit jamais exécuter de script
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
