<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->aPermission('agents.gerer');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['matricule' => mb_strtoupper(trim((string) $this->input('matricule')))]);
    }

    public function rules(): array
    {
        $id = $this->route('agent')?->id;

        return [
            'matricule' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-\/\.]{2,30}$/', Rule::unique('agents', 'matricule')->ignore($id)],
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'direction_id' => ['nullable', 'integer', 'exists:directions,id'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->when($this->input('direction_id'), fn ($r) => $r->where('direction_id', $this->input('direction_id')))],
            'fonction' => ['nullable', 'string', 'max:190'],
            'statut_professionnel_id' => ['nullable', 'integer', Rule::exists('nomenclatures', 'id')->where('type', 'statut_professionnel')],
            'email_pro' => ['nullable', 'email', 'max:190'],
            'telephone_pro' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+ ().-]{6,40}$/'],
        ];
    }

    public function messages(): array
    {
        return ['service_id.exists' => 'Le service choisi n\'appartient pas à la direction sélectionnée.', 'matricule.regex' => 'Le matricule ne peut contenir que des lettres, chiffres, tirets, barres obliques et points.'];
    }
}
