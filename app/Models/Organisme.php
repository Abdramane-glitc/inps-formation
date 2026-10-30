<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Organisme extends Model
{
    protected $fillable = [
        'raison_sociale', 'sigle', 'adresse', 'telephone', 'email', 'site_web',
        'domaines_intervention', 'identifiant_fiscal', 'actif',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ContactOrganisme::class);
    }

    public function conventions(): HasMany
    {
        return $this->hasMany(Convention::class)->orderByDesc('date_debut');
    }

    public function formateurs(): HasMany
    {
        return $this->hasMany(Formateur::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ActionFormation::class);
    }

    public function appreciations(): HasMany
    {
        return $this->hasMany(AppreciationPrestataire::class)->latest();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
