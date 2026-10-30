<?php

namespace Tests\Feature;

use App\Models\AnneeReference;
use App\Models\AuditLog;
use App\Models\Direction;
use App\Models\Domaine;
use App\Models\Nomenclature;
use App\Models\Service;
use App\Services\Audit;
use App\Services\SauvegardeService;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Référentiels (1.7.1) : création et modification de chaque type depuis l'écran d'administration, journalisées
 * sans erreur (une modification de direction provoquait une erreur 500 : type absent de la table des types).
 */
class ReferentielTest extends TestCase
{
    public function test_chaque_modele_a_un_type_stable_pour_le_journal(): void
    {
        foreach (glob(app_path('Models/*.php')) as $f) {
            $classe = 'App\\Models\\'.basename($f, '.php');
            if (is_subclass_of($classe, Model::class) && ! (new \ReflectionClass($classe))->isAbstract()) {
                $this->assertNotSame('', (new $classe)->getMorphClass(), $classe);
            }
        }
    }

    public function test_un_modele_hors_table_des_types_ne_fait_pas_echouer_le_journal(): void
    {
        $m = new class extends Model
        {
            protected $table = 'directions';
        };
        $log = Audit::enregistrer('essai', $m, 'Essai');
        $this->assertNotEmpty($log->objet_type);
    }

    public function test_creation_et_modification_de_chaque_referentiel(): void
    {
        $rh = $this->utilisateur('admin_fonctionnel');
        $dir = Direction::firstOrFail();
        $cas = [
            'directions' => [['code' => 'DRH', 'nom' => 'Direction des ressources humaines', 'actif' => 1], ['code' => 'DRH', 'nom' => 'Direction des Ressources humaines'], Direction::class, 'nom'],
            'services' => [['code' => 'SRV-X', 'nom' => 'Service X', 'direction_id' => $dir->id, 'actif' => 1], ['code' => 'SRV-X', 'nom' => 'Service X modifié', 'direction_id' => $dir->id, 'actif' => 1], Service::class, 'nom'],
            'domaines' => [['code' => 'DOM-X', 'nom' => 'Domaine X', 'actif' => 1], ['code' => 'DOM-X', 'nom' => 'Domaine X modifié', 'description' => 'Texte'], Domaine::class, 'nom'],
            'annees' => [['annee' => 2030, 'ouverte' => 1], ['annee' => 2031], AnneeReference::class, 'annee'],
        ];
        foreach (array_keys(Nomenclature::TYPES) as $type) {
            $cas[$type] = [['code' => 'essai_'.$type, 'libelle' => 'Essai', 'actif' => 1], ['code' => 'essai_'.$type, 'libelle' => 'Essai modifié', 'actif' => 1], Nomenclature::class, 'libelle'];
        }
        foreach ($cas as $type => [$creation, $modification, $classe, $champ]) {
            $this->actingAs($rh)->post(route('admin.referentiels.store', $type), $creation)->assertSessionHasNoErrors()->assertSessionHas('succes');
            $m = $classe::latest('id')->firstOrFail();
            $this->actingAs($rh)->put(route('admin.referentiels.update', [$type, $m->id]), $modification)->assertSessionHasNoErrors()->assertSessionHas('succes');
            $this->assertSame($modification[$champ], $m->fresh()->{$champ}, $type);
            $this->assertTrue(AuditLog::where('action', 'referentiel.modification')->where('objet_id', $m->id)->exists(), $type);
            $this->actingAs($rh)->get(route('admin.referentiels.index', $type))->assertOk();
        }
        $this->assertFalse(Direction::where('code', 'DRH')->firstOrFail()->actif, 'Case « actif » décochée = désactivée.');
        $this->actingAs($this->utilisateur('admin_technique'))->get('/administration/audit?categorie=administration')->assertOk()->assertSee('Référentiel modifié');
    }

