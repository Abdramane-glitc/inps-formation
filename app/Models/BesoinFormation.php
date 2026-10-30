<?php

namespace App\Models;

use App\Enums\StatutBesoin;
use App\Enums\StatutSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BesoinFormation extends Model
{
    use SoftDeletes;

    protected $table = 'besoins_formation';

    /**
     * Champs saisis (formulaire minimal, version 1.4.0). Le statut, l'auteur, la référence, l'année et la date d'envoi
     * sont gérés par le service métier. Les autres colonnes (priorité, domaine, objectifs, coût…) ne sont plus saisies
     * et restent affichées pour les demandes antérieures.
     */
    protected $fillable = [
        'annee', 'service_id', 'intitule', 'contexte',
        'nb_participants', 'periode_souhaitee', // nb_participants : calculé à partir des agents cochés (1.5.0)
        'causes', 'justification_formation', 'resultat_attendu', // analyse du besoin (1.8.0)
        'diag_savoir', 'diag_moyens', 'diag_temps', 'gravite', 'situation_actuelle', 'objectif_vise', 'solutions_essayees', // 2.1.0
    ];

    /** Diagnostic du besoin (2.1.0) : trois questions simples dont l'application tire une conclusion. */
    public const DIAG_SAVOIR = ['non' => 'Non, ils n\'ont pas appris', 'partiel' => 'En partie (certains agents, ou pas tout)', 'oui' => 'Oui, ils savent faire'];

    public const DIAG_OUI_NON = ['oui' => 'Oui', 'non' => 'Non'];

    public const GRAVITES = ['legere' => 'Gêne légère', 'reguliere' => 'Retards ou erreurs réguliers', 'risque' => 'Risque pour les assurés ou pour l\'Institut'];

    public const SOLUTIONS = ['aucune' => 'Rien pour l\'instant', 'collegue' => 'Accompagnement par un collègue', 'procedure' => 'Note ou procédure écrite',
        'organisation' => 'Changement d\'organisation ou d\'outil', 'autre' => 'Autre (à préciser dans le problème constaté)'];

    public function aDiagnostic(): bool
    {
        return $this->diag_savoir !== null;
    }

    /**
     * Conclusion du diagnostic : [couleur, texte]. Une formation règle un manque de savoir-faire ;
     * elle ne règle ni un manque de moyens ni un manque de temps.
     *
     * @return array{0:string,1:string}|null
     */
    public function conclusionDiagnostic(): ?array
    {
        if (! $this->aDiagnostic()) {
            return null;
        }
        if ($this->diag_savoir === 'oui') {
            return ['rouge', 'Les agents savent déjà faire : une formation ne réglera probablement pas ce problème. Cherchez plutôt du côté des moyens, du temps ou de l\'organisation.'];
        }
        if ($this->diag_moyens === 'non' || $this->diag_temps === 'non') {
            return ['orange', 'Il manque du savoir-faire, mais aussi '.($this->diag_moyens === 'non' && $this->diag_temps === 'non' ? 'des moyens et du temps' : ($this->diag_moyens === 'non' ? 'des moyens' : 'du temps')).' : la formation seule ne suffira pas.'];
        }

        return ['vert', 'Il manque du savoir-faire, et les agents ont les moyens et le temps : une formation est la bonne réponse.'];
    }

    /** @return list<string> */
    public function libellesSolutions(): array
    {
        return array_values(array_map(fn ($c) => self::SOLUTIONS[$c], array_filter($this->solutions_essayees ?? [], fn ($c) => isset(self::SOLUTIONS[$c]))));
    }

    /**
     * Origine du problème constaté (1.8.0). Le booléen indique une cause liée aux compétences, la seule
     * qu'une formation peut régler ; les autres appellent plutôt une action d'organisation, de moyens ou d'effectif.
     */
    public const CAUSES = [
        'connaissances' => ['Manque de connaissances ou de savoir-faire', true],
        'nouveaute' => ['Nouvel outil, nouveau logiciel ou nouvelle procédure à maîtriser', true],
        'organisation' => ['Organisation du travail (répartition des tâches, procédure peu claire)', false],
        'moyens' => ['Matériel, logiciel ou équipement insuffisant ou en panne', false],
        'effectif' => ['Effectif insuffisant ou surcharge de travail', false],
        'conditions' => ['Motivation ou conditions de travail', false],
        'autre' => ['Autre cause (à préciser dans le problème constaté)', false],
    ];

    /** @return list<string> libellés des causes cochées */
    public function libellesCauses(): array
    {
        return array_values(array_map(fn ($c) => self::CAUSES[$c][0], array_filter($this->causes ?? [], fn ($c) => isset(self::CAUSES[$c]))));
    }

    /** Vrai si l'analyse indique que le problème ne vient pas d'un manque de compétences (diagnostic 2.1.0 ou causes 1.8.0). */
    public function causesHorsCompetences(): bool
    {
        if ($this->aDiagnostic()) {
            return $this->diag_savoir === 'oui';
        }
        $c = array_filter($this->causes ?? [], fn ($x) => isset(self::CAUSES[$x]));

        return $c !== [] && ! array_filter($c, fn ($x) => self::CAUSES[$x][1]);
    }

    protected function casts(): array
    {
        return [
            'statut' => StatutBesoin::class,
            'causes' => 'array',
            'solutions_essayees' => 'array',
            'soumis_le' => 'datetime',
            'duree_estimee_jours' => 'decimal:1',
            'annee' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function priorite(): BelongsTo
    {
        return $this->belongsTo(Nomenclature::class, 'priorite_id');
    }

    public function typeFormation(): BelongsTo
    {
        return $this->belongsTo(Nomenclature::class, 'type_formation_id');
    }

    public function domaine(): BelongsTo
    {
        return $this->belongsTo(Domaine::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'besoin_agent', 'besoin_id', 'agent_id');
    }

    /** Formation organisée en réponse à cette demande (au plus une). */
    public function formation(): HasOne
    {
        return $this->hasOne(ActionFormation::class, 'besoin_id');
    }

    /** Statuts affichés dans le filtre des demandes (1.7.2) : ceux de la demande, et l'avancement de sa formation. */
    public static function optionsFiltre(): array
    {
        $o = StatutBesoin::options();
        unset($o[StatutBesoin::Brouillon->value]);
        $pos = array_search(StatutBesoin::Organisee->value, array_keys($o), true);

        return array_slice($o, 0, $pos + 1, true) + ['formation_terminee' => 'Formation terminée', 'formation_annulee' => 'Formation annulée'] + array_slice($o, $pos + 1, null, true);
    }

    /**
     * Statut affiché (1.7.2) : une demande « Formation programmée » suit l'avancement de sa formation
     * (« Formation terminée » ou « Formation annulée »). Le statut enregistré de la demande ne change pas.
     *
     * @return array{0: string, 1: string} libellé, couleur
     */
    public function statutAffiche(): array
    {
        if ($this->statut === StatutBesoin::Organisee) {
            $s = $this->formation?->session?->statut;
            if ($s === StatutSession::Terminee) {
                return ['Formation terminée', 'vert'];
            }
            if ($s === StatutSession::Annulee) {
                return ['Formation annulée', 'rouge'];
            }
        }

        return [$this->statut->libelle(), $this->statut->couleur()];
    }

    /** Ligne du plan annuel où la demande est inscrite (1.7.0). */
    public function lignePlan(): HasOne
    {
        return $this->hasOne(PlanLigne::class, 'besoin_id');
    }

    public function decisions(): MorphMany
    {
        return $this->morphMany(Decision::class, 'decidable')->latest('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, [StatutBesoin::Brouillon, StatutBesoin::ACompleter], true);
    }

    /** Cloisonnement par service selon les permissions. */
    public function scopeVisiblesPar(Builder $q, User $user): Builder
    {
        if ($user->aPermission('besoins.voir_tous')) {
            return $q;
        }
        if ($user->aPermission('besoins.voir_service') && $user->service_id) {
            return $q->where('service_id', $user->service_id);
        }

        return $q->whereRaw('1 = 0');
    }
}
