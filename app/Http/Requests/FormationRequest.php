<?php

namespace App\Http\Requests;

use App\Models\Formateur;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire unique « Formation » : ce qui est enseigné (action) et quand / où (session).
 * Les champs de dates sont facultatifs à la modification d'une formation terminée ou annulée
 * (ils ne sont alors plus affichés) ; ils sont obligatoires à la création.
 */
class FormationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->aPermission('actions.gerer');
    }

    public function rules(): array
    {
        $dates = $this->isMethod('post') || $this->has('date_debut') ? 'required' : 'sometimes';

        return [
            'intitule' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'domaine_id' => ['nullable', 'integer', 'exists:domaines,id'],
            'objectifs_pedagogiques' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'in:interne,externe,mixte'],
            'modalite' => ['nullable', 'in:presentiel,distanciel,hybride'],
            'organisme_id' => ['nullable', 'required_if:type,externe,mixte', 'integer', 'exists:organismes,id'],
            'public_cible' => ['nullable', 'string', 'max:255'],

            'date_debut' => [$dates, 'date'],
            'date_fin' => [$dates, 'date', 'after_or_equal:date_debut'],
            'heure_debut' => ['nullable', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i', 'after:heure_debut'],
            'duree_heures' => ['nullable', 'numeric', 'min:0.5', 'max:2000'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'formateurs' => ['sometimes', 'array', 'max:20'],
            'formateurs.*' => ['integer', 'distinct', 'exists:formateurs,id'],
            'motif' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['date_debut' => 'date de début', 'date_fin' => 'date de fin', 'organisme_id' => 'organisme'];
    }

    public function messages(): array
    {
        return ['organisme_id.required_if' => 'L\'organisme prestataire est obligatoire pour une formation externe ou mixte.'];
    }

    /** La liste des formateurs fait partie du formulaire : tout décocher la vide (1.8.0). */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('formateurs_envoyes') && ! $this->has('formateurs')) {
            $this->merge(['formateurs' => []]);
        }
    }

    /** Le type de formation doit correspondre aux formateurs choisis (un interne et un externe : « Mixte »). */
    public function withValidator($v): void
    {
        $v->after(function ($v) {
            $ids = (array) $this->input('formateurs', []);
            if ($ids === [] || $v->errors()->hasAny(['type', 'formateurs', 'formateurs.*'])) {
                return;
            }
            $types = Formateur::whereIn('id', $ids)->pluck('type')->unique();
            $type = $this->input('type');
            if ($type === 'interne' && $types->contains('externe')) {
                $v->errors()->add('formateurs', 'Un formateur externe est choisi : choisissez le type « Externe » ou « Mixte ».');
            } elseif ($type === 'externe' && $types->contains('interne')) {
                $v->errors()->add('formateurs', 'Un formateur interne est choisi : choisissez le type « Interne » ou « Mixte ».');
            }
        });
    }
}
