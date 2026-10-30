<?php

namespace Tests\Feature;

use App\Models\Parametre;
use Tests\TestCase;

/** 30/10/2026 : les paramètres ne sont plus marqués « À valider par l'INPS » ; ils se renseignent au besoin. */
class ParametresSansValidationTest extends TestCase
{
    public function test_ecran_parametres_sans_mention_a_valider(): void
    {
        $admin = $this->utilisateur('responsable_drh');
        $this->actingAs($admin)->get(route('admin.parametres.index'))->assertOk()
            ->assertDontSee('À valider par l', false)->assertDontSee('(à valider)')
            ->assertSee('Durées de conservation')->assertSee('Renseignez ces réglages au besoin');
        $this->assertSame(0, Parametre::where('a_valider_inps', true)->count());
    }

    public function test_la_migration_met_a_jour_une_installation_existante_sans_toucher_aux_valeurs(): void
    {
        Parametre::where('cle', 'institution.adresse')->update(['aide' => 'À renseigner par l\'INPS.', 'a_valider_inps' => true, 'valeur' => 'BP 1234 Bamako']);
        Parametre::where('cle', 'institution.direction_rh')->update(['aide' => 'Texte modifié à la main', 'a_valider_inps' => true]);
        $migration = require base_path('database/migrations/2026_11_05_002600_parametres_sans_validation_inps.php');
        $migration->up();
        $adresse = Parametre::where('cle', 'institution.adresse')->first();
        $this->assertFalse($adresse->a_valider_inps);
        $this->assertSame('BP 1234 Bamako', $adresse->valeur, 'La valeur saisie ne change pas.');
        $this->assertStringStartsWith('Facultatif', $adresse->aide);
        $this->assertSame('Texte modifié à la main', Parametre::where('cle', 'institution.direction_rh')->value('aide'), 'Une aide déjà modifiée est gardée.');
    }
}
