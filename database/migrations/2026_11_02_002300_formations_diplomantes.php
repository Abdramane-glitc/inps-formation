<?php

use App\Support\CataloguePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2.5.0 (demandes de l'INPS du 28/10/2026) :
 *  - formations diplômantes (licence, master, doctorat…) suivies par les agents, saisies par la RH, sans montants ;
 *  - le profil « Chef de service » s'appelle désormais « Responsable de structure » (code technique chef_service inchangé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations_diplomantes', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 30)->unique();               // DIP-AAAA-NNNN
            $t->foreignId('agent_id')->constrained('agents')->restrictOnDelete();
            $t->string('niveau', 20);                             // licence, master, doctorat, bts_dut, certificat, autre
            $t->string('intitule', 255);                          // spécialité / intitulé du diplôme
            $t->string('etablissement', 255);
            $t->string('ville', 120)->nullable();
            $t->string('pays', 120)->nullable();
            $t->unsignedSmallInteger('annee_debut');              // année académique 2026-2027 → 2026
            $t->unsignedTinyInteger('duree_annees')->nullable();
            $t->string('mode', 20);                               // presentiel, cours_du_soir, distance
            $t->string('prise_en_charge', 20);                    // inps_totale, inps_partielle, agent, bourse
            $t->string('situation', 20)->default('en_cours');     // en_cours, obtenu, ajourne, abandonne
            $t->date('date_obtention')->nullable();
            $t->text('observations')->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['annee_debut', 'situation']);
        });

        $maintenant = now();
        [$module, $libelle, $sensible] = CataloguePermissions::PERMISSIONS['diplomantes.gerer'];
        DB::table('permissions')->updateOrInsert(['code' => 'diplomantes.gerer'], ['module' => $module, 'libelle' => $libelle, 'sensible' => $sensible, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        $pid = DB::table('permissions')->where('code', 'diplomantes.gerer')->value('id');
        foreach (DB::table('roles')->where('code', 'responsable_drh')->pluck('id') as $rid) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $rid, 'permission_id' => $pid]);
        }

        [$nom, $description] = CataloguePermissions::ROLES['chef_service'];
        $renomme = DB::table('roles')->where('code', 'chef_service')->update(['nom' => $nom, 'description' => $description, 'updated_at' => $maintenant]);

        DB::table('audit_logs')->insert([
            'action' => 'migration.formations_diplomantes',
            'description' => 'Version 2.5.0 : formations diplômantes ; profil « Chef de service » renommé « Responsable de structure ».',
            'contexte' => json_encode(['role_renomme' => $renomme]),
            'created_at' => $maintenant,
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('code', 'diplomantes.gerer')->delete();
        DB::table('roles')->where('code', 'chef_service')->update(['nom' => 'Chef de service']);
        Schema::dropIfExists('formations_diplomantes');
    }
};
