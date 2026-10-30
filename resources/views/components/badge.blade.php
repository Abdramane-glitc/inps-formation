@props(['statut' => null, 'texte' => null, 'couleur' => null])
@php
    $c = $couleur ?? ($statut instanceof \BackedEnum ? $statut->couleur() : 'gris');
    $t = $texte ?? ($statut instanceof \BackedEnum ? $statut->libelle() : (string) $statut);
    $classes = [
        'gris' => 'bg-[#EEF1F5] text-encre-2',
        'bleu' => 'bg-inps-50 text-inps-2',
        'indigo' => 'bg-inps-100 text-inps',
        'orange' => 'bg-alerte-fond text-alerte',
        'vert' => 'bg-ok-fond text-ok',
        'rouge' => 'bg-erreur-fond text-erreur',
        'emeraude' => 'bg-ok-fond text-[#13603C]',
    ][$c] ?? 'bg-[#EEF1F5] text-encre-2';
@endphp
<span class="statut {{ $classes }}">{{ $t }}</span>
