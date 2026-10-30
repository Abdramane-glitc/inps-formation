<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Direction;
use App\Models\Inscription;
use App\Models\Service;
use App\Models\SessionFormation;
use App\Models\User;
use App\Services\InscriptionService;
use Tests\TestCase;

/** 29/10/2026 : vidage des agents, directions et services de test ; les comptes et les formations restent tels quels. */
class ViderPersonnelTest extends TestCase
{
    public function test_vidage_garde_les_comptes_et_les_formations(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $agent = Agent::create(['matricule' => 'VID1', 'nom' => 'A', 'prenom' => 'B', 'service_id' => $this->service1->id, 'direction_id' => $this->service1->direction_id]);
        $compteAgent = $this->utilisateur('agent', null, ['agent_id' => $agent->id]);
        $s = $this->nouvelleFormation($drh, ['date_debut' => now()->addMonth()->toDateString(), 'date_fin' => now()->addMonth()->toDateString()]);
        app(InscriptionService::class)->inscrire($s, [$agent->id], $drh);
        $this->assertSame(1, Inscription::count());

        // Réponse autre que OUI : rien n'est supprimé
        $this->artisan('inps:vider-personnel')->expectsQuestion('Tapez OUI pour supprimer (toute autre réponse annule)', 'non')->assertSuccessful();
        $this->assertSame(1, Agent::count());

        $this->artisan('inps:vider-personnel')->expectsQuestion('Tapez OUI pour supprimer (toute autre réponse annule)', 'OUI')->assertSuccessful();
        $this->assertSame([0, 0, 0, 0], [Agent::count(), Direction::count(), Service::count(), Inscription::count()]);
        $this->assertSame(1, SessionFormation::count(), 'Les formations restent (sans participants).');
        foreach ([$drh, $compteAgent] as $u) {
            $u2 = User::findOrFail($u->id);
            $this->assertTrue($u2->actif, 'Comptes conservés tels quels.');
            $this->assertSame($u->roles()->pluck('code')->all(), $u2->roles()->pluck('code')->all());
            $this->assertNull($u2->service_id);
        }
        $this->assertNull(User::find($compteAgent->id)->agent_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'referentiel.vidage_personnel']);
    }

    public function test_import_depuis_la_console(): void
    {
        $this->utilisateur('responsable_drh');
        $f = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($f, "UA_CODE;DESIGNATION;SIGLE\n9010000;Direction fictive;DF\n;Sans code;\n");
        $this->artisan('inps:importer', ['type' => 'structure', 'fichier' => $f])->expectsOutputToContain('valides : 1')->assertSuccessful();
        $this->assertDatabaseMissing('directions', ['code' => '9010000']);
        $this->artisan('inps:importer', ['type' => 'structure', 'fichier' => $f, '--confirmer' => true])->expectsOutputToContain('1 ligne(s) importée(s)')->assertSuccessful();
        $this->assertDatabaseHas('directions', ['code' => '9010000', 'sigle' => 'DF']);
        $this->artisan('inps:importer', ['type' => 'agents', 'fichier' => '/introuvable.csv'])->assertFailed();
        // Tout ou rien : la ligne déjà présente bloque tout le fichier
        file_put_contents($f, "UA_CODE;DESIGNATION;SIGLE\n9010000;Direction fictive;DF\n9020000;Autre fictive;AF\n");
        $this->artisan('inps:importer', ['type' => 'structure', 'fichier' => $f, '--confirmer' => true, '--tout-ou-rien' => true])->assertFailed();
        $this->assertDatabaseMissing('directions', ['code' => '9020000']);
    }
}
