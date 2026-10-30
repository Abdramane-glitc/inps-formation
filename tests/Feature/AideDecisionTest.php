<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AnneeReference;
use App\Models\BesoinFormation;
use App\Models\Direction;
use App\Models\Domaine;
use App\Models\EvaluationImpact;
use App\Models\Nomenclature;
use App\Models\Service;
use App\Services\ActionService;
use App\Services\AideDecisionService;
use App\Services\BesoinService;
use App\Services\InscriptionService;
use App\Support\Similarite;
use Tests\TestCase;

/**
 * 2.1.0 : aide à la décision de la RH (agents déjà formés, demandes semblables, résultat des formations semblables)
 * et suppression des référentiels jamais utilisés.
 */
class AideDecisionTest extends TestCase
{
    public function test_rapprochement_des_intitules(): void
    {
        $this->assertTrue(Similarite::proches('Excel perfectionnement pour le suivi des cotisations', 'Formation Excel : tableaux de cotisations'));
        $this->assertTrue(Similarite::proches('Excel', 'Initiation à Excel'));
        $this->assertFalse(Similarite::proches('Excel pour les cotisations', 'Accueil des assurés au guichet'));
        $this->assertFalse(Similarite::proches('Formation de base', 'Formation avancée'), 'Les mots trop généraux ne rapprochent pas deux formations.');
    }

    public function test_signaux_pour_la_rh(): void
    {
        $rh = $this->utilisateur('responsable_drh');
        $chef = $this->utilisateur('chef_service');
        $chef2 = $this->utilisateur('chef_service', $this->service2);
        $a1 = Agent::create(['matricule' => 'AD1', 'nom' => 'COULIBALY', 'prenom' => 'Awa', 'service_id' => $this->service1->id]);
        $a2 = Agent::create(['matricule' => 'AD2', 'nom' => 'DIARRA', 'prenom' => 'Ali', 'service_id' => $this->service1->id]);

        // Une formation Excel a déjà eu lieu il y a 8 mois, suivie par Awa, évaluée à froid
        $s = $this->nouvelleFormation($rh, ['intitule' => 'Excel pour le suivi des cotisations', 'date_debut' => now()->subMonths(8)->toDateString(), 'date_fin' => now()->subMonths(8)->toDateString()]);
        app(InscriptionService::class)->inscrire($s, [$a1->id], $rh);
        $ins = $s->inscriptions()->firstOrFail();
        app(ActionService::class)->terminer($s, [$ins->id], $rh);
        EvaluationImpact::create(['inscription_id' => $ins->id, 'date_evaluation' => today(), 'niveau_mise_en_pratique' => 2, 'resultat_atteint' => 'non']);

        // Un autre service demande aussi Excel
        app(BesoinService::class)->creer(['intitule' => 'Tableaux Excel des cotisations', 'contexte' => 'X'] + $this->analyse(), $chef2, []);

        $b = app(BesoinService::class)->creer(['intitule' => 'Excel avancé : cotisations', 'contexte' => 'Erreurs'] + $this->analyse(), $chef, [$a1->id, $a2->id]);
        $titres = collect(app(AideDecisionService::class)->signaux($b))->pluck('titre')->implode(' | ');
        $this->assertStringContainsString('une formation est la bonne réponse', $titres);
        $this->assertStringContainsString('1 agent(s) de la demande ont déjà suivi une formation proche', $titres);
        $this->assertStringContainsString('0 agent(s) sur 1 appliquent ce qu\'ils ont appris (en partie : 0, non : 1)', $titres);
        $this->assertStringContainsString('1 autre(s) service(s) demandent une formation proche', $titres);

        $this->actingAs($rh)->get(route('besoins.show', $b))->assertOk()->assertSee('Aide à la décision')->assertSee('COULIBALY Awa : « Excel pour le suivi des cotisations »', false);

        // Sans rapprochement possible : seule la conclusion du diagnostic
        $autre = app(BesoinService::class)->creer(['intitule' => 'Accueil au guichet', 'contexte' => 'X'] + $this->analyse(), $chef, [$a2->id]);
        $this->assertCount(1, app(AideDecisionService::class)->signaux($autre));
    }

    public function test_suppression_d_un_referentiel_jamais_utilise(): void
    {
        $rh = $this->utilisateur('admin_fonctionnel');
        $vide = Direction::create(['code' => 'DOUBLON', 'nom' => 'Direction en double', 'actif' => true]);
        $utilisee = $this->service1->direction;

        $page = $this->actingAs($rh)->get(route('admin.referentiels.index', 'directions'))->assertOk();
        // (même adresse que l'enregistrement de la ligne : on vérifie le message de confirmation du bouton)
        $page->assertSee('Supprimer définitivement « Direction en double »', false)
            ->assertDontSee('Supprimer définitivement « '.$utilisee->nom.' »', false)->assertSee('Utilisé : ');

        // Utilisée : refus expliqué, rien n'est supprimé
        $this->actingAs($rh)->delete(route('admin.referentiels.destroy', ['directions', $utilisee->id]))
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'service(s)') && str_contains($m, 'Décochez « Actif »'));
        $this->assertNotNull($utilisee->fresh());

        $this->actingAs($rh)->delete(route('admin.referentiels.destroy', ['directions', $vide->id]))->assertSessionHas('succes');
        $this->assertNull(Direction::find($vide->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'referentiel.suppression']);

        // Service avec un agent ; domaine, nomenclature et année jamais utilisés
        Agent::create(['matricule' => 'SR1', 'nom' => 'X', 'prenom' => 'Y', 'service_id' => $this->service1->id]);
        $this->actingAs($rh)->delete(route('admin.referentiels.destroy', ['services', $this->service1->id]))->assertSessionHas('erreur', fn ($m) => str_contains($m, 'agent(s)'));
        $srvVide = Service::create(['direction_id' => $utilisee->id, 'code' => 'S-VIDE', 'nom' => 'Service vide', 'actif' => true]);
        $this->actingAs($rh)->delete(route('admin.referentiels.destroy', ['services', $srvVide->id]))->assertSessionHas('succes');
        $dom = Domaine::create(['code' => 'DOM-V', 'nom' => 'Domaine vide', 'actif' => true]);
        $this->actingAs($rh)->delete(route('admin.referentiels.destroy', ['domaines', $dom->id]))->assertSessionHas('succes');
        $nom = Nomenclature::create(['type' => 'statut_professionnel', 'code' => 'essai', 'libelle' => 'Essai', 'actif' => true, 'ordre' => 9]);
        $this->actingAs($rh)->delete(route('admin.referentiels.destroy', ['categorie_document', $nom->id]))->assertNotFound(); // mauvais type
        $this->actingAs($rh)->delete(route('admin.referentiels.destroy', ['statut_professionnel', $nom->id]))->assertSessionHas('succes');

        // Une année portée par une demande n'est pas supprimable
        $an = AnneeReference::create(['annee' => 2031, 'statut' => 'ouverte']);
        $chef = $this->utilisateur('chef_service');
        BesoinFormation::query()->getModel()->newQuery()->getConnection()->table('besoins_formation')->insert([
            'reference' => 'BES-2031-0001', 'annee' => 2031, 'service_id' => $this->service1->id, 'intitule' => 'X', 'statut' => 'soumise', 'auteur_id' => $chef->id,
            'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($rh)->delete(route('admin.referentiels.destroy', ['annees', $an->id]))->assertSessionHas('erreur', fn ($m) => str_contains($m, 'demande(s)'));
        $this->actingAs($this->utilisateur('chef_service'))->delete(route('admin.referentiels.destroy', ['domaines', 1]))->assertForbidden();
    }
}
