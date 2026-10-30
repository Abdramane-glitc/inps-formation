{{-- Champs d'une ligne du plan ($l : ligne existante ou null ; $p : préfixe d'identifiant) --}}
<div><label for="{{ $p }}_i">Formation prévue <span class="text-erreur" aria-hidden="true">*</span></label>
    <input id="{{ $p }}_i" type="text" name="intitule" maxlength="255" required value="{{ $l?->intitule }}" placeholder="Exemple : Excel pour le suivi des cotisations"></div>
<div class="grid grid-cols-2 gap-3">
    <div><label for="{{ $p }}_p">Période <span class="text-erreur" aria-hidden="true">*</span></label>
        <select id="{{ $p }}_p" name="periode" required>@foreach (\App\Models\PlanLigne::PERIODES as $k => $v)<option value="{{ $k }}" @selected(($l?->periode ?? 'annee') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label for="{{ $p }}_n">Agents prévus</label><input id="{{ $p }}_n" type="number" name="nb_agents" min="1" max="5000" value="{{ $l?->nb_agents }}"></div>
</div>
<div><label for="{{ $p }}_s">Service concerné</label>
    <select id="{{ $p }}_s" name="service_id"><option value="">Plusieurs services ou toute l'INPS</option>@foreach ($services as $dn => $groupe)<optgroup label="{{ $dn }}">@foreach ($groupe as $id => $nom)<option value="{{ $id }}" @selected($l?->service_id == $id)>{{ $nom }}</option>@endforeach</optgroup>@endforeach</select></div>
<div class="grid grid-cols-2 gap-3">
    <div><label for="{{ $p }}_t">Formation</label><select id="{{ $p }}_t" name="type"><option value="">À préciser</option><option value="interne" @selected($l?->type === 'interne')>Interne</option><option value="externe" @selected($l?->type === 'externe')>Externe</option><option value="mixte" @selected($l?->type === 'mixte')>Mixte</option></select></div>
    <div><label for="{{ $p }}_o">Organisme envisagé</label><select id="{{ $p }}_o" name="organisme_id"><option value="">—</option>@foreach ($organismes as $id => $nom)<option value="{{ $id }}" @selected($l?->organisme_id == $id)>{{ $nom }}</option>@endforeach</select></div>
</div>
<div><label for="{{ $p }}_b">Observations</label><textarea id="{{ $p }}_b" name="observations" rows="2" maxlength="2000">{{ $l?->observations }}</textarea></div>