    /** 1.7.4 : le choix du service se fait en deux temps (direction, puis service) ; tous les services actifs sont proposés. */
    public function test_le_choix_du_service_propose_tous_les_services_classes_par_direction(): void
    {
        $admin = $this->utilisateur('admin_technique');
        $cible = $this->utilisateur('chef_service');
        $page = $this->actingAs($admin)->get(route('admin.utilisateurs.edit', $cible))->assertOk();
        $page->assertSee('data-services-de="#compte_dir"', false);
        foreach (Service::actifs()->get() as $s) {
            $page->assertSee('value="'.$s->id.'" data-direction="'.$s->direction_id.'"', false);
        }
        foreach (Direction::actives()->get() as $d) {
            $page->assertSee('<option value="'.$d->id.'"', false);
        }
        $this->actingAs($this->utilisateur('responsable_drh'))->get(route('agents.create'))->assertOk()->assertSee('data-services-de="#agent_dir"', false)->assertSee('name="direction_id"', false);
    }

    /**
     * 1.7.4 : chaque champ d'une ligne doit être rattaché au formulaire de son bouton « Enregistrer ».
     * La case « Actif » des directions, services et domaines ne l'était pas : enregistrer une ligne la désactivait.
     */
    public function test_chaque_champ_d_une_ligne_est_envoye_avec_son_bouton(): void
    {
        $rh = $this->utilisateur('admin_fonctionnel');
        foreach (array_merge(['directions', 'services', 'domaines', 'annees'], array_keys(Nomenclature::TYPES)) as $type) {
            $html = $this->actingAs($rh)->get(route('admin.referentiels.index', $type))->assertOk()->getContent();
            $dom = new \DOMDocument;
            @$dom->loadHTML($html);
            $lignes = 0;
            foreach ((new \DOMXPath($dom))->query('//tbody/tr[.//button[starts-with(@form, "f")]]') as $tr) {
                $formulaire = (new \DOMXPath($dom))->query('.//button', $tr)->item(0)->getAttribute('form');
                foreach ((new \DOMXPath($dom))->query('.//input[not(ancestor::form)]|.//select[not(ancestor::form)]', $tr) as $champ) {
                    $this->assertSame($formulaire, $champ->getAttribute('form'), "$type : champ « {$champ->getAttribute('name')} » non envoyé avec sa ligne");
                }
                $lignes++;
            }
            $this->assertGreaterThan(0, $lignes, $type);
        }
    }

    public function test_enregistrer_une_direction_sans_toucher_la_case_la_garde_active(): void
    {
        $rh = $this->utilisateur('admin_fonctionnel');
        $d = Direction::where('actif', true)->firstOrFail();
        // Ce que le navigateur envoie quand la case « Actif » cochée est bien rattachée à la ligne
        $this->actingAs($rh)->put(route('admin.referentiels.update', ['directions', $d->id]), ['code' => $d->code, 'nom' => 'Nom corrigé', 'actif' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($d->fresh()->actif);
    }

    /** 2.5.0 : années simplifiées — l'année et une case « Demandes ouvertes ». */
    public function test_annees_simplifiees(): void
    {
        $rh = $this->utilisateur('admin_fonctionnel');
        $this->actingAs($rh)->get(route('admin.referentiels.index', 'annees'))->assertOk()->assertSee('Demandes ouvertes')
            ->assertDontSee('Date limite des besoins')->assertDontSee('Date de clôture');
        $this->actingAs($rh)->post(route('admin.referentiels.store', 'annees'), ['annee' => 2033, 'ouverte' => 1])->assertSessionHas('succes');
        $a = AnneeReference::where('annee', 2033)->firstOrFail();
        $this->assertSame('ouverte', $a->statut);
        $this->actingAs($rh)->put(route('admin.referentiels.update', ['annees', $a->id]), ['annee' => 2033])->assertSessionHas('succes');
        $this->assertSame('cloturee', $a->fresh()->statut);
    }

    /** 2.5.0 : une ligne « BACKUP_PATH= » vide n'empêche plus l'écran Sauvegardes de s'ouvrir. */
    public function test_dossier_de_sauvegarde_vide(): void
    {
        config(['inps.sauvegarde.repertoire' => '']);
        $this->assertSame(storage_path('app/sauvegardes'), app(SauvegardeService::class)->repertoire());
        $admin = $this->utilisateur('admin_technique');
        $this->actingAs($admin)->get(route('admin.systeme.index'))->assertOk();
    }
}
