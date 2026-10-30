<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Création sécurisée du premier administrateur technique (aucun compte ni mot de passe par défaut).
 * Le mot de passe est saisi de manière masquée et doit respecter la politique en vigueur.
 */
class CreerAdministrateur extends Command
{
    protected $signature = 'inps:creer-admin {--email=} {--nom=} {--drh : le compte reçoit aussi le rôle « Responsable DRH / formation » (organisation à deux profils)}';

    protected $description = 'Créer un compte administrateur technique (saisie masquée du mot de passe)';

    public function handle(): int
    {
        $role = Role::where('code', 'admin_technique')->first();
        if (! $role) {
            $this->error('Rôle « admin_technique » absent : exécutez d\'abord « php artisan db:seed --class=ReferentielInitialSeeder ».');

            return self::FAILURE;
        }
        $drh = $this->option('drh') ? Role::where('code', 'responsable_drh')->first() : null;
        if ($this->option('drh') && ! $drh) {
            $this->error('Rôle « responsable_drh » absent : exécutez d\'abord « php artisan db:seed ».');

            return self::FAILURE;
        }
        $email = $this->option('email') ?: text('Adresse e-mail professionnelle', required: true);
        $nom = $this->option('nom') ?: text('Nom complet', required: true);
        $mdp = password('Mot de passe (saisie masquée)', required: true);
        $confirmation = password('Confirmation du mot de passe', required: true);

        $v = Validator::make(['email' => $email, 'name' => $nom, 'password' => $mdp, 'password_confirmation' => $confirmation], [
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'name' => ['required', 'string', 'max:190'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($email, $nom, $mdp, $role, $drh) {
            $u = new User(['name' => $nom, 'email' => $email, 'password' => $mdp]);
            $u->actif = true;
            $u->mot_de_passe_modifie_le = now();
            $u->save();
            $u->roles()->attach($role->id, ['attribue_le' => now()]);
            if ($drh) {
                $u->roles()->attach($drh->id, ['attribue_le' => now()]);
            }
            Audit::enregistrer('utilisateur.creation_admin_console', $u, "Création de l'administrateur technique {$email} en console", ['roles' => $u->roles()->pluck('code')->all()]);
        });
        $this->info(($drh ? 'Compte RH (Responsable DRH + administrateur technique) créé : ' : 'Administrateur technique créé : ').$email);

        return self::SUCCESS;
    }
}
