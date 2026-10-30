<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Formation diplômante d'un agent (2.5.0) : licence, master, doctorat… suivie dans un établissement.
 * Distincte des formations organisées par l'INPS (pas de sessions, de présences ni d'attestation INPS).
 * Saisie par la RH ; aucun montant n'est enregistré, seulement le type de prise en charge.
 */
class FormationDiplomante extends Model
{
    protected $table = 'formations_diplomantes';

    protected $fillable = ['agent_id', 'niveau', 'intitule', 'etablissement', 'ville', 'pays', 'annee_debut', 'duree_annees',
        'mode', 'prise_en_charge', 'situation', 'date_obtention', 'observations'];

    public const NIVEAUX = ['licence' => 'Licence', 'master' => 'Master', 'doctorat' => 'Doctorat', 'bts_dut' => 'BTS / DUT',
        'certificat' => 'Certificat professionnel', 'autre' => 'Autre diplôme'];

    public const MODES = ['presentiel' => 'Présentiel', 'cours_du_soir' => 'Cours du soir', 'distance' => 'À distance'];

    public const PRISES_EN_CHARGE = ['inps_totale' => 'Totale par l\'INPS', 'inps_partielle' => 'Partielle par l\'INPS',
        'agent' => 'À ses frais', 'bourse' => 'Bourse ou partenaire'];

    public const SITUATIONS = ['en_cours' => 'En cours', 'obtenu' => 'Diplôme obtenu', 'ajourne' => 'Ajourné', 'abandonne' => 'Abandonné'];

    /** Couleur du badge de situation (composant x-badge). */
    public const COULEURS = ['en_cours' => 'bleu', 'obtenu' => 'vert', 'ajourne' => 'orange', 'abandonne' => 'gris'];

    protected function casts(): array
    {
        return ['date_obtention' => 'date', 'annee_debut' => 'integer', 'duree_annees' => 'integer'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** « 2026-2027 » */
    public function getAnneeAcademiqueAttribute(): string
    {
        return $this->annee_debut.'-'.($this->annee_debut + 1);
    }

    public function libelle(string $champ): string
    {
        $listes = ['niveau' => self::NIVEAUX, 'mode' => self::MODES, 'prise_en_charge' => self::PRISES_EN_CHARGE, 'situation' => self::SITUATIONS];

        return $listes[$champ][$this->{$champ}] ?? (string) $this->{$champ};
    }
}
