<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Formateur extends Model
{
    protected $fillable = ['type', 'agent_id', 'organisme_id', 'nom', 'prenom', 'email', 'telephone', 'domaines_expertise', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function organisme(): BelongsTo
    {
        return $this->belongsTo(Organisme::class);
    }

    /** Sessions animées par ce formateur (plusieurs formateurs par session depuis la 1.8.0). */
    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(SessionFormation::class, 'session_formateur', 'formateur_id', 'session_id')->withTimestamps();
    }

    public function estExterne(): bool
    {
        return $this->type === 'externe';
    }

    /** Nom suivi de « interne » ou de l'organisme du formateur externe. */
    public function libelleComplet(): string
    {
        return $this->nom_complet.' ('.($this->estExterne() ? 'externe'.($this->organisme ? ' — '.$this->organisme->raison_sociale : '') : 'interne').')';
    }

    public function getNomCompletAttribute(): string
    {
        return trim(mb_strtoupper($this->nom).' '.$this->prenom);
    }
}
