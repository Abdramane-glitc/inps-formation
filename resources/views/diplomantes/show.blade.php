@php use App\Models\FormationDiplomante as D; @endphp
<x-layout :titre="$d->reference">
    <x-entete :titre="$d->libelle('niveau').' — '.$d->intitule" :sous-titre="$d->reference.' — '.$d->agent->nom_complet">
        <a class="btn btn-secondaire" href="{{ route('diplomantes.edit', $d) }}">Modifier</a>
        <x-action-form :url="route('diplomantes.destroy', $d)" methode="DELETE" libelle="Supprimer" classe="btn btn-danger" confirmer="Supprimer définitivement cette formation diplômante et retirer ses pièces jointes ?" />
    </x-entete>
    <div class="grid gap-5 lg:grid-cols-3 mb-5">
        <x-carte titre="Agent">
            <dl class="dl-grille">
                <dt>Nom</dt><dd>@permission('agents.voir_tous')<a href="{{ route('agents.show', $d->agent) }}">{{ $d->agent->nom_complet }}</a>@else{{ $d->agent->nom_complet }}@endpermission</dd>
                <dt>Matricule</dt><dd>{{ $d->agent->matricule }}</dd>
                <dt>Direction</dt><dd>{{ $d->agent->direction?->nom ?? '—' }}</dd>
                <dt>Service</dt><dd>{{ $d->agent->service?->nom ?? '—' }}</dd>
            </dl>
        </x-carte>
        <x-carte titre="Formation" class="lg:col-span-2">
            <dl class="dl-grille">
                <dt>Diplôme</dt><dd>{{ $d->libelle('niveau') }} — {{ $d->intitule }}</dd>
                <dt>Établissement</dt><dd>{{ $d->etablissement }}{{ $d->ville || $d->pays ? ' ('.collect([$d->ville, $d->pays])->filter()->implode(', ').')' : '' }}</dd>
                <dt>Année académique</dt><dd>{{ $d->annee_academique }}{{ $d->duree_annees ? ' — durée prévue : '.$d->duree_annees.' an'.($d->duree_annees > 1 ? 's' : '') : '' }}</dd>
                <dt>Mode</dt><dd>{{ $d->libelle('mode') }}</dd>
                <dt>Prise en charge</dt><dd>{{ $d->libelle('prise_en_charge') }}</dd>
                <dt>Situation</dt><dd><x-badge :texte="$d->libelle('situation')" :couleur="D::COULEURS[$d->situation] ?? 'gris'" />@if ($d->date_obtention) le {{ date_fr($d->date_obtention) }}@endif</dd>
                @if ($d->observations)<dt>Observations</dt><dd class="whitespace-pre-line">{{ $d->observations }}</dd>@endif
                <dt>Enregistrée</dt><dd>le {{ date_fr($d->created_at) }}{{ $d->createur ? ' par '.$d->createur->name : '' }}</dd>
            </dl>
        </x-carte>
    </div>
    <x-documents :objet="$d" type="diplomante" :documents="$d->documents" :categories="$categoriesDoc" :peut-deposer="true" />
</x-layout>
