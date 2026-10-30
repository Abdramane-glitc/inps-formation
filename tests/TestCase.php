<?php

namespace Tests;

use App\Models\AnneeReference;
use App\Models\Direction;
use App\Models\Role;
use App\Models\Service;
use App\Models\SessionFormation;
use App\Models\User;
use App\Services\ActionService;
use Database\Seeders\ReferentielInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base des tests : base MySQL de test dédiée, référentiel initial chargé, fabrique d'utilisateurs par rôle.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = ReferentielInitialSeeder::class;

    protected Service $service1;

    protected Service $service2;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.connections.mysql.database') !== 'inps_formation_test' && ! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
            $this->fail('Les tests doivent utiliser une base dont le nom se termine par « _test ».');
        }
        $d = Direction::create(['code' => 'T-DIR', 'nom' => 'Direction de test', 'actif' => true]);
        $this->service1 = Service::create(['direction_id' => $d->id, 'code' => 'T-S1', 'nom' => 'Service test 1', 'actif' => true]);
        $this->service2 = Service::create(['direction_id' => $d->id, 'code' => 'T-S2', 'nom' => 'Service test 2', 'actif' => true]);
        AnneeReference::create(['annee' => 2026, 'statut' => 'ouverte']);
    }

    protected function utilisateur(string $role, ?Service $service = null, array $attributs = []): User
    {
        $u = new User(array_merge(['name' => 'Test '.$role, 'email' => $role.'.'.uniqid().'@exemple.invalid', 'password' => 'Mot-de-passe-Test-2026!',
            'service_id' => ($service ?? $this->service1)->id], $attributs));
        $u->actif = true;
        $u->save();
        $u->roles()->attach(Role::where('code', $role)->value('id'));

        return $u;
    }

    /** Crée une formation (action + session unique) et renvoie sa session. Par défaut : interne, le lundi 5 octobre 2026, 10 places. */
    protected function nouvelleFormation(User $u, array $donnees = []): SessionFormation
    {
        $a = app(ActionService::class)->creerFormation(array_merge(['intitule' => 'Formation test', 'type' => 'interne', 'modalite' => 'presentiel',
            'date_debut' => '2026-10-05', 'date_fin' => '2026-10-05', 'places' => 10], $donnees), $u);

        return $a->session()->firstOrFail();
    }

    /** Champs de l'analyse du besoin exigés par le formulaire de demande (1.8.0). */
    protected function analyse(): array
    {
        return ['diag_savoir' => 'non', 'diag_moyens' => 'oui', 'diag_temps' => 'oui', 'gravite' => 'reguliere', 'situation_actuelle' => '20 erreurs par mois',
            'objectif_vise' => 'Moins de 3 erreurs par mois', 'solutions_essayees' => ['aucune'], 'justification_formation' => 'Jamais formés', 'resultat_attendu' => 'Savoir faire X'];
    }
}
