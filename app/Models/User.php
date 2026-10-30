<?php

namespace App\Models;

use App\Services\AccesAgentService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Compte utilisateur. Les droits sont obtenus exclusivement via les rôles attribués
 * (refus par défaut : aucune permission sans rôle).
 */
class User extends Authenticatable
{
    use Notifiable;

    /** Champs modifiables en masse. Les rôles et le statut actif ne le sont jamais. */
    protected $fillable = ['name', 'email', 'password', 'agent_id', 'service_id', 'direction_id'];

    protected $hidden = ['password', 'remember_token', 'code_activation'];

    /** @var array<string,bool>|null cache mémoire des permissions de la requête */
    protected ?array $permissionsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
            'mot_de_passe_modifie_le' => 'datetime',
            'derniere_connexion_le' => 'datetime',
            'code_activation' => 'encrypted',
            'code_activation_expire_le' => 'datetime',
            'active_le' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['attribue_le', 'attribue_par']);
    }

    /** Code d'activation encore utilisable (2.4.0). */
    public function aCodeValide(): bool
    {
        return $this->code_activation !== null && $this->code_activation_expire_le?->isFuture() && $this->code_activation_essais < AccesAgentService::ESSAIS_MAX;
    }

    /** « 482 913 » */
    public function getCodeActivationAfficheAttribute(): ?string
    {
        return $this->code_activation ? substr($this->code_activation, 0, 3).' '.substr($this->code_activation, 3) : null;
    }

    /** Identifiant affiché : l'e-mail, ou le matricule pour un compte d'agent sans e-mail (2.3.0). */
    public function getIdentifiantAttribute(): string
    {
        return $this->email ?? ($this->agent ? 'matricule '.$this->agent->matricule : 'compte n° '.$this->id);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    /** Écarts de droits par rapport au profil (2.0.0) : pivot « accorde » vrai (ajouté) ou faux (retiré). */
    public function permissionsPersonnalisees(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')->withPivot(['accorde', 'attribue_par'])->withTimestamps();
    }

    /** Droits du profil (rôles), avant personnalisation. @return array<string,bool> */
    public function permissionsDuProfil(): array
    {
        return Permission::query()
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('role_user', 'role_user.role_id', '=', 'permission_role.role_id')
            ->where('role_user.user_id', $this->id)
            ->pluck('permissions.code')
            ->mapWithKeys(fn ($c) => [$c => true])
            ->all();
    }

    /** Droits ajoutés (true) ou retirés (false) au profil pour ce compte (2.0.0). @return array<string,bool> */
    public function droitsPersonnalises(): array
    {
        return Permission::query()->join('permission_user', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('permission_user.user_id', $this->id)->pluck('permission_user.accorde', 'permissions.code')
            ->map(fn ($v) => (bool) $v)->all();
    }

    /** Droits effectifs : ceux du profil, plus les droits ajoutés, moins les droits retirés. @return array<string,bool> */
    public function permissionCodes(): array
    {
        if ($this->permissionsCache === null) {
            $codes = $this->permissionsDuProfil();
            foreach ($this->droitsPersonnalises() as $code => $accorde) {
                if ($accorde) {
                    $codes[$code] = true;
                } else {
                    unset($codes[$code]);
                }
            }
            $this->permissionsCache = $codes;
        }

        return $this->permissionsCache;
    }

    public function viderCachePermissions(): void
    {
        $this->permissionsCache = null;
        $this->unsetRelation('roles');
    }

    public function aPermission(string $code): bool
    {
        if (! $this->actif) {
            return false;
        }

        return isset($this->permissionCodes()[$code]);
    }

    public function aUneDesPermissions(string ...$codes): bool
    {
        foreach ($codes as $c) {
            if ($this->aPermission($c)) {
                return true;
            }
        }

        return false;
    }

    public function aRole(string $code): bool
    {
        return $this->roles->contains('code', $code);
    }
}
