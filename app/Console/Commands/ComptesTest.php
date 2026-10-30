<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comptes de démonstration et d'essai (29/10/2026) : ceux dont l'adresse se termine par « @exemple.invalid »,
 * le domaine réservé aux données fictives (DemoSeeder, essais). Sans option : liste seulement.
 * Avec --desactiver : désactivation (jamais de suppression : l'historique et le journal restent intacts),
 * sessions et jetons « se souvenir de moi » coupés. Refusé s'il ne resterait aucun administrateur actif réel.
 */
class ComptesTest extends Command
{
    public const DOMAINE = '@exemple.invalid';

    protected $signature = 'inps:comptes-test {--desactiver : désactiver les comptes de test actifs}';

    protected $description = 'Lister (ou désactiver avec --desactiver) les comptes de démonstration et d\'essai (@exemple.invalid)';

    public function handle(): int
    {
        $test = User::where('email', 'like', '%'.self::DOMAINE)->orderBy('name')->get();
        $actifs = $test->where('actif', true);
        $this->info($test->count().' compte(s) de test, dont '.$actifs->count().' actif(s).');
        foreach ($test as $u) {
            $this->line('  '.($u->actif ? '[actif]    ' : '[désactivé]').' '.$u->name.' ('.$u->email.')');
        }
        $reels = User::where('actif', true)->where('email', 'not like', '%'.self::DOMAINE)->get();
        $admins = $reels->filter(fn (User $u) => $u->aPermission('utilisateurs.gerer'));
        $this->line('Comptes réels actifs : '.$reels->count().' (dont '.$admins->count().' pouvant gérer les comptes) : '
            .$reels->map(fn ($u) => $u->name)->implode(', '));

        if (! $this->option('desactiver')) {
            $this->line('Liste seulement : rien n\'est modifié. Relancez avec --desactiver pour désactiver les comptes de test actifs.');

            return self::SUCCESS;
        }
        if ($admins->isEmpty()) {
            $this->error('Refusé : aucun compte réel actif ne peut gérer les comptes. Créez d\'abord le vôtre (php artisan inps:creer-admin).');

            return self::FAILURE;
        }
        if ($actifs->isEmpty()) {
            $this->info('Aucun compte de test actif : rien à faire.');

            return self::SUCCESS;
        }
        $ids = $actifs->pluck('id')->all();
        DB::transaction(function () use ($ids) {
            DB::table('users')->whereIn('id', $ids)->update(['actif' => false, 'remember_token' => null, 'updated_at' => now()]);
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $ids)->delete();
            }
            Audit::enregistrer('utilisateur.desactivation_comptes_test', null, count($ids).' compte(s) de test désactivé(s) (adresse '.self::DOMAINE.')', ['ids' => $ids]);
        });
        $this->info(count($ids).' compte(s) de test désactivé(s). Ils restent visibles (désactivés) dans Administration → Utilisateurs.');

        return self::SUCCESS;
    }
}
