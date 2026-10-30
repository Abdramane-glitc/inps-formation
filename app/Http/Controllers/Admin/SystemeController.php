<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\RegleMetierException;
use App\Http\Controllers\Controller;
use App\Models\EnvoiEmail;
use App\Services\Audit;
use App\Services\SauvegardeService;
use App\Support\ProtectionAudit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * « Sauvegardes » (version 1.6.0) : faire une sauvegarde, voir les sauvegardes et les télécharger,
 * voyant « Tout va bien / À vérifier » ; l'état technique détaillé est replié.
 * La restauration reste une opération en ligne de commande (docs/SAUVEGARDE_RESTAURATION.md).
 */
class SystemeController extends Controller
{
    public function index(SauvegardeService $s): View
    {
        $sauvegardes = $s->liste();
        $derniere = $sauvegardes[0]['date'] ?? null;
        $planificateur = cache('inps.planificateur');
        $production = app()->environment('production');

        // Points de contrôle en langage courant : [libellé, bon ?, explication si non]
        $points = [
            ['Sauvegarde de moins de 2 jours', $derniere && $derniere >= now()->subDays(2)->getTimestamp(),
                $derniere ? 'Dernière sauvegarde le '.date('d/m/Y à H:i', $derniere).'.' : 'Aucune sauvegarde : cliquez sur « Faire une sauvegarde ».'],
            ['Sauvegardes protégées par un mot de passe', (bool) config('inps.sauvegarde.mot_de_passe'),
                'Demandez à l\'informaticien de renseigner BACKUP_PASSWORD dans le fichier .env.'],
            ['Journal d\'audit protégé contre la modification', ProtectionAudit::estActive(),
                'Demandez à l\'informaticien d\'installer la protection (database/sql/protection_audit.sql).'],
            ['Rappels automatiques actifs', $planificateur && Carbon::parse($planificateur)->greaterThan(now()->subDays(2)),
                'La tâche planifiée « php artisan schedule:run » ne tourne pas : les rappels et sauvegardes automatiques ne sont pas faits.'],
        ];
        if ($production) {
            $points[] = ['Mode débogage désactivé', ! config('app.debug'), 'APP_DEBUG doit valoir false en production.'];
        }

        $technique = [
            'Environnement' => app()->environment(),
            'Mode débogage' => config('app.debug') ? 'activé' : 'désactivé',
            'HTTPS forcé' => config('inps.forcer_https') ? 'oui' : 'non',
            'Sessions chiffrées' => config('session.encrypt') ? 'oui' : 'non',
            'Cookies sécurisés' => config('session.secure') ? 'oui' : 'non',
            'Durée d\'inactivité de session' => config('session.lifetime').' min',
            'Transport e-mail' => config('mail.default').(in_array(config('mail.default'), ['log', 'array']) ? ' (aucun envoi réel)' : ''),
            'Dernier passage du planificateur' => $planificateur ?? 'jamais',
            'Dossier des sauvegardes' => $s->repertoire(),
            'Nombre de sauvegardes conservées' => config('inps.sauvegarde.retention'),
            'Version PHP' => PHP_VERSION,
            'Version Laravel' => app()->version(),
            'Version MySQL' => DB::selectOne('SELECT VERSION() v')->v ?? '?',
        ];

        return view('admin.systeme.index', ['points' => $points, 'toutVaBien' => collect($points)->every(fn ($p) => $p[1]),
            'technique' => $technique, 'sauvegardes' => $sauvegardes, 'emails' => EnvoiEmail::latest('id')->limit(20)->get()]);
    }

    public function sauvegarder(SauvegardeService $s): RedirectResponse
    {
        try {
            $chemin = $s->sauvegarder();
        } catch (RegleMetierException $e) {
            $m = $e->getMessage();
            // L'aide dépend de la cause réelle : programme introuvable, ou connexion à MySQL impossible
            $aide = match (true) {
                str_contains($m, '[introuvable]') => ' L\'outil mysqldump est introuvable : sur Laragon, indiquez son chemin dans .env (MYSQLDUMP_PATH=C:\\laragon\\bin\\mysql\\…\\bin\\mysqldump.exe), puis lancez php artisan optimize.',
                str_contains($m, 'mysqldump') && preg_match('/\b(2002|2003|2004|2005|1045)\b/', $m) === 1 => ' mysqldump est bien trouvé mais n\'a pas pu se connecter à MySQL : vérifiez que MySQL est démarré dans Laragon et que DB_HOST, DB_PORT, DB_USERNAME et DB_PASSWORD du fichier .env sont corrects. La commande « php artisan inps:sauvegarde » permet de faire le même essai depuis la console.',
                default => '',
            };

            $m = rtrim(str_replace(' [introuvable]', '', $m), ' .');

            return back()->with('erreur', 'La sauvegarde a échoué. '.$m.($aide ? '.'.$aide : ''));
        }
        cache()->forever('inps.derniere_sauvegarde', now()->toIso8601String());

        return back()->with('succes', 'Sauvegarde faite : '.basename($chemin).'. Pensez à en copier une régulièrement hors du serveur (clé USB, autre serveur).');
    }

    public function telecharger(string $fichier, SauvegardeService $s): BinaryFileResponse
    {
        // Seuls les fichiers de la liste peuvent être téléchargés (aucun chemin fourni par l'utilisateur n'est suivi)
        abort_unless(in_array($fichier, array_column($s->liste(), 'fichier'), true), 404);
        Audit::enregistrer('systeme.sauvegarde_telechargement', null, "Téléchargement de la sauvegarde $fichier");

        return response()->download($s->repertoire().'/'.$fichier, $fichier, ['Content-Type' => 'application/zip']);
    }
}
