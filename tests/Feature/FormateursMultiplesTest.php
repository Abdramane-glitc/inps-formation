<?php

namespace Tests\Feature;

use App\Enums\StatutSession;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\Formateur;
use App\Models\Organisme;
use App\Models\SessionFormation;
use Tests\TestCase;

/**
 * 1.8.0 : une formation peut avoir plusieurs formateurs, internes et externes ensemble (type « Mixte »).
 */
class FormateursMultiplesTest extends TestCase
{
    public function test_formation_mixte_avec_un_formateur_interne_et_un_externe(): void
    {
        $rh = $this->utilisateur('responsable_drh');
        $org = Organisme::create(['raison_sociale' => 'Cabinet Essai', 'actif' => true]);
        $ag = Agent::create(['matricule' => 'FI01', 'nom' => 'KEITA', 'prenom' => 'Awa', 'service_id' => $this->service1->id]);
        $interne = Formateur::create(['type' => 'interne', 'agent_id' => $ag->id, 'nom' => 'KEITA', 'prenom' => 'Awa', 'actif' => true]);
        $externe = Formateur::create(['type' => 'externe', 'organisme_id' => $org->id, 'nom' => 'TRAORE', 'prenom' => 'Moussa', 'actif' => true]);
        $base = ['intitule' => 'Accueil des assurés', 'date_debut' => '2026-11-09', 'date_fin' => '2026-11-10', 'formateurs_envoyes' => 1];

        // Le formulaire présente les formateurs internes et externes, et le type « Mixte »
        $this->actingAs($rh)->get(route('formations.create'))->assertOk()->assertSee('Mixte (formateurs internes et externes)')
            ->assertSee('KEITA Awa')->assertSee('TRAORE Moussa');

        // Type incohérent avec les formateurs choisis : refusé avec une explication
        $this->actingAs($rh)->post(route('formations.store'), $base + ['type' => 'interne', 'formateurs' => [$interne->id, $externe->id]])
            ->assertSessionHasErrors(['formateurs' => 'Un formateur externe est choisi : choisissez le type « Externe » ou « Mixte ».']);
        $this->actingAs($rh)->post(route('formations.store'), $base + ['type' => 'externe', 'organisme_id' => $org->id, 'formateurs' => [$interne->id]])
            ->assertSessionHasErrors('formateurs');
        // Mixte : l'organisme du prestataire est obligatoire
        $this->actingAs($rh)->post(route('formations.store'), $base + ['type' => 'mixte', 'formateurs' => [$interne->id, $externe->id]])->assertSessionHasErrors('organisme_id');

        $this->actingAs($rh)->post(route('formations.store'), $base + ['type' => 'mixte', 'organisme_id' => $org->id, 'formateurs' => [$interne->id, $externe->id]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $a = ActionFormation::latest('id')->firstOrFail();
        $s = $a->session()->firstOrFail();
        $this->assertSame('mixte', $a->type);
        $this->assertEqualsCanonicalizing([$interne->id, $externe->id], $s->formateurs->pluck('id')->all());
        $this->actingAs($rh)->get(route('formations.show', $a))->assertOk()->assertSee('Mixte (interne et externe)')
            ->assertSee('Formateurs')->assertSee('KEITA Awa (interne)')->assertSee('TRAORE Moussa (externe — Cabinet Essai)');
        $this->actingAs($rh)->get(route('sessions.emargement', $s))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($rh)->get(route('tableau-bord', ['type' => 'mixte']))->assertOk();

        // Les formateurs restent modifiables après la formation ; tout décocher vide la liste
        $s->statut = StatutSession::Terminee;
        $s->save();
        $maj = ['intitule' => $a->intitule, 'type' => 'externe', 'organisme_id' => $org->id, 'formateurs_envoyes' => 1];
        $this->actingAs($rh)->put(route('formations.update', $a), $maj + ['formateurs' => [$externe->id]])->assertSessionHasNoErrors();
        $this->assertSame([$externe->id], $s->fresh()->formateurs->pluck('id')->all());
        $this->actingAs($rh)->put(route('formations.update', $a), $maj)->assertSessionHasNoErrors();
        $this->assertCount(0, $s->fresh()->formateurs);
        $this->assertDatabaseHas('audit_logs', ['action' => 'action.formateurs', 'objet_id' => $a->id]);
    }

    public function test_l_ancien_formateur_unique_est_repris(): void
    {
        $rh = $this->utilisateur('responsable_drh');
        $fm = Formateur::create(['type' => 'externe', 'nom' => 'ANCIEN', 'prenom' => 'Formateur', 'actif' => true]);
        $s = $this->nouvelleFormation($rh);
        // Une formation enregistrée avant la 1.8.0 ne connaît que formateur_id : la migration l'a recopié dans la liaison
        SessionFormation::whereKey($s->id)->update(['formateur_id' => $fm->id]);
        $migration = require database_path('migrations/2026_10_26_001600_formateurs_multiples.php');
        $migration->down();
        $migration->up();
        $this->assertSame([$fm->id], $s->fresh()->formateurs->pluck('id')->all());
    }
}
