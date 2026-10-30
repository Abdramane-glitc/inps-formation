<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\EnvoiEmail;
use App\Models\Organisme;
use App\Models\Parametre;
use App\Models\RappelEnvoye;
use App\Notifications\NotificationMetier;
use App\Services\ActionService;
use App\Services\IndicateurService;
use App\Services\InscriptionService;
use App\Services\NotificationService;
use App\Services\RapportService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RapportNotificationTest extends TestCase
{
    /** 2 actions ; l'agent A suit les deux (achevées), B en suit une (achevée), C est inscrit mais non achevé. */
    private function donnees(): array
    {
        $drh = $this->utilisateur('responsable_drh');
        $ag = [];
        foreach (['A', 'B', 'C', 'D'] as $x) {
            $ag[$x] = Agent::create(['matricule' => 'R'.$x, 'nom' => 'AGENT'.$x, 'prenom' => 'P', 'service_id' => $this->service1->id, 'direction_id' => $this->service1->direction_id])->id;
        }
        foreach ([['2026-10-05', [$ag['A'], $ag['B'], $ag['C']], 'interne'], ['2026-10-12', [$ag['A']], 'externe']] as $k => [$d, $ids, $type]) {
            $s = $this->nouvelleFormation($drh, ['intitule' => 'Action '.$k, 'type' => $type,
                'organisme_id' => $type === 'externe' ? Organisme::create(['raison_sociale' => 'Org'])->id : null, 'date_debut' => $d, 'date_fin' => $d]);
            app(InscriptionService::class)->inscrire($s, $ids, $drh);
            // La RH coche ceux qui ont suivi la formation : tous sauf C
            $suivis = $s->participantsActifs()->where('agent_id', '!=', $ag['C'])->pluck('id')->all();
            app(ActionService::class)->terminer($s->fresh(), $suivis, $drh);
        }

        return [$drh, $ag];
    }

    public function test_indicateurs_sans_double_comptage(): void
    {
        $this->donnees();
        $k = app(IndicateurService::class)->tableauDeBord(['annee' => 2026]);
        $this->assertSame(2, $k['agents_formes'], 'A et B : agents uniques.');
        $this->assertSame(4, $k['inscriptions_retenues']);
        $this->assertSame(100.0 * 3 / 4, $k['taux_achevement']);
        $this->assertSame(75.0, $k['taux_participation']);
        $this->assertSame(1, $k['actions']['internes']);
        $this->assertSame(1, $k['actions']['externes']);
        $this->assertArrayNotHasKey('budget', $k, 'Plus de budget dans l\'application.');
        // Filtre domaine sans données : zéro, jamais de chiffres fictifs
        $vide = app(IndicateurService::class)->tableauDeBord(['annee' => 2030]);
        $this->assertSame(0, $vide['agents_formes']);
        $this->assertNull($vide['taux_achevement']);
    }

    public function test_tous_les_rapports_se_generent_et_s_exportent(): void
    {
        [$drh] = $this->donnees();
        foreach (array_keys(RapportService::RAPPORTS) as $code) {
            $this->actingAs($drh)->get(route('rapports.show', [$code, 'annee' => 2026]))->assertOk();
            $this->actingAs($drh)->get(route('rapports.export', [$code, 'xlsx', 'annee' => 2026]))->assertOk();
            $this->actingAs($drh)->get(route('rapports.export', [$code, 'pdf', 'annee' => 2026]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $this->actingAs($drh)->get('/tableau-de-bord?annee=2026')->assertOk();
    }

    public function test_rapport_agents_formes_et_agents_sans_formation(): void
    {
        $this->donnees();
        $r = app(RapportService::class)->generer('agents_formes', ['annee' => 2026]);
        $this->assertSame(2, $r['lignes'][0][2]);   // agents uniques
        $this->assertSame(4, $r['lignes'][0][3]);   // inscriptions
        $this->assertSame(3, $r['lignes'][0][4]);   // achevées
        $sans = app(RapportService::class)->generer('agents_sans_formation', ['annee' => 2026]);
        $this->assertEqualsCanonicalizing(['RC', 'RD'], array_column($sans['lignes'], 0));
    }

    public function test_echeances_generent_des_rappels_sans_doublon(): void
    {
        $this->travelTo('2026-09-30 08:00');
        $drh = $this->utilisateur('responsable_drh');
        $s = $this->nouvelleFormation($drh, ['intitule' => 'Proche', 'places' => 5]);
        app(InscriptionService::class)->inscrire($s, [Agent::create(['matricule' => 'E1', 'nom' => 'A', 'prenom' => 'B'])->id], $drh);
        $this->artisan('inps:echeances')->assertSuccessful();
        $n = $drh->notifications()->count();
        $this->assertGreaterThanOrEqual(1, $n, 'Formation à venir.');
        $this->assertSame(0, RappelEnvoye::where('cle', 'like', 'convocation%')->orWhere('cle', 'like', 'evaluation%')->count(), 'Plus de rappel de convocation ni d\'évaluation.');
        // Formation passée non terminée : rappel de cocher qui l'a suivie
        $this->travelTo('2026-10-08 08:00');
        $this->artisan('inps:echeances')->assertSuccessful();
        $this->assertTrue(RappelEnvoye::where('cle', "session_non_cloturee:{$s->id}")->exists());
        $n = $drh->fresh()->notifications()->count();
        $this->artisan('inps:echeances')->assertSuccessful();
        $this->assertSame($n, $drh->fresh()->notifications()->count(), 'Aucun doublon.');
        $this->assertNotNull(cache('inps.planificateur'));
    }

    public function test_email_desactive_par_defaut_et_jamais_declare_envoye_sans_smtp(): void
    {
        $u = $this->utilisateur('agent');
        app(NotificationService::class)->notifier($u, 'test', 'Titre', 'Message');
        $this->assertSame(1, $u->notifications()->count());
        $this->assertSame(0, EnvoiEmail::count());
        Parametre::where('cle', 'notifications.email_actif')->update(['valeur' => '1']);
        Parametre::viderCache();
        config(['mail.default' => 'log']);
        app(NotificationService::class)->notifier($u, 'test', 'Titre', 'Message');
        $this->assertSame('non_remis', EnvoiEmail::latest('id')->value('statut'));
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        app()->forgetInstance('mail.manager');
        Mail::clearResolvedInstances(); // vrai transport SMTP : la connexion échoue
        app(NotificationService::class)->notifier($u, 'test', 'Titre', 'Message');
        $this->assertSame('echec', EnvoiEmail::latest('id')->value('statut'));
    }

    public function test_notifications_consultables_et_marquees_lues(): void
    {
        $u = $this->utilisateur('agent');
        $u->notify(new NotificationMetier('test', 'Bonjour', 'Contenu', route('espace.index')));
        $this->actingAs($u)->get('/notifications')->assertOk()->assertSee('Bonjour');
        $this->actingAs($u)->post(route('notifications.lue', $u->notifications()->first()->id))->assertRedirect(route('espace.index'));
        $this->assertSame(0, $u->unreadNotifications()->count());
    }
}
