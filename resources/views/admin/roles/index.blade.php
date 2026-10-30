@php use App\Support\Profils; @endphp
<x-layout titre="Profils et droits">
    <x-entete titre="Profils et droits" sous-titre="Deux profils de départ : la RH gère tout, le responsable de structure fait les demandes de son service. Le profil se choisit dans « Utilisateurs », où les droits d'un compte peuvent aussi être personnalisés." />

    <div class="grid gap-5 md:grid-cols-2 mb-5">
        @foreach (Profils::PROFILS as $code => [$libelle, , $description])
            <x-carte>
                <h2 class="text-lg font-semibold">{{ $libelle }}</h2>
                <p class="text-sm text-encre-2 mt-1">{{ $description }}</p>
                <p class="text-sm mt-3"><a href="{{ route('admin.utilisateurs.index', ['profil' => $code]) }}">{{ $comptes[$code] }} compte(s) actif(s)</a></p>
            </x-carte>
        @endforeach
    </div>

    <x-carte titre="Qui peut faire quoi" sans-marge>
        <div class="overflow-x-auto"><table>
            <thead><tr><th>Action</th>@foreach (Profils::PROFILS as $code => [$libelle])<th class="text-center w-36">{{ $libelle }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($permissions as $module => $liste)
                <tr class="bg-fond"><th colspan="{{ 1 + count(Profils::PROFILS) }}" class="text-left text-sm font-semibold">{{ $module }}</th></tr>
                @foreach ($liste as $perm)
                    <tr><td>{{ $perm->libelle }}</td>
                        @foreach (Profils::PROFILS as $code => $x)
                            <td class="text-center">@if (in_array($perm->code, $droits[$code], true))<span class="text-ok font-semibold" aria-label="Oui">✓</span>@else<span class="text-encre-3" aria-label="Non">—</span>@endif</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach</tbody>
        </table></div>
    </x-carte>

    <x-carte titre="Comptes aux droits personnalisés ({{ $personnalises->count() }})" sans-marge class="mt-5">
        @if ($personnalises->isEmpty())
            <x-vide message="Aucun compte : tous ont exactement les droits de leur profil." />
        @else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Compte</th><th>Profil</th><th>Droits ajoutés</th><th>Droits retirés</th></tr></thead>
                <tbody>@foreach ($personnalises as $pu)
                    @php $ecarts = $pu->permissionsPersonnalisees; @endphp
                    <tr><td><a href="{{ route('admin.utilisateurs.edit', $pu) }}">{{ $pu->name }}</a><div class="text-xs text-encre-3">{{ $pu->identifiant }}</div></td>
                        <td>{{ Profils::libelle(Profils::de($pu) ?? '') ?: '—' }}</td>
                        <td>{{ $ecarts->filter(fn ($p) => $p->pivot->accorde)->pluck('libelle')->implode(' ; ') ?: '—' }}</td>
                        <td>{{ $ecarts->reject(fn ($p) => $p->pivot->accorde)->pluck('libelle')->implode(' ; ') ?: '—' }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </x-carte>

    @if ($autresRoles->where('users_count', '>', 0)->isNotEmpty())
        <div class="message message-info mt-5"><x-icone nom="info" class="h-5 w-5 flex-none" /><div>
            Des comptes créés avant la simplification ont encore d'autres rôles
            ({{ $autresRoles->where('users_count', '>', 0)->map(fn ($r) => $r->nom.' : '.$r->users_count)->implode(', ') }}).
            Ouvrez ces comptes dans « Utilisateurs » pour leur attribuer le profil RH ou Responsable de structure.
        </div></div>
    @endif
</x-layout>
