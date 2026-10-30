<?php

namespace Tests\Feature;

use App\Models\ActionFormation;
use App\Models\Document;
use App\Services\BesoinService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Documents joints : dépôt sécurisé, téléchargement autorisé, confidentialité, cloisonnement. */
class DocumentTest extends TestCase
{
    private function action(): ActionFormation
    {
        return $this->nouvelleFormation($this->utilisateur('responsable_drh'), ['intitule' => 'Formation documents', 'places' => 5])->action;
    }

    private function pdf(): UploadedFile
    {
        $f = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($f, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");

        return new UploadedFile($f, 'programme.pdf', 'application/pdf', null, true);
    }

    public function test_depot_securise_et_telechargement_autorise(): void
    {
        Storage::fake('prive');
        $drh = $this->utilisateur('responsable_drh');
        $a = $this->action();
        $this->actingAs($drh)->post(route('documents.store', ['action', $a->id]), ['fichier' => $this->pdf(), 'titre' => 'Programme'])->assertSessionHas('succes');
        $d = Document::firstOrFail();
        $this->assertMatchesRegularExpression('#^documents/\d{4}/\d{2}/[0-9a-f-]{36}\.pdf$#', $d->chemin, 'Nom de stockage aléatoire.');
        $this->assertStringNotContainsString('programme', $d->chemin);
        Storage::disk('prive')->assertExists($d->chemin);
        $this->assertSame(64, strlen($d->sha256));
        $this->actingAs($drh)->get(route('documents.telecharger', $d))->assertOk()->assertDownload('programme.pdf');
        $this->actingAs($this->utilisateur('agent'))->get(route('documents.telecharger', $d))->assertForbidden();
        auth()->logout();
        $this->get(route('documents.telecharger', $d))->assertRedirect(route('login'));
        $this->assertFileDoesNotExist(public_path('storage/'.$d->chemin));
    }

    public function test_fichier_deguise_ou_type_interdit_refuse(): void
    {
        Storage::fake('prive');
        $drh = $this->utilisateur('responsable_drh');
        $a = $this->action();
        $f = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($f, "<?php echo 'malveillant'; ?>");
        $this->actingAs($drh)->post(route('documents.store', ['action', $a->id]), ['fichier' => new UploadedFile($f, 'faux.pdf', 'application/pdf', null, true)])->assertSessionHas('erreur');
        $this->actingAs($drh)->post(route('documents.store', ['action', $a->id]), ['fichier' => new UploadedFile($f, 'script.php', 'text/plain', null, true)])->assertSessionHasErrors('fichier');
        $this->assertSame(0, Document::count());
        $this->assertSame([], Storage::disk('prive')->allFiles());
    }

    public function test_document_confidentiel_reserve(): void
    {
        Storage::fake('prive');
        $drh = $this->utilisateur('responsable_drh');
        $a = $this->action();
        $this->actingAs($drh)->post(route('documents.store', ['action', $a->id]), ['fichier' => $this->pdf(), 'confidentiel' => 1]);
        $d = Document::firstOrFail();
        $this->assertTrue($d->confidentiel);
        $this->actingAs($this->utilisateur('auditeur'))->get(route('documents.telecharger', $d))->assertForbidden(); // voit l'action mais pas le confidentiel
        $this->actingAs($drh)->get(route('documents.telecharger', $d))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.telechargement_confidentiel']);
        // Un utilisateur sans « documents.confidentiels » ne peut pas en déposer
        $this->actingAs($this->utilisateur('chef_service'))->post(route('documents.store', ['action', $a->id]), ['fichier' => $this->pdf(), 'confidentiel' => 1])->assertForbidden();
    }

    public function test_document_d_un_besoin_d_un_autre_service_inaccessible(): void
    {
        Storage::fake('prive');
        $chef1 = $this->utilisateur('chef_service', $this->service1);
        $chef2 = $this->utilisateur('chef_service', $this->service2);
        $b = app(BesoinService::class)->creer(['intitule' => 'X', 'contexte' => 'Y'], $chef1);
        // Le service demandeur joint un justificatif après l'envoi de la demande
        $this->actingAs($chef1)->post(route('documents.store', ['besoin', $b->id]), ['fichier' => $this->pdf()])->assertSessionHas('succes');
        $d = Document::firstOrFail();
        $this->actingAs($chef2)->get(route('documents.telecharger', $d))->assertForbidden();
        $this->actingAs($chef2)->post(route('documents.store', ['besoin', $b->id]), ['fichier' => $this->pdf()])->assertForbidden();
    }
}
