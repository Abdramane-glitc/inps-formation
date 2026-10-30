<?php

namespace Tests\Feature;

use App\Exceptions\RegleMetierException;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\Import;
use App\Models\Inscription;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\SessionFormation;
use App\Services\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AgentImportTest extends TestCase
{
    public function test_creation_modification_et_unicite_du_matricule(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->post('/agents', ['matricule' => 'mat-001', 'nom' => 'TEST', 'prenom' => 'Un', 'service_id' => $this->service1->id, 'direction_id' => $this->service1->direction_id])
            ->assertRedirect();
        $a = Agent::where('matricule', 'MAT-001')->firstOrFail(); // normalisé en majuscules
        $this->actingAs($drh)->post('/agents', ['matricule' => 'MAT-001', 'nom' => 'X', 'prenom' => 'Y'])->assertSessionHasErrors('matricule');
        $this->actingAs($drh)->put(route('agents.update', $a), ['matricule' => 'MAT-001', 'nom' => 'MODIFIE', 'prenom' => 'Un'])->assertRedirect();
        $this->assertSame('MODIFIE', $a->fresh()->nom);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent.modification', 'objet_id' => $a->id]);
    }

    public function test_service_incoherent_avec_la_direction_refuse(): void
    {
        $autreDir = Direction::create(['code' => 'T-D2', 'nom' => 'Autre', 'actif' => true]);
        $this->actingAs($this->utilisateur('responsable_drh'))->post('/agents', ['matricule' => 'M2', 'nom' => 'A', 'prenom' => 'B', 'direction_id' => $autreDir->id, 'service_id' => $this->service1->id])
            ->assertSessionHasErrors('service_id');
    }

    public function test_desactivation_logique_sans_suppression(): void
    {
        $a = Agent::create(['matricule' => 'M3', 'nom' => 'A', 'prenom' => 'B']);
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->post(route('agents.activation', $a), ['actif' => 0])->assertSessionHasErrors('motif');
        $this->actingAs($drh)->post(route('agents.activation', $a), ['actif' => 0, 'motif' => 'Départ'])->assertSessionHas('succes');
        $this->assertDatabaseHas('agents', ['id' => $a->id, 'actif' => false, 'motif_desactivation' => 'Départ']);
        $this->actingAs($drh)->get('/agents?actif=1')->assertDontSee('>M3</a>', false);
        $this->actingAs($drh)->get('/agents?actif=tous')->assertSee('>M3</a>', false);
    }

    public function test_recherche_multicritere_et_injection_neutralisee(): void
    {
        Agent::create(['matricule' => 'ABC1', 'nom' => 'DIALLO', 'prenom' => 'Awa', 'fonction' => 'Comptable', 'service_id' => $this->service1->id]);
        Agent::create(['matricule' => 'ABC2', 'nom' => 'KEITA', 'prenom' => 'Moussa', 'service_id' => $this->service2->id]);
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->get('/agents?q=compta')->assertSee('ABC1')->assertDontSee('ABC2');
        $this->actingAs($drh)->get('/agents?service_id='.$this->service2->id)->assertSee('ABC2')->assertDontSee('ABC1');
        $this->actingAs($drh)->get('/agents?q='.urlencode("' OR 1=1 --"))->assertOk()->assertDontSee('ABC1');
    }

    public function test_export_excel_autorise_et_journalise(): void
    {
        Agent::create(['matricule' => 'EXP1', 'nom' => 'A', 'prenom' => 'B']);
        $r = $this->actingAs($this->utilisateur('responsable_drh'))->get('/agents/export');
        $r->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.agents']);
        $this->actingAs($this->utilisateur('chef_service'))->get('/agents/export')->assertForbidden();
    }

    private function csv(string $contenu, string $nom = 'import.csv'): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($chemin, $contenu);

        return new UploadedFile($chemin, $nom, 'text/csv', null, true);
    }

    public function test_import_agents_apercu_doublons_erreurs_puis_confirmation(): void
    {
        Agent::create(['matricule' => 'EXIST1', 'nom' => 'DEJA', 'prenom' => 'La']);
        $drh = $this->utilisateur('responsable_drh');
        $csv = "matricule;nom;prenom;direction_code;service_code;fonction\n"
            ."NEW1;NOUVEAU;Un;T-DIR;T-S1;Agent\n"
            ."NEW2;NOUVEAU;Deux;;T-S2;\n"
            ."EXIST1;DEJA;La;;;\n"          // doublon base
            ."NEW1;DOUBLE;Fichier;;;\n"     // doublon fichier
            ."NEW3;;SansNom;;;\n"           // erreur : nom manquant
            ."NEW4;X;Y;;INCONNU;\n";        // référence inconnue
        $this->actingAs($drh)->post('/imports', ['type' => 'agents', 'fichier' => $this->csv($csv)])->assertRedirect();
        $imp = Import::latest('id')->first();
        $this->assertSame(['total' => 6, 'valides' => 2, 'erreurs' => 2, 'doublons' => 2],
            ['total' => $imp->total_lignes, 'valides' => $imp->lignes_valides, 'erreurs' => $imp->lignes_erreur, 'doublons' => $imp->lignes_doublon]);
        $this->assertFalse(Agent::where('matricule', 'NEW1')->exists(), 'L\'analyse ne doit rien écrire.');
        $this->actingAs($drh)->get(route('imports.show', $imp))->assertOk()->assertSee('Service inconnu');

        $this->actingAs($drh)->post(route('imports.confirmer', $imp))->assertRedirect();
        $this->assertSame('termine', $imp->fresh()->statut);
        $this->assertSame(2, $imp->fresh()->lignes_importees);
        $this->assertTrue(Agent::where('matricule', 'NEW1')->where('nom', 'NOUVEAU')->exists());
        $this->assertSame($this->service2->direction_id, Agent::where('matricule', 'NEW2')->value('direction_id'));
        $this->assertSame('DEJA', Agent::where('matricule', 'EXIST1')->value('nom'), 'Aucune donnée existante ne doit être modifiée.');
        // Une seconde confirmation est refusée
        $this->actingAs($drh)->post(route('imports.confirmer', $imp))->assertSessionHas('erreur');
    }

    public function test_un_faux_classeur_est_refuse(): void
    {
        // 2.5.1 : un fichier HTML renommé en .xlsx n'est jamais lu par le lecteur HTML de PhpSpreadsheet
        $f = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($f, '<html><body><table><tr><td>matricule</td></tr><tr><td><img src="http://exemple.invalid/x.png"></td></tr></table></body></html>');
        $lire = new \ReflectionMethod(ImportService::class, 'lire');
        $this->expectException(RegleMetierException::class);
        $lire->invoke(app(ImportService::class), new UploadedFile($f, 'agents.xlsx', null, null, true));
    }

    public function test_import_colonnes_obligatoires_absentes(): void
    {
        $this->actingAs($this->utilisateur('responsable_drh'))->post('/imports', ['type' => 'agents', 'fichier' => $this->csv("nom;prenom\nA;B\n")])
            ->assertSessionHas('erreur');
        $this->assertSame(0, Import::count());
    }

    public function test_import_csv_windows_1252_et_virgule(): void
    {
        $csv = mb_convert_encoding("matricule,nom,prenom\nW1,ÉLÈVE,Hélène\n", 'Windows-1252', 'UTF-8');
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->post('/imports', ['type' => 'agents', 'fichier' => $this->csv($csv)]);
        $this->actingAs($drh)->post(route('imports.confirmer', Import::latest('id')->first()));
        $this->assertSame('ÉLÈVE', Agent::where('matricule', 'W1')->value('nom'));
    }

    public function test_import_excel(): void
    {
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray([['matricule', 'nom', 'prenom'], ['XL1', 'TABLEUR', 'Ligne']]);
        $f = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        (new Xlsx($ss))->save($f);
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->post('/imports', ['type' => 'agents', 'fichier' => new UploadedFile($f, 'agents.xlsx', null, null, true)])->assertRedirect();
        $this->actingAs($drh)->post(route('imports.confirmer', Import::latest('id')->first()));
        $this->assertTrue(Agent::where('matricule', 'XL1')->exists());
    }

    public function test_import_historique_excel_avec_de_vraies_cellules_date(): void
    {
        Agent::create(['matricule' => 'XD1', 'nom' => 'A', 'prenom' => 'B']);
        $ss = new Spreadsheet;
        $f = $ss->getActiveSheet();
        $f->fromArray([['matricule', 'intitule', 'type', 'date_debut', 'date_fin', 'resultat', 'duree_heures'],
            ['XD1', 'Dates Excel', 'interne', ExcelDate::PHPToExcel('2025-03-10'), ExcelDate::PHPToExcel('2025-03-12'), 'achevee', 21]]);
        $f->getStyle('D2:E2')->getNumberFormat()->setFormatCode('dd/mm/yyyy'); // saisie naturelle d'une date dans Excel
        $chemin = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        (new Xlsx($ss))->save($chemin);
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->post('/imports', ['type' => 'historique', 'fichier' => new UploadedFile($chemin, 'historique.xlsx', null, null, true)])->assertRedirect();
        $imp = Import::latest('id')->first();
        $this->assertSame(1, $imp->lignes_valides, 'Une vraie date Excel (nombre + format date) est reconnue.');
        $this->actingAs($drh)->post(route('imports.confirmer', $imp));
        $this->assertSame('2025-03-10', SessionFormation::firstOrFail()->date_debut->toDateString());
        $this->assertEquals(21, SessionFormation::firstOrFail()->duree_heures, 'Un nombre ordinaire n\'est pas pris pour une date.');
    }

    public function test_import_historique_des_formations(): void
    {
        Agent::create(['matricule' => 'H1', 'nom' => 'A', 'prenom' => 'B']);
        Agent::create(['matricule' => 'H2', 'nom' => 'C', 'prenom' => 'D']);
        $drh = $this->utilisateur('responsable_drh');
        $csv = "matricule;intitule;type;date_debut;date_fin;resultat;organisme\n"
            ."H1;Formation passée;externe;10/03/2024;12/03/2024;achevee;Organisme historique\n"
            ."H2;Formation passée;externe;10/03/2024;12/03/2024;non_achevee;Organisme historique\n"
            ."H9;Autre;interne;01/01/2024;01/01/2024;achevee;\n"     // agent inconnu
            ."H1;Mauvaise date;interne;31/02/2024;01/03/2024;achevee;\n";
        $this->actingAs($drh)->post('/imports', ['type' => 'historique', 'fichier' => $this->csv($csv)]);
        $imp = Import::latest('id')->first();
        $this->assertSame(2, $imp->lignes_valides);
        $this->assertSame(2, $imp->lignes_erreur);
        $this->actingAs($drh)->post(route('imports.confirmer', $imp));
        $this->assertSame(2, Inscription::count(), 'Les deux lignes d\'une même formation sont regroupées dans une session.');
        $this->assertSame(1, SessionFormation::count());
        $this->assertSame('achevee', Inscription::whereHas('agent', fn ($q) => $q->where('matricule', 'H1'))->value('resultat'));
        // Re-import : doublons détectés
        $this->actingAs($drh)->post('/imports', ['type' => 'historique', 'fichier' => $this->csv($csv)]);
        $this->assertSame(2, Import::latest('id')->first()->lignes_doublon);
    }

    public function test_modele_d_import_telechargeable(): void
    {
        $r = $this->actingAs($this->utilisateur('responsable_drh'))->get('/imports/modele/agents')->assertOk();
        $this->assertStringContainsString('matricule;nom;prenom', $r->streamedContent());
    }

    public function test_import_de_la_structure_directions_et_services(): void
    {
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->setTitle('Lisez-moi')->fromArray([['Texte d\'explication']]);
        $ss->createSheet()->setTitle('Structure à importer')->fromArray([
            ['direction_code', 'direction_nom', 'service_code', 'service_nom', 'origine', 'remarque'],
            ['DSI', 'Direction des Systèmes d\'Information', 'DSI-DIR', 'Direction des Systèmes d\'Information — Direction', 'Créé', ''],
            ['DSI', 'Direction des Systèmes d\'Information', 'dsi-01', 'Division  Exploitation et Support', 'Déduite', 'à vérifier'],
            ['DSI', 'Autre nom', 'DSI-02', 'Division Réseau', '', ''],
            ['DSI', 'Direction des Systèmes d\'Information', 'DSI-01', 'Doublon', '', ''],
            ['T-DIR', 'Nom différent', 'T-S9', 'Service existant renommé ?', '', ''],
            ['T-DIR', 'Nom différent', 'T-S1', 'Service test 1 renommé', '', ''],
            ['', 'Sans code', 'X-1', 'X', '', ''],
        ]);
        $ss->setActiveSheetIndex(1); // la feuille lue est celle ouverte à l'enregistrement
        $f = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        (new Xlsx($ss))->save($f);
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($this->utilisateur('chef_service'))->post('/imports', ['type' => 'structure', 'fichier' => new UploadedFile($f, 's.xlsx', null, null, true)])->assertForbidden();
        $this->actingAs($drh)->get('/imports')->assertSee('Structure de l&#039;INPS (directions et services)', false);
        $this->actingAs($drh)->post('/imports', ['type' => 'structure', 'fichier' => new UploadedFile($f, 's.xlsx', null, null, true)])->assertRedirect();
        $i = Import::latest('id')->firstOrFail();
        $this->assertSame(['valide' => 3, 'erreur' => 2, 'doublon' => 2],
            ['valide' => $i->lignes_valides, 'erreur' => $i->lignes_erreur, 'doublon' => $i->lignes_doublon]);
        $this->assertDatabaseMissing('directions', ['code' => 'DSI']); // rien n'est écrit avant confirmation
        // Confirmation réservée aux mêmes droits que l'analyse
        $r = Role::create(['code' => 'import_seul', 'nom' => 'Import seul']);
        $r->permissions()->sync(Permission::where('code', 'agents.importer')->pluck('id'));
        $this->actingAs($this->utilisateur('import_seul'))->post(route('imports.confirmer', $i))->assertForbidden();
        $this->actingAs($drh)->post(route('imports.confirmer', $i))->assertSessionHas('succes');
        $dsi = Direction::where('code', 'DSI')->firstOrFail();
        $this->assertSame(['DSI-01', 'DSI-DIR'], $dsi->services()->orderBy('code')->pluck('code')->all());
        $this->assertSame('Division Exploitation et Support', Service::where('code', 'DSI-01')->value('nom'), 'Espaces normalisés, code en majuscules.');
        $this->assertSame('Direction de test', Direction::where('code', 'T-DIR')->value('nom'), 'Une direction existante n\'est jamais renommée.');
        $this->assertSame('Service test 1', $this->service1->fresh()->nom);
        $this->assertEquals(['T-DIR — Direction de test' => [$this->service1->id => 'Service test 1', $this->service2->id => 'Service test 2', Service::where('code', 'T-S9')->value('id') => 'Service existant renommé ?']],
            array_intersect_key(Service::optionsParDirection(), ['T-DIR — Direction de test' => 1]));
    }

    /** 29/10/2026 : extractions du système du personnel reconnues telles quelles (données fictives ici). */
    public function test_import_de_la_liste_des_directions_puis_du_personnel_tels_qu_extraits(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray([
            ['UA_CODE', 'DESIGNATION', 'SIGLE'],
            [9010000, 'Direction fictive des essais', 'DFE'],
            [9020000, 'Direction régionale fictive', 'DR FICTIVE'],
            [9010000, 'Direction fictive des essais', 'DFE'],   // doublon dans le fichier
            ['T-DIR', 'Direction de test', 'DT'],              // déjà présente : jamais modifiée
            ['', 'Sans code', ''],                             // erreur
        ]);
        $f = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        (new Xlsx($ss))->save($f);
        $this->actingAs($drh)->post('/imports', ['type' => 'structure', 'fichier' => new UploadedFile($f, 'v_direction.xlsx', null, null, true)])->assertRedirect();
        $i = Import::latest('id')->firstOrFail();
        $this->assertSame(['valide' => 2, 'erreur' => 1, 'doublon' => 2], ['valide' => $i->lignes_valides, 'erreur' => $i->lignes_erreur, 'doublon' => $i->lignes_doublon]);
        $this->actingAs($drh)->post(route('imports.confirmer', $i))->assertSessionHas('succes');
        $this->assertSame('DR FICTIVE', Direction::where('code', '9020000')->value('sigle'));
        $this->assertSame(0, Direction::where('code', '9010000')->firstOrFail()->services()->count(), 'Aucun service inventé.');
        $this->assertNull(Direction::where('code', 'T-DIR')->value('sigle'), 'Une direction existante n\'est pas modifiée.');

        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray([
            ['MATRICULE', 'NOM', 'PRENOM', 'DATE_EMBAUCHE', 'ETAT_EMPLOYE', 'NO_INPS', 'UACODE_DIRECTION', 'DIRECTION', 'FONCTION', 'NO_COMPTE', 'BANQUE'],
            [90001, 'FICTIF', 'Premier', '01/01/2000', 'ACTIF', '00000000000', 9010000, 'Direction fictive des essais', 'Chef de division', 'COMPTE-FICTIF-1', 'BANQUE-FICTIVE'],
            [90002, 'FICTIF', 'Second', '01/01/2000', 'ACTIF', '00000000001', 9999999, 'Inconnue', 'Agent', 'COMPTE-FICTIF-2', 'BANQUE-FICTIVE'],
        ]);
        (new Xlsx($ss))->save($f);
        $this->actingAs($drh)->post('/imports', ['type' => 'agents', 'fichier' => new UploadedFile($f, 'v_personnel.xlsx', null, null, true)])->assertRedirect();
        $i = Import::latest('id')->firstOrFail();
        $this->assertSame([1, 1], [$i->lignes_valides, $i->lignes_erreur]);
        foreach ($i->lignes as $l) {
            $this->assertSame([], array_intersect(array_keys($l->donnees), ['no_compte', 'banque', 'no_inps', 'date_embauche']), 'Les colonnes inutiles ne sont jamais enregistrées.');
        }
        $this->actingAs($drh)->post(route('imports.confirmer', $i))->assertSessionHas('succes');
        $a = Agent::where('matricule', '90001')->firstOrFail();
        $this->assertSame(['Premier', 'Chef de division', Direction::where('code', '9010000')->value('id')], [$a->prenom, $a->fonction, $a->direction_id]);
        $this->assertStringNotContainsString('COMPTE-FICTIF', json_encode(DB::table('import_lignes')->get()));
    }

    public function test_le_chef_de_service_ne_voit_que_son_service_dans_le_personnel(): void
    {
        Agent::create(['matricule' => 'MIEN1', 'nom' => 'MIEN', 'prenom' => 'A', 'service_id' => $this->service1->id]);
        Agent::create(['matricule' => 'AUTRE1', 'nom' => 'AUTRE', 'prenom' => 'B', 'service_id' => $this->service2->id]);
        $chef = $this->utilisateur('chef_service', $this->service1);
        $this->actingAs($chef)->get('/agents?q=AUTRE')->assertOk()->assertDontSee('AUTRE1');
        $this->actingAs($chef)->get('/agents?service_id='.$this->service2->id)->assertOk()->assertDontSee('AUTRE1')->assertSee('MIEN1');
        $this->actingAs($chef)->get('/agents')->assertSee('Agents de votre service : Service test 1')
            ->assertDontSee('Service test 2')->assertDontSee('name="direction_id"', false);
        $this->actingAs($chef)->get(route('besoins.create'))->assertSee('MIEN1')->assertDontSee('AUTRE1');
        $this->actingAs($this->utilisateur('responsable_drh'))->get('/agents')->assertSee('<optgroup label="Direction de test">', false);
    }
}
