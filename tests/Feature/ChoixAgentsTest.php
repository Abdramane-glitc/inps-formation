<?php

namespace Tests\Feature;

use App\Http\Controllers\BesoinController;
use App\Models\Agent;
use App\Models\Direction;
use App\Services\InscriptionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** 29/10/2026 : choix des agents dans les formulaires, même avec des milliers d'agents (l'ancienne liste s'arrêtait à 1 000). */
class ChoixAgentsTest extends TestCase
{
    public function test_recherche_mots_dans_tout_ordre_et_cloisonnement(): void
    {
        Agent::create(['matricule' => '1808', 'nom' => 'DIARRA', 'prenom' => 'OUMAR', 'service_id' => $this->service1->id]);
        Agent::create(['matricule' => '1809', 'nom' => 'DIARRA', 'prenom' => 'AWA', 'service_id' => $this->service2->id]);
        $inactif = Agent::create(['matricule' => '1810', 'nom' => 'TRAORE', 'prenom' => 'Inactif', 'service_id' => $this->service1->id]);
        $inactif->forceFill(['actif' => false])->save();
        $drh = $this->utilisateur('responsable_drh');

        $noms = fn ($r) => collect($r->json('agents'))->pluck('matricule')->all();
        $this->assertSame(['1808'], $noms($this->actingAs($drh)->getJson(route('agents.choisir', ['q' => 'oumar diarra']))->assertOk()));
        $this->assertSame(['1809', '1808'], $noms($this->actingAs($drh)->getJson(route('agents.choisir', ['q' => 'DIARRA']))));
        $this->assertSame([], $noms($this->actingAs($drh)->getJson(route('agents.choisir', ['q' => 'd']))), 'Au moins 2 caractères.');
        $this->assertSame([], $noms($this->actingAs($drh)->getJson(route('agents.choisir', ['q' => 'TRAORE']))), 'Les agents inactifs ne sont pas proposés.');
        $this->assertSame([], $noms($this->actingAs($drh)->getJson(route('agents.choisir', ['q' => '%']))), 'Le joker SQL est neutralisé.');
        // Un responsable de structure ne trouve que les agents de son service
        $chef = $this->utilisateur('chef_service', $this->service1);
        $this->assertSame(['1808'], $noms($this->actingAs($chef)->getJson(route('agents.choisir', ['q' => 'DIARRA']))));
        // Un agent (profil Agent) n'a pas accès à la recherche
        $this->actingAs($this->utilisateur('agent'))->getJson(route('agents.choisir', ['q' => 'DIARRA']))->assertForbidden();
    }

    public function test_les_participants_actuels_sont_exclus(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $a = Agent::create(['matricule' => 'P1', 'nom' => 'PARTICIPANT', 'prenom' => 'Un', 'service_id' => $this->service1->id]);
        Agent::create(['matricule' => 'P2', 'nom' => 'PARTICIPANT', 'prenom' => 'Deux', 'service_id' => $this->service1->id]);
        $s = $this->nouvelleFormation($drh, ['date_debut' => now()->addMonth()->toDateString(), 'date_fin' => now()->addMonth()->toDateString()]);
        app(InscriptionService::class)->inscrire($s, [$a->id], $drh);
        $r = $this->actingAs($drh)->getJson(route('agents.choisir', ['q' => 'participant', 'session' => $s->id]));
        $this->assertSame(['P2'], collect($r->json('agents'))->pluck('matricule')->all());
    }

    public function test_au_dela_du_seuil_le_formulaire_propose_la_recherche(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->get(route('besoins.create'))->assertOk()->assertDontSee('data-choix-agents', false);
        $lignes = [];
        for ($i = 1; $i <= BesoinController::LISTE_MAX + 1; $i++) {
            $lignes[] = ['matricule' => 'N'.$i, 'nom' => 'NOMBREUX', 'prenom' => 'Agent '.$i, 'actif' => true, 'source' => 'saisie', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('agents')->insert($lignes);
        $page = $this->actingAs($drh)->get(route('besoins.create'))->assertOk();
        $page->assertSee('data-choix-agents', false)->assertDontSee('name="agents[]" value="', false);
        // La fiche d'une formation propose aussi la recherche, avec exclusion de ses participants
        $s = $this->nouvelleFormation($drh, ['date_debut' => now()->addMonth()->toDateString(), 'date_fin' => now()->addMonth()->toDateString()]);
        $this->actingAs($drh)->get(route('formations.show', $s->action_id))->assertOk()->assertSee(route('agents.choisir', ['session' => $s->id]), false);
        // L'envoi d'agents choisis par la recherche fonctionne comme avant
        $id = Agent::where('matricule', 'N301')->value('id');
        $this->actingAs($drh)->post(route('formations.participants', $s->action_id), ['agents' => [$id]])->assertSessionHas('succes');
        $this->assertTrue($s->participantsActifs()->where('agent_id', $id)->exists());
    }

    public function test_la_liste_complete_depasse_mille_agents_et_est_rangee_par_direction(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $dir = Direction::create(['code' => '9050000', 'nom' => 'Direction fictive des essais', 'sigle' => 'DFE', 'actif' => true]);
        $lignes = [];
        for ($i = 1; $i <= 1200; $i++) {
            $lignes[] = ['matricule' => 'M'.$i, 'nom' => 'ZZTEST', 'prenom' => 'Agent '.$i, 'direction_id' => $dir->id, 'actif' => true, 'source' => 'saisie', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('agents')->insert($lignes);
        $page = $this->actingAs($drh)->get(route('besoins.create'))->assertOk();
        $this->assertGreaterThanOrEqual(1200, substr_count($page->getContent(), 'name="agents[]"'), 'Tous les agents sont dans la liste (plus de limite à 1 000).');
        $page->assertSee('M1200 — ZZTEST Agent 1200')->assertSee('DFE — Direction fictive des essais')->assertDontSee('data-choix-agents', false);
    }
}
