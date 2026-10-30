<?php

namespace Tests\Feature;

use App\Models\Parametre;
use App\Support\Identite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Refonte visuelle : page de connexion, ressources institutionnelles, coquille de navigation. */
class InterfaceIdentiteTest extends TestCase
{
    private string $dossierVide;

    protected function setUp(): void
    {
        parent::setUp();
        // Les tests ne dépendent pas des fichiers officiels éventuellement présents dans public/images/institution.
        $this->dossierVide = sys_get_temp_dir().'/inps-identite-'.uniqid();
        mkdir($this->dossierVide);
        Identite::$dossierPublic = $this->dossierVide;
    }

    protected function tearDown(): void
    {
        Identite::$dossierPublic = null;
        array_map('unlink', glob($this->dossierVide.'/*') ?: []);
        @rmdir($this->dossierVide);
        parent::tearDown();
    }

    public function test_fichiers_officiels_deposes_sur_le_serveur(): void
    {
        copy(base_path('tests/fixtures/logo-test.png'), $this->dossierVide.'/logo.png');
        $this->get('/identite/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/identite/photo')->assertNotFound();
        $this->get('/connexion')->assertSee(route('identite.fichier', 'logo'), false)->assertSee('class="connexion-visuel sans-photo', false);
    }

    public function test_page_de_connexion_institutionnelle(): void
    {
        $r = $this->get('/connexion')->assertOk();
        $r->assertSee('à votre espace formation')->assertSee('INPS Formation')
            ->assertSee('Institut National de Prévoyance Sociale du Mali')
            ->assertSee('data-basculer-mdp', false)          // afficher / masquer le mot de passe
            ->assertSee(route('password.request'), false)
            ->assertDontSee('Se souvenir de moi');           // fonctionnalité non proposée
        $r->assertSee('class="connexion-visuel sans-photo', false); // aucune photographie fournie : rendu de repli
        $this->assertStringNotContainsString('<script>', preg_replace('/<script[^>]+src=[^>]+><\/script>/', '', $r->getContent()), 'Aucun script en ligne (CSP).');
    }

    public function test_erreur_de_connexion_affichee_sous_le_champ(): void
    {
        $this->post('/connexion', ['email' => 'inconnu@exemple.invalid', 'password' => 'x']);
        $this->get('/connexion')->assertSee('Identifiants incorrects', false)->assertSee('aria-invalid="true"', false);
    }

    public function test_ressources_institutionnelles_absentes_puis_deposees(): void
    {
        Storage::fake('prive');
        $this->get('/identite/logo')->assertNotFound();
        $this->get('/identite/photo')->assertNotFound();
        $this->get('/identite/autre')->assertNotFound();
        $this->assertNull(Identite::url('photo'));

        $admin = $this->utilisateur('admin_fonctionnel');
        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $photo = UploadedFile::fake()->image('siege.jpg', 1600, 900);
        $this->actingAs($admin)->put(route('admin.parametres.update'), ['logo' => $logo, 'photo' => $photo])->assertSessionHas('succes');
        Parametre::viderCache();

        auth()->logout();
        $this->get('/identite/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/identite/photo')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/connexion')->assertSee('class="photo"', false)->assertSee(route('identite.fichier', 'photo'), false);
        $this->assertNotNull(Identite::dataUri('logo'), 'Le logo est repris dans les documents PDF.');
    }

    public function test_photographie_trop_petite_refusee(): void
    {
        Storage::fake('prive');
        $this->actingAs($this->utilisateur('admin_fonctionnel'))
            ->put(route('admin.parametres.update'), ['photo' => UploadedFile::fake()->image('petite.jpg', 400, 300)])
            ->assertSessionHasErrors('photo');
    }

    public function test_coquille_navigation_selon_les_droits(): void
    {
        $r = $this->actingAs($this->utilisateur('responsable_drh'))->get('/besoins')->assertOk();
        $r->assertSee('aria-current="page"', false)->assertSee('Se déconnecter')
            ->assertSee('data-menu-reduire', false)->assertSee('data-menu-ouvrir', false)
            ->assertSee('id="recherche-globale"', false);                     // recherche générale (2.6.0)
        // Fil d'Ariane : seulement sous une rubrique (fiche, formulaire), pas sur la liste elle-même (2.6.0)
        $this->actingAs($this->utilisateur('responsable_drh'))->get('/besoins/creer')->assertOk()->assertSee('Fil d\'Ariane', false);
        $this->actingAs($this->utilisateur('agent'))->get('/mon-espace')->assertOk()
            ->assertDontSee(route('besoins.index'), false)->assertDontSee(route('admin.utilisateurs.index'), false);
    }

    public function test_couleur_institutionnelle_par_defaut_et_valeur_invalide_neutralisee(): void
    {
        $u = $this->utilisateur('responsable_drh');
        $this->actingAs($u)->get('/besoins')->assertSee('--couleur-principale: #003B73', false);
        Parametre::where('cle', 'institution.couleur_principale')->update(['valeur' => 'red;}</style><script>']);
        Parametre::viderCache();
        $this->actingAs($u)->get('/besoins')->assertSee('--couleur-principale: #003B73', false)->assertDontSee('</style><script>', false);
    }
}
