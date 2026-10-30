@php
    $existe = $formation->exists;
    $s = $session;
    // Dates modifiables : à la création, ou tant que la formation n'est ni terminée ni annulée.
    $datesModifiables = ! $s->exists || ! in_array($s->statut, [\App\Enums\StatutSession::Annulee, \App\Enums\StatutSession::Terminee], true);
@endphp
<x-layout :titre="$existe ? 'Modifier la formation' : 'Nouvelle formation'">
    <x-entete :titre="$existe ? 'Modifier la formation '.$formation->reference : 'Nouvelle formation'"
              :sous-titre="$existe ? null : 'Pour une formation décidée par la RH, sans demande d\'un service. Les participants s\'ajoutent ensuite sur la fiche.'" />
    <form method="POST" action="{{ $existe ? route('formations.update', $formation) : route('formations.store') }}" class="space-y-5">
        @csrf @if ($existe) @method('PUT') @endif

        <x-carte>
            <div class="grille-form">
                <x-champ class="md:col-span-2" nom="intitule" libelle="Intitulé de la formation" :valeur="$formation->intitule" :requis="true" />
                @if ($datesModifiables)
                    <x-champ nom="date_debut" libelle="Date de début" type="date" :valeur="$s->date_debut" :requis="true" />
                    <x-champ nom="date_fin" libelle="Date de fin" type="date" :valeur="$s->date_fin" :requis="true" />
                    <x-champ class="md:col-span-2" nom="lieu" libelle="Lieu" :valeur="$s->lieu" />
                @endif
                <x-champ nom="type" libelle="Formation" type="select" :valeur="$formation->type" :options="['interne' => 'Interne (assurée par l\'INPS)', 'externe' => 'Externe (organisme formateur)', 'mixte' => 'Mixte (formateurs internes et externes)']" :vide="false" :requis="true" />
                <x-champ nom="organisme_id" libelle="Organisme formateur" type="select" :valeur="$formation->organisme_id" :options="$organismes->pluck('raison_sociale', 'id')" aide="Obligatoire pour une formation externe ou mixte." />
                @php $choisisF = collect(old('formateurs', ($s->relationLoaded('formateurs') || $s->exists) ? $s->formateurs->pluck('id')->all() : []))->map(fn ($v) => (int) $v); @endphp
                <fieldset class="md:col-span-2">
                    <legend class="font-semibold">Formateurs <span class="font-normal text-encre-3 text-sm">(un ou plusieurs, internes et externes)</span></legend>
                    <input type="hidden" name="formateurs_envoyes" value="1">
                    @if ($formateurs->isEmpty())
                        <p class="aide">Aucun formateur enregistré.@permission('organismes.gerer') <a href="{{ route('formateurs.create') }}" target="_blank" rel="noopener">Ajouter un formateur</a> (nouvel onglet), puis rechargez cette page.@endpermission</p>
                    @else
                        <div class="grid gap-4 md:grid-cols-2 mt-2 text-sm">
                            @foreach (['interne' => 'Internes (agents de l\'INPS)', 'externe' => 'Externes (prestataires)'] as $t => $titreGroupe)
                                <div>
                                    <p class="font-semibold text-encre-2 mb-1">{{ $titreGroupe }}</p>
                                    @forelse ($formateurs->where('type', $t) as $fm)
                                        <label class="font-normal flex gap-2 items-start"><input type="checkbox" name="formateurs[]" value="{{ $fm->id }}" @checked($choisisF->contains($fm->id)) class="mt-1"> {{ $fm->nom_complet }}@if ($fm->organisme) <span class="text-encre-3">— {{ $fm->organisme->raison_sociale }}</span>@endif</label>
                                    @empty
                                        <p class="text-encre-3">Aucun.</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                        @error('formateurs')<p class="erreur-champ"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $message }}</p>@else<p class="aide">Un formateur interne et un externe ensemble : choisissez le type « Mixte ». Les formateurs restent modifiables après la formation.@permission('organismes.gerer') <a href="{{ route('formateurs.create') }}" target="_blank" rel="noopener">Ajouter un formateur</a> (nouvel onglet).@endpermission</p>@enderror
                    @endif
                </fieldset>
            </div>
        </x-carte>

        @unless ($datesModifiables)
            <div class="message message-info"><x-icone nom="info" class="h-5 w-5 flex-none" /><div>La formation est {{ mb_strtolower($s->statut->libelle()) }} : ses dates ne sont plus modifiables.</div></div>
        @endunless

        <details class="carte" @if ($errors->hasAny(['domaine_id', 'objectifs_pedagogiques', 'description', 'heure_debut', 'heure_fin', 'duree_heures', 'public_cible'])) open @endif>
            <summary class="cursor-pointer px-4 py-3 font-semibold text-sm">Plus de détails (facultatif)</summary>
            <div class="grille-form px-4 pb-4">
                <x-champ nom="domaine_id" libelle="Domaine" type="select" :valeur="$formation->domaine_id" :options="$domaines->pluck('nom', 'id')" />
                <x-champ nom="modalite" libelle="Modalité" type="select" :valeur="$formation->modalite ?? 'presentiel'" :options="\App\Models\ActionFormation::MODALITES" :vide="false" />
                @if ($datesModifiables)
                    <x-champ nom="heure_debut" libelle="Heure de début" type="time" :valeur="$s->heure_debut ? substr($s->heure_debut, 0, 5) : null" />
                    <x-champ nom="heure_fin" libelle="Heure de fin" type="time" :valeur="$s->heure_fin ? substr($s->heure_fin, 0, 5) : null" />
                    <x-champ nom="duree_heures" libelle="Durée totale (heures)" type="number" :valeur="$s->duree_heures" :attrs="['min' => 0.5, 'step' => 0.5]" />
                @endif
                <x-champ class="md:col-span-2" nom="public_cible" libelle="Public visé" :valeur="$formation->public_cible" />
                <x-champ class="md:col-span-2" nom="objectifs_pedagogiques" libelle="Objectifs" type="textarea" :valeur="$formation->objectifs_pedagogiques" />
                <x-champ class="md:col-span-2" nom="description" libelle="Description" type="textarea" :valeur="$formation->description" />
            </div>
        </details>

        <div class="flex gap-2">
            <button class="btn btn-primaire">{{ $existe ? 'Enregistrer' : 'Créer la formation' }}</button>
            <a class="btn btn-secondaire" href="{{ $existe ? route('formations.show', $formation) : route('formations.index') }}">Annuler</a>
        </div>
    </form>
</x-layout>
