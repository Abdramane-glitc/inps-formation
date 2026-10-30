<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Services\IndicateurService;
use Tests\TestCase;

/** 2.6.0 : recherche générale (droits et cloisonnement) et nouvelles données du tableau de bord. */
class RechercheTableauBordTest extends TestCase
{
    public function test_recherche_respecte_droits_et_services(): void
    {
        Agent::create(['matricule' => 'RCH-1', 'nom' => 'KOUYATE', 'prenom' => 'Awa', 'service_id' => $this->service1->id]);
        Agent::create(['matricule' => 'RCH-2', 'nom' => 'KOUYATE', 'prenom' => 'Ali', 'service_id' => $this->service2->id]);
        $rh = $this->utilisateur('responsable_drh');
        $this->nouvelleFormation($rh, ['intitule' => 'Kouyaté et la comptabilité']);

        $this->actingAs($rh)->get(route('recherche', ['q' => 'kouyat']))->assertOk()
            ->assertSee('KOUYATE Awa')->assertSee('KOUYATE Ali')->assertSee('Formations (1)');
        // Responsable de structure : seulement les agents de son service
        $this->actingAs($this->utilisateur('chef_service', $this->service1))->get(route('recherche', ['q' => 'kouyat']))->assertOk()
            ->assertSee('KOUYATE Awa')->assertDontSee('KOUYATE Ali');
        // Agent : aucune rubrique
        $this->actingAs($this->utilisateur('agent'))->get(route('recherche', ['q' => 'kouyat']))->assertOk()
            ->assertDontSee('KOUYATE')->assertSee('Aucun résultat');
        // Moins de deux caractères : pas de recherche ; caractères spéciaux neutralisés
        $this->actingAs($rh)->get(route('recherche', ['q' => 'k']))->assertOk()->assertDontSee('KOUYATE');
        $this->actingAs($rh)->get(route('recherche', ['q' => '%_']))->assertOk()->assertDontSee('KOUYATE');
        $this->actingAs($rh)->get(route('recherche'))->assertOk();

        // Suggestions pendant la frappe (28/10/2026) : réponse JSON, mêmes droits et même cloisonnement
        $this->actingAs($rh)->getJson(route('recherche', ['q' => 'kouyat']))->assertOk()
            ->assertJsonPath('rubriques.0.titre', 'Personnel')->assertJsonCount(2, 'rubriques.0.lignes')->assertJsonPath('rubriques.1.titre', 'Formations');
        $json = $this->actingAs($this->utilisateur('chef_service', $this->service1))->getJson(route('recherche', ['q' => 'kouyat']))->assertOk()->json();
        $this->assertSame(['KOUYATE Awa'], collect($json['rubriques'])->flatMap(fn ($r) => array_column($r['lignes'], 'titre'))->all());
        $this->actingAs($this->utilisateur('agent'))->getJson(route('recherche', ['q' => 'kouyat']))->assertOk()->assertJsonCount(0, 'rubriques');
        $this->app['auth']->forgetGuards(); // visiteur non connecté
        $this->getJson(route('recherche', ['q' => 'kouyat']))->assertUnauthorized();
    }

    public function test_tableau_de_bord_par_mois_et_evolution(): void
    {
        $this->travelTo('2026-10-20 09:00');
        $rh = $this->utilisateur('responsable_drh');
        $this->nouvelleFormation($rh, ['date_debut' => '2026-03-10', 'date_fin' => '2026-03-11']);
        $this->nouvelleFormation($rh, ['date_debut' => '2026-03-20', 'date_fin' => '2026-03-20']);

        $mois = app(IndicateurService::class)->parMois(['annee' => 2026]);
        $this->assertCount(12, $mois);
        $this->assertSame(2, $mois[2]['programmees'], 'Deux formations en mars.');
        $this->assertSame(0, $mois[0]['programmees']);
        $this->assertSame(50.0, IndicateurService::variation(3, 2));
        $this->assertNull(IndicateurService::variation(3, 0));

        $this->actingAs($rh)->get(route('tableau-bord'))->assertOk()
            ->assertSee('Formations par mois')->assertSee('Participants par mois')->assertSee('Formations par type')
            ->assertSee('Mars : 0 terminée(s), 2 programmée(s), 0 annulée(s)', false);
    }
}
