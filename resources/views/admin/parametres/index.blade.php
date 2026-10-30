<x-layout titre="Paramètres">
    <x-entete titre="Paramétrage institutionnel" sous-titre="Renseignez ces réglages au besoin : un champ laissé vide n'empêche rien de fonctionner." />
    <form method="POST" action="{{ route('admin.parametres.update') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf @method('PUT')
        @foreach (\App\Models\Parametre::GROUPES as $g => $titre)
            @continue(! isset($groupes[$g]))
            <x-carte :titre="$titre">
                <div class="grille-form">
                    @foreach ($groupes[$g] as $p)
                        @php $nom = 'p['.str_replace('.', '__', $p->cle).']'; @endphp
                        <div>
                            @if ($p->type === 'bool')
                                <x-champ :nom="$nom" :libelle="$p->libelle" type="select" :valeur="$p->valeur" :options="['1' => 'Oui', '0' => 'Non']" :vide="false" />
                            @elseif ($p->type === 'file')
                                @php $champ = $p->cle === 'institution.logo' ? 'logo' : 'photo'; $url = \App\Support\Identite::url($champ); @endphp
                                <label for="f_{{ $champ }}">{{ $p->libelle }}</label>
                                @if ($url)
                                    <img src="{{ $url }}" alt="{{ $champ === 'logo' ? 'Logo actuel' : 'Photographie actuelle' }}" class="{{ $champ === 'logo' ? 'h-14' : 'h-28 w-full object-cover' }} mb-2 rounded border border-filet">
                                @else
                                    <p class="mb-2 text-sm text-alerte">Aucun fichier fourni pour le moment.</p>
                                @endif
                                <input id="f_{{ $champ }}" type="file" name="{{ $champ }}" accept="{{ $champ === 'logo' ? '.png,.jpg,.jpeg' : '.jpg,.jpeg,.png,.webp' }}">
                            @elseif ($p->type === 'text')
                                <x-champ :nom="$nom" :libelle="$p->libelle" type="textarea" :valeur="$p->valeur" />
                            @else
                                <x-champ :nom="$nom" :libelle="$p->libelle" :type="in_array($p->type, ['int', 'decimal']) ? 'number' : 'text'" :valeur="$p->valeur" :attrs="$p->type === 'decimal' ? ['step' => '0.01'] : []" />
                            @endif
                            @if ($p->aide)<p class="aide">{{ $p->aide }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </x-carte>
        @endforeach
        <button class="btn btn-primaire">Enregistrer les paramètres</button>
    </form>
    <x-carte titre="Autres paramétrages" class="mt-5">
        <p class="text-sm">Directions, services, domaines, nomenclatures (priorités, types, statuts, axes, catégories) et années de référence : <a href="{{ route('admin.referentiels.index', 'directions') }}">Référentiels</a>.</p>
    </x-carte>
</x-layout>
