<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Services\AccesAgentService;
use App\Services\ActionService;
use App\Services\AttestationService;
use App\Services\InscriptionService;
use Tests\TestCase;

/**
 * 28/10/2026 (demande de la RH) : nombre d'impressions de chaque attestation (téléchargements du PDF, d'après le journal)
 * et plus de téléchargement de l'attestation par l'agent dans « Mon espace ».
 */
class AttestationImpressionTest extends TestCase
{
    public function test_nombre_d_impressions_et_agent_sans_telechargement(): void
    {
        $rh = $this->utilisateur('responsable_drh');
        $agent = Agent::create(['matricule' => 'IMP-1', 'nom' => 'CISSE', 'prenom' => 'Adama', 'service_id' => $this->service1->id]);
        app(AccesAgentService::class)->genererCodes([$agent->id], $rh);
        $s = $this->nouvelleFormation($rh);
        app(InscriptionService::class)->inscrire($s, [$agent->id], $rh);
        $ins = $s->inscriptions()->first();
        app(ActionService::class)->terminer($s->fresh(), [$ins->id], $rh);
        $att = app(AttestationService::class)->emettre($ins->fresh(), $rh);

        // Pas encore imprimée
        $this->actingAs($rh)->get(route('attestations.index'))->assertOk()->assertSee('Imprimée')->assertSee('Jamais');
        $this->actingAs($rh)->get(route('formations.show', $s->action_id))->assertSee('Pas encore imprimée');

        // Deux téléchargements du PDF par la RH
        $this->actingAs($rh)->get(route('attestations.pdf', $att))->assertOk();
        $this->actingAs($rh)->get(route('attestations.pdf', $att))->assertOk();
        $this->actingAs($rh)->get(route('attestations.index'))->assertSee('2 fois');
        $this->actingAs($rh)->get(route('formations.show', $s->action_id))->assertSee('Imprimée 2 fois');

        // L'agent voit que son attestation est délivrée, mais ne peut plus la télécharger
        $compte = $agent->fresh()->user;
        $this->actingAs($compte)->get(route('espace.index'))->assertOk()->assertSee('Délivrée')->assertSee('à retirer auprès de la RH')
            ->assertDontSee('/mon-espace/attestations/');
        $this->actingAs($compte)->get('/mon-espace/attestations/'.$att->id)->assertNotFound();
        $this->actingAs($compte)->get(route('attestations.pdf', $att))->assertForbidden();
    }
}
