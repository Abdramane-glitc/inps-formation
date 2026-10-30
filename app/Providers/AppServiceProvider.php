<?php

namespace App\Providers;

use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\AnneeReference;
use App\Models\AppreciationPrestataire;
use App\Models\Attestation;
use App\Models\AttestationIntervenant;
use App\Models\AuditLog;
use App\Models\BesoinFormation;
use App\Models\ContactOrganisme;
use App\Models\Convention;
use App\Models\Decision;
use App\Models\Direction;
use App\Models\Document;
use App\Models\Domaine;
use App\Models\EnvoiEmail;
use App\Models\EvaluationAcquis;
use App\Models\EvaluationImpact;
use App\Models\EvaluationSatisfaction;
use App\Models\Formateur;
use App\Models\FormationDiplomante;
use App\Models\Import;
use App\Models\ImportLigne;
use App\Models\Inscription;
use App\Models\InscriptionEvenement;
use App\Models\Nomenclature;
use App\Models\Organisme;
use App\Models\Parametre;
use App\Models\Permission;
use App\Models\PlanAnnuel;
use App\Models\PlanLigne;
use App\Models\Presence;
use App\Models\PresenceCorrection;
use App\Models\RappelEnvoye;
use App\Models\Role;
use App\Models\Seance;
use App\Models\Service;
use App\Models\SessionChangement;
use App\Models\SessionFormation;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Protection contre l'affectation massive : seuls les champs $fillable sont acceptés ;
        // en développement, toute tentative sur un champ non autorisé lève une exception.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventLazyLoading(false);

        // Types polymorphes stables en base (indépendants des noms de classes).
        // Les anciens types « plan », « plan_version » (plan annuel retiré en 1.3.0) et « depense » (finances retirées en 1.4.0) ne sont plus chargés.
        Relation::enforceMorphMap([
            'user' => User::class, 'role' => Role::class, 'agent' => Agent::class, 'besoin' => BesoinFormation::class,
            'action' => ActionFormation::class,
            'session' => SessionFormation::class, 'inscription' => Inscription::class, 'organisme' => Organisme::class,
            'document' => Document::class, 'attestation' => Attestation::class, 'import' => Import::class,
            // 1.7.1 : tous les modèles susceptibles d'être cités dans le journal d'audit (référentiels, formateurs, plan…)
            'direction' => Direction::class, 'service' => Service::class, 'domaine' => Domaine::class,
            'annee' => AnneeReference::class, 'nomenclature' => Nomenclature::class, 'parametre' => Parametre::class,
            'formateur' => Formateur::class, 'contact_organisme' => ContactOrganisme::class, 'convention' => Convention::class,
            'appreciation' => AppreciationPrestataire::class, 'permission' => Permission::class, 'decision' => Decision::class,
            'plan_annuel' => PlanAnnuel::class, 'plan_ligne' => PlanLigne::class, 'seance' => Seance::class,
            'session_changement' => SessionChangement::class, 'inscription_evenement' => InscriptionEvenement::class,
            'presence' => Presence::class, 'presence_correction' => PresenceCorrection::class,
            'attestation_intervenant' => AttestationIntervenant::class, 'diplomante' => FormationDiplomante::class,
            'evaluation_satisfaction' => EvaluationSatisfaction::class, 'evaluation_acquis' => EvaluationAcquis::class,
            'evaluation_impact' => EvaluationImpact::class, 'import_ligne' => ImportLigne::class,
            'envoi_email' => EnvoiEmail::class, 'rappel' => RappelEnvoye::class, 'audit_log' => AuditLog::class,
        ]);

        if (config('inps.forcer_https')) {
            URL::forceScheme('https');
        }

        // Politique de mot de passe (longueur paramétrable, complexité, contrôle des fuites optionnel).
        Password::defaults(function () {
            $r = Password::min(config('inps.securite.mot_de_passe_longueur', 12))->letters()->mixedCase()->numbers()->symbols();

            return config('inps.securite.mot_de_passe_compromis') ? $r->uncompromised() : $r;
        });

        // Limitation des tentatives de connexion et de réinitialisation.
        RateLimiter::for('connexion', fn (Request $r) => [
            Limit::perMinute(config('inps.securite.tentatives_connexion', 5))->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(30)->by($r->ip()),
        ]);
        RateLimiter::for('reinitialisation', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        // Activation par code (2.4.0) : en plus des 5 essais par code, 10 essais par minute et par poste
        RateLimiter::for('activation', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));

        // Chaque permission est exposée comme « ability » : @can('rapports.voir'), $user->can(...).
        // Refus par défaut : aucune règle ne donne tous les droits à un profil.
        Gate::after(function (User $user, string $ability, ?bool $resultat) {
            if ($resultat !== null) {
                return $resultat;
            }

            return str_contains($ability, '.') ? $user->aPermission($ability) : null;
        });

        Blade::if('permission', fn (string ...$codes) => auth()->check() && auth()->user()->aUneDesPermissions(...$codes));

        Paginator::defaultView('components.pagination');

        // Courriel de réinitialisation en français (lien à usage unique et durée limitée)
        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — gestion des formations')
            ->greeting('Bonjour,')
            ->line('Une demande de réinitialisation du mot de passe de votre compte a été reçue.')
            ->action('Définir un nouveau mot de passe', url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false)))
            ->line('Ce lien expire dans '.config('auth.passwords.users.expire').' minutes.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message et prévenez l\'administrateur.')
            ->salutation('Direction des Ressources humaines'));
    }
}
