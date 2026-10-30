@php $gerer = auth()->user()->aPermission('organismes.gerer'); @endphp
<x-layout :titre="$o->raison_sociale">
    <x-entete :titre="$o->raison_sociale" :sous-titre="$o->sigle">@if ($gerer)<a class="btn btn-secondaire" href="{{ route('organismes.edit', $o) }}">Modifier</a>@endif</x-entete>
    <div class="grid gap-5 lg:grid-cols-3 mb-5">
        <x-carte titre="Coordonnées">
            <dl class="dl-grille">
                <dt>Adresse</dt><dd>{{ $o->adresse ?? '—' }}</dd><dt>Téléphone</dt><dd>{{ $o->telephone ?? '—' }}</dd><dt>E-mail</dt><dd>{{ $o->email ?? '—' }}</dd>
                <dt>Site</dt><dd>{{ $o->site_web ?? '—' }}</dd><dt>Domaines</dt><dd class="whitespace-pre-line">{{ $o->domaines_intervention ?? '—' }}</dd>
                @if ($contrats)<dt>Identifiant fiscal</dt><dd>{{ $o->identifiant_fiscal ?? '—' }}</dd>@endif
                <dt>Statut</dt><dd>{{ $o->actif ? 'Actif' : 'Inactif' }}</dd>
            </dl>
        </x-carte>
        <x-carte titre="Personnes de contact" class="lg:col-span-2">
            <ul class="text-sm divide-y divide-slate-100">
                @forelse ($o->contacts as $c)
                    <li class="py-2 flex justify-between gap-2"><span><strong>{{ $c->nom }}</strong> {{ $c->fonction ? '— '.$c->fonction : '' }} {{ $c->telephone }} {{ $c->email }}</span>
                        @if ($gerer)<x-action-form :url="route('organismes.contacts.destroy', $c)" methode="DELETE" libelle="Retirer" classe="btn btn-danger btn-petit" confirmer="Retirer ce contact ?" />@endif</li>
                @empty <li class="py-2 text-slate-500">Aucun contact.</li> @endforelse
            </ul>
            @if ($gerer)
                <form method="POST" action="{{ route('organismes.contacts.store', $o) }}" class="mt-3 grid gap-2 md:grid-cols-5 items-end no-print">@csrf
                    <x-champ nom="nom" libelle="Nom" :requis="true" /><x-champ nom="fonction" libelle="Fonction" /><x-champ nom="telephone" libelle="Téléphone" /><x-champ nom="email" libelle="E-mail" type="email" />
                    <div><button class="btn btn-secondaire">Ajouter</button></div></form>
            @endif
        </x-carte>
    </div>

    @if ($contrats)
        <x-carte titre="Conventions et contrats (accès restreint)" class="mb-5" sans-marge>
            @if ($o->conventions->isEmpty())<x-vide message="Aucune convention." />@else
                <table><thead><tr><th>Référence</th><th>Objet</th><th>Période</th></tr></thead>
                    <tbody>@foreach ($o->conventions as $c)<tr><td>{{ $c->reference }}</td><td>{{ $c->objet }}</td><td>{{ date_fr($c->date_debut) }} – {{ date_fr($c->date_fin) }}</td></tr>@endforeach</tbody></table>
            @endif
            <form method="POST" action="{{ route('organismes.conventions.store', $o) }}" class="p-4 grid gap-2 md:grid-cols-5 items-end border-t border-slate-100 no-print">@csrf
                <x-champ nom="reference" libelle="Référence" :requis="true" /><x-champ class="md:col-span-2" nom="objet" libelle="Objet" :requis="true" />
                <x-champ nom="date_debut" libelle="Début" type="date" /><x-champ nom="date_fin" libelle="Fin" type="date" />
                <div><button class="btn btn-secondaire">Ajouter</button></div></form>
        </x-carte>
    @endif

    <div class="grid gap-5 lg:grid-cols-2 mb-5">
        <x-carte titre="Historique des prestations" sans-marge>
            @if ($o->actions->isEmpty())<x-vide message="Aucune action rattachée." />@else
                <table><thead><tr><th>Formation</th><th>Année</th><th>Statut</th></tr></thead><tbody>@foreach ($o->actions as $a)<tr><td>@permission('actions.voir')<a href="{{ route('formations.show', $a) }}">{{ $a->reference }}</a>@else{{ $a->reference }}@endpermission — {{ $a->intitule }}</td><td>{{ $a->annee }}</td><td><x-badge :statut="$a->statut" /></td></tr>@endforeach</tbody></table>
            @endif
        </x-carte>
        <x-carte titre="Évaluations du prestataire">
            <ul class="text-sm space-y-2 mb-3">@forelse ($o->appreciations as $ap)<li><strong>{{ $ap->note }}/5</strong> — {{ date_fr($ap->created_at) }} par {{ $ap->user->name }}{{ $ap->action ? ' ('.$ap->action->reference.')' : '' }}@if ($ap->commentaire)<div class="text-slate-600">{{ $ap->commentaire }}</div>@endif</li>@empty<li class="text-slate-500">Aucune évaluation.</li>@endforelse</ul>
            @permission('organismes.gerer')
                <form method="POST" action="{{ route('organismes.appreciations.store', $o) }}" class="grid gap-2 md:grid-cols-3 items-end no-print">@csrf
                    <x-champ nom="note" libelle="Note" type="select" :options="[5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']" :requis="true" />
                    <x-champ nom="action_id" libelle="Action concernée" type="select" :options="$o->actions->pluck('reference', 'id')" />
                    <x-champ class="md:col-span-3" nom="commentaire" libelle="Commentaire" type="textarea" />
                    <div><button class="btn btn-secondaire">Enregistrer</button></div></form>
            @endpermission
        </x-carte>
    </div>
    @if ($contrats)<x-documents :objet="$o" type="organisme" :documents="$o->documents" :categories="$categoriesDoc" :peut-deposer="true" />@endif
</x-layout>
