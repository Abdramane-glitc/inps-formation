<?php

namespace App\Models;

use App\Enums\StatutInscription;
use App\Enums\StatutSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class SessionFormation extends Model
{
    protected $table = 'sessions_formation';

    protected $fillable = [
        'date_debut', 'date_fin', 'heure_debut', 'heure_fin', 'duree_heures', 'lieu', 'places',
        'liste_attente_active', 'formateur_id', 'bilan_pedagogique',
    ];

    /** Statuts d'inscription qui occupent une place. */
    public const STATUTS_OCCUPANT_PLACE = ['inscrit', 'convoque', 'confirme'];

    protected function casts(): array
    {
        return [
            'statut' => StatutSession::class,
            'date_debut' => 'date',
            'date_fin' => 'date',
            'places' => 'integer',
            'liste_attente_active' => 'boolean',
            'presences_validees_le' => 'datetime',
            'duree_heures' => 'decimal:1',
            'evaluation_froid_mois' => 'integer',
            'rapport_redige_le' => 'datetime',
            'convoquee_le' => 'datetime',
            'convocation_details' => 'array',
        ];
    }

    /** Délais possibles de l'évaluation à froid, en mois après la fin de la formation (1.9.0). */
    public const DELAIS_FROID = [3, 4, 5, 6];

    public const DELAI_FROID_DEFAUT = 6;

    public function delaiFroid(): int
    {
        return in_array($this->evaluation_froid_mois, self::DELAIS_FROID, true) ? $this->evaluation_froid_mois : self::DELAI_FROID_DEFAUT;
    }

    /** Date à partir de laquelle le responsable de structure évalue la mise en pratique (fin de la formation + délai). */
    public function dateEvaluationFroid(): ?Carbon
    {
        return $this->date_fin?->copy()->addMonthsNoOverflow($this->delaiFroid());
    }

    public function rapportRedige(): bool
    {
        return $this->rapport_redige_le !== null;
    }

    public function redacteurRapport(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rapport_redige_par');
    }

    public function attestationsIntervenants(): HasMany
    {
        return $this->hasMany(AttestationIntervenant::class, 'session_id');
    }

    /** Dates passées sans « Formation terminée » : il reste à cocher les participants (1.7.2). */
    public function estATerminer(): bool
    {
        return $this->statut === StatutSession::Planifiee && $this->date_fin && $this->date_fin->lt(today());
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(ActionFormation::class, 'action_id')->withTrashed(); // historique lisible même si la formation est à la corbeille
    }

    /** Ancien formateur unique (avant 1.8.0), conservé en lecture : utiliser formateurs(). */
    public function formateur(): BelongsTo
    {
        return $this->belongsTo(Formateur::class);
    }

    /** Formateurs de la session : internes et externes (1.8.0). */
    public function formateurs(): BelongsToMany
    {
        return $this->belongsToMany(Formateur::class, 'session_formateur', 'session_id', 'formateur_id')->withTimestamps()->orderByDesc('formateurs.type')->orderBy('formateurs.nom'); // internes d'abord
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'session_id');
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class, 'session_id')->orderBy('date')->orderBy('heure_debut');
    }

    public function changements(): HasMany
    {
        return $this->hasMany(SessionChangement::class, 'session_id')->latest('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function placesOccupees(): int
    {
        return $this->inscriptions()->whereIn('statut', self::STATUTS_OCCUPANT_PLACE)->count();
    }

    public function placesRestantes(): int
    {
        return max(0, $this->places - $this->placesOccupees());
    }

    public function nombreJours(): int
    {
        return $this->date_debut->diffInDays($this->date_fin) + 1;
    }

    public function presencesValidees(): bool
    {
        return $this->presences_validees_le !== null;
    }

    public function estCloturable(): bool
    {
        return ! in_array($this->statut, [StatutSession::Annulee, StatutSession::Terminee], true);
    }

    public function participantsActifs()
    {
        return $this->inscriptions()->whereIn('statut', [StatutInscription::Inscrit->value, StatutInscription::Convoque->value, StatutInscription::Confirme->value]);
    }
}
