<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\FormationDiplomante;
use App\Models\Role;
use App\Services\SuppressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 2.5.0 : formations diplômantes des agents (licence, master, doctorat…), saisies par la RH, sans montants ;
 * profil « Chef de service » renommé « Responsable de structure ».
 */
class DiplomanteTest extends TestCase
{
    private function donnees(Agent $ag, array $autres = []): array
    {
        return array_merge(['agent_id' => $ag->id, 'niveau' => 'master', 'intitule' => 'Gestion des ressources humaines', 'etablissement' => 'Université de test',
            'ville' => 'Bamako', 'pays' => 'Mali', 'annee_debut' => 2025, 'duree_annees' => 2, 'mode' => 'cours_du_soir',
            'prise_en_charge' => 'inps_partielle', 'situation' => 'en_cours'], $autres);
    }

    public function test_la_rh_enregistre_suit_exporte_et_supprime(): void
    {
        $rh = $this->utilisateur('responsable_drh');
        $ag = Agent::create(['matricule' => 'DIP-001', 'nom' => 'TRAORE', 'prenom' => 'Fanta', 'service_id' => $this->service1->id]);

        $this->actingAs($rh)->get(route('diplomantes.index'))->assertOk()->assertSee('Aucune formation diplômante enregistrée');
        $this->actingAs($rh)->get(route('diplomantes.create', ['agent' => $ag->id]))->assertOk()->assertSee('Prise en charge');

        // Champs obligatoires et date d'obtention ignorée tant que le diplôme n'est pas obtenu
        $this->actingAs($rh)->post(route('diplomantes.store'), ['agent_id' => $ag->id])->assertSessionHasErrors(['niveau', 'intitule', 'etablissement', 'annee_debut', 'mode', 'prise_en_charge']);
        $this->actingAs($rh)->post(route('diplomantes.store'), $this->donnees($ag, ['date_obtention' => '2026-01-10']))->assertRedirect();
        $d = FormationDiplomante::firstOrFail();
        $this->assertSame('DIP-2025-0001', $d->reference);
        $this->assertNull($d->date_obtention);
        $this->assertSame('2025-2026', $d->annee_academique);
        $this->assertSame($rh->id, $d->cree_par);
        $this->assertDatabaseHas('audit_logs', ['action' => 'diplomante.creation']);

        // Aucun montant : la table n'a pas de colonne financière
        $this->assertEmpty(array_filter(\Schema::getColumnListing('formations_diplomantes'), fn ($c) => preg_match('/montant|cout|frais|prix/', $c)));

        $this->actingAs($rh)->get(route('diplomantes.show', $d))->assertOk()->assertSee('Master')->assertSee('Partielle par l\'INPS')->assertSee('Cours du soir');
        $this->actingAs($rh)->get(route('agents.show', $ag))->assertOk()->assertSee('Formations diplômantes')->assertSee('Gestion des ressources humaines');

        // Diplôme obtenu ; date future refusée
        $this->actingAs($rh)->put(route('diplomantes.update', $d), $this->donnees($ag, ['situation' => 'obtenu', 'date_obtention' => now()->addDay()->toDateString()]))->assertSessionHasErrors('date_obtention');
        $this->actingAs($rh)->put(route('diplomantes.update', $d), $this->donnees($ag, ['situation' => 'obtenu', 'date_obtention' => '2026-07-15']))->assertRedirect(route('diplomantes.show', $d));
        $this->assertSame('2026-07-15', $d->fresh()->date_obtention->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'diplomante.modification', 'description' => "Formation diplômante {$d->reference} modifiée : En cours → Diplôme obtenu"]);

        // Filtres et export Excel
        $this->actingAs($rh)->get(route('diplomantes.index', ['situation' => 'obtenu', 'niveau' => 'master', 'q' => 'TRAORE']))->assertOk()->assertSee('Université de test');
        $this->actingAs($rh)->get(route('diplomantes.index', ['situation' => 'en_cours']))->assertOk()->assertDontSee('Université de test');
        $this->actingAs($rh)->get(route('diplomantes.export', ['niveau' => 'master']))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Pièce jointe
        Storage::fake('prive');
        $f = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($f, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
        $this->actingAs($rh)->post(route('documents.store', ['diplomante', $d->id]), ['fichier' => new UploadedFile($f, 'diplome.pdf', 'application/pdf', null, true), 'titre' => 'Diplôme'])->assertSessionHas('succes');
        $this->assertSame(1, $d->documents()->count());

        // L'agent ne peut plus être supprimé (seulement désactivé)
        $this->assertNotNull(app(SuppressionService::class)->obstacleAgent($ag));

        $this->actingAs($rh)->delete(route('diplomantes.destroy', $d))->assertRedirect(route('diplomantes.index'));
        $this->assertDatabaseMissing('formations_diplomantes', ['id' => $d->id]);
        $this->assertSame(0, $d->documents()->count(), 'Les pièces jointes sont retirées avec la formation.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'diplomante.suppression']);
    }

    public function test_reserve_a_la_rh_et_visible_par_l_agent(): void
    {
        $rh = $this->utilisateur('responsable_drh');
        $chef = $this->utilisateur('chef_service');
        $ag = Agent::create(['matricule' => 'DIP-002', 'nom' => 'DIARRA', 'prenom' => 'Moussa', 'service_id' => $this->service1->id]);
        $this->actingAs($rh)->post(route('diplomantes.store'), $this->donnees($ag, ['niveau' => 'doctorat', 'intitule' => 'Droit social']))->assertRedirect();
        $d = FormationDiplomante::firstOrFail();

        foreach ([route('diplomantes.index'), route('diplomantes.show', $d), route('diplomantes.create'), route('diplomantes.export')] as $url) {
            $this->actingAs($chef)->get($url)->assertForbidden();
        }
        $this->actingAs($chef)->post(route('documents.store', ['diplomante', $d->id]), ['fichier' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])->assertForbidden();
        $this->actingAs($chef)->get(route('agents.show', $ag))->assertOk()->assertDontSee('Droit social');

        $compte = $this->utilisateur('agent');
        $compte->forceFill(['agent_id' => $ag->id])->save();
        $this->actingAs($compte)->get(route('espace.index'))->assertOk()->assertSee('Mes formations diplômantes')->assertSee('Doctorat')->assertSee('Droit social');
        $this->actingAs($compte)->get(route('diplomantes.show', $d))->assertForbidden();

        $autre = $this->utilisateur('agent');
        $autre->forceFill(['agent_id' => Agent::create(['matricule' => 'DIP-003', 'nom' => 'X', 'prenom' => 'Y', 'service_id' => $this->service1->id])->id])->save();
        $this->actingAs($autre)->get(route('espace.index'))->assertOk()->assertDontSee('Droit social');
    }

    public function test_profil_responsable_de_structure(): void
    {
        $this->assertSame('Responsable de structure', Role::where('code', 'chef_service')->value('nom'));
        $rh = $this->utilisateur('responsable_drh');
        $rh->roles()->attach(Role::where('code', 'admin_technique')->value('id'));
        $this->actingAs($rh)->get(route('admin.utilisateurs.create'))->assertOk()->assertSee('Responsable de structure')->assertDontSee('Chef de service');
        $this->actingAs($rh)->get(route('admin.roles.index'))->assertOk()->assertSee('Responsable de structure')->assertDontSee('Chef de service');
    }
}
