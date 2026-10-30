<?php

namespace Tests\Feature;

use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\AnneeReference;
use App\Models\Attestation;
use App\Models\BesoinFormation;
use App\Models\Formateur;
use App\Models\FormationDiplomante;
use App\Models\Inscription;
use App\Models\Organisme;
use App\Models\SessionFormation;
use App\Models\User;
use App\Support\CataloguePermissions;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Contrôle d'ensemble (1.7.3) : chaque page sans paramètre s'ouvre sans erreur serveur pour chacun des rôles,
 * et renvoie 200 (autorisé), 403 (refusé) ou une redirection — jamais 500.
 *
 * Complété en 2.5.1 : avec les données de démonstration, chaque page de l'application s'ouvre
 * sans erreur serveur (500) pour chaque profil : RH, responsable de structure et agent.
 * Une page interdite à un profil doit répondre 403 ou 404, jamais 500.
 */
class ParcoursPagesTest extends TestCase
{
    /** Pages qui envoient un fichier, demandent un fichier précis ou ne concernent que les visiteurs. */
    private const IGNOREES = ['administration/systeme/sauvegardes/{fichier}', 'documents/{document}/telecharger', 'reinitialisation/{token}',
        'identite/{type}', 'deconnexion'];

    public function test_aucune_page_ne_renvoie_d_erreur_serveur_quel_que_soit_le_role(): void
    {
        $pages = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && ! str_contains($r->uri(), '{') && in_array('auth', $r->gatherMiddleware(), true))
            ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
            ->reject(fn ($u) => in_array($u, ['/deconnexion'], true))->values();
        $this->assertGreaterThan(25, $pages->count());
        foreach (array_keys(CataloguePermissions::ROLES) as $role) {
            $u = $this->utilisateur($role);
            foreach ($pages as $p) {
                $statut = $this->actingAs($u)->get($p)->getStatusCode();
                $this->assertContains($statut, [200, 302, 403, 404], "$role $p → $statut");
            }
        }
    }

    public function test_toutes_les_pages_s_ouvrent_sans_erreur_pour_chaque_profil(): void
    {
        config(['inps.demo_mot_de_passe' => 'Demo-Formation-2026!']);
        AnneeReference::query()->delete(); // la démonstration crée ses propres années
        $this->seed(DemoSeeder::class);

        $rh = User::where('email', 'demo.responsable_drh@exemple.invalid')->firstOrFail();
        $responsable = User::where('email', 'demo.chef_service@exemple.invalid')->firstOrFail();
        $agent = $this->utilisateur('agent', null, ['name' => 'Agent démo']);
        $inscription = Inscription::firstOrFail();
        $agent->forceFill(['agent_id' => $inscription->agent_id])->save();

        $valeurs = [
            '{type}' => 'directions', '{utilisateur}' => $responsable->id, '{agent}' => Agent::value('id'),
            '{attestation}' => Attestation::value('id'), '{besoin}' => BesoinFormation::value('id'), '{formateur}' => Formateur::value('id'),
            '{diplomante}' => FormationDiplomante::value('id'), '{formation}' => ActionFormation::value('id'),
            '{import}' => 0, '{inscription}' => $inscription->id, '{organisme}' => Organisme::value('id'), '{annee}' => now()->year,
            '{format}' => 'pdf', '{code}' => 'synthese', '{session}' => SessionFormation::value('id'),
        ];
        $pages = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && ! in_array($r->uri(), self::IGNOREES, true)
                && ! str_starts_with($r->uri(), '_') && ! str_starts_with($r->uri(), 'storage') && ! in_array('guest', $r->gatherMiddleware(), true))
            ->map(fn ($r) => '/'.ltrim(strtr($r->uri(), $valeurs), '/'))
            ->reject(fn ($u) => str_contains($u, '{') || str_contains($u, '/0'))
            ->unique()->values();
        $this->assertGreaterThan(50, $pages->count());

        $erreurs = [];
        foreach (['RH' => $rh, 'Responsable de structure' => $responsable, 'Agent' => $agent] as $profil => $u) {
            foreach ($pages as $url) {
                $statut = $this->actingAs($u)->get($url)->getStatusCode();
                if ($statut >= 500) {
                    $erreurs[] = "{$profil} : {$url} → {$statut}";
                }
            }
        }
        $this->assertSame([], $erreurs);
    }
}
