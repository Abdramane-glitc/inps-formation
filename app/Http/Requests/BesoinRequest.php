<?php

namespace App\Http\Requests;

use App\Models\BesoinFormation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Demande de formation : intitulé, analyse du besoin (problème constaté ; diagnostic savoir-faire / moyens / temps ;
 * gravité ; situation actuelle et objectif ; solutions essayées ; pourquoi une formation ; résultat attendu — 2.1.0),
 * agents à former (au moins un), période souhaitée.
 * Le nombre de participants est celui des agents cochés (version 1.5.0).
 * Le service est celui du responsable de structure (un autre service est refusé par BesoinService) ; seule la RH (droit « tous services ») le choisit. L'année est déterminée automatiquement.
 */
class BesoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->aPermission('besoins.creer');
    }

    public function rules(): array
    {
        $tousServices = $this->user()->aPermission('besoins.creer_tous_services');

        return [
            'service_id' => [$tousServices ? 'required' : 'nullable', 'integer', Rule::exists('services', 'id')->where('actif', true)],
            'intitule' => ['required', 'string', 'max:255'],
            'contexte' => ['required', 'string', 'max:5000'],
            'diag_savoir' => ['required', Rule::in(array_keys(BesoinFormation::DIAG_SAVOIR))],
            'diag_moyens' => ['required', Rule::in(array_keys(BesoinFormation::DIAG_OUI_NON))],
            'diag_temps' => ['required', Rule::in(array_keys(BesoinFormation::DIAG_OUI_NON))],
            'gravite' => ['required', Rule::in(array_keys(BesoinFormation::GRAVITES))],
            'situation_actuelle' => ['required', 'string', 'max:500'],
            'objectif_vise' => ['required', 'string', 'max:500'],
            'solutions_essayees' => ['required', 'array', 'min:1'],
            'solutions_essayees.*' => ['string', Rule::in(array_keys(BesoinFormation::SOLUTIONS))],
            'justification_formation' => ['required', 'string', 'max:3000'],
            'resultat_attendu' => ['required', 'string', 'max:3000'],
            'periode_souhaitee' => ['nullable', 'string', 'max:100'],
            'agents' => ['required', 'array', 'min:1', 'max:500'],
            'agents.*' => ['integer', 'exists:agents,id'],
        ];
    }

    public function attributes(): array
    {
        return ['service_id' => 'service demandeur', 'intitule' => 'formation souhaitée', 'contexte' => 'problème constaté', 'diag_savoir' => 'les agents savent-ils faire', 'diag_moyens' => 'les moyens',
            'diag_temps' => 'le temps', 'gravite' => 'gravité', 'situation_actuelle' => 'situation actuelle', 'objectif_vise' => 'objectif visé',
            'solutions_essayees' => 'solutions déjà essayées',
            'justification_formation' => 'raison pour laquelle une formation réglerait le problème', 'resultat_attendu' => 'ce que les agents devront savoir faire',
            'agents' => 'agents à former', 'periode_souhaitee' => 'période souhaitée'];
    }

    public function messages(): array
    {
        return ['agents.required' => 'Cochez au moins un agent à former.', 'agents.min' => 'Cochez au moins un agent à former.',
            'diag_savoir.required' => 'Répondez à la question.', 'diag_moyens.required' => 'Répondez à la question.', 'diag_temps.required' => 'Répondez à la question.',
            'gravite.required' => 'Indiquez la gravité du problème.',
            'solutions_essayees.required' => 'Cochez au moins une réponse (« Rien pour l\'instant » si rien n\'a été essayé).',
            'solutions_essayees.min' => 'Cochez au moins une réponse (« Rien pour l\'instant » si rien n\'a été essayé).'];
    }

    /** « Rien pour l'instant » ne se coche pas avec une autre solution. */
    public function withValidator($v): void
    {
        $v->after(function ($v) {
            $s = (array) $this->input('solutions_essayees', []);
            if (in_array('aucune', $s, true) && count($s) > 1) {
                $v->errors()->add('solutions_essayees', '« Rien pour l\'instant » ne peut pas être coché avec une autre réponse.');
            }
        });
    }

    public function donnees(): array
    {
        return collect($this->validated())->except('agents')->all();
    }
}
