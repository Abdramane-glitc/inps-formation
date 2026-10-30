<?php

namespace App\Http\Controllers;

use App\Models\Attestation;
use App\Models\Inscription;
use App\Models\SessionFormation;
use App\Services\AttestationService;
use App\Services\Audit;
use App\Services\ExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AttestationController extends Controller
{
    public function __construct(private AttestationService $service) {}

    public function index(Request $r): View
    {
        $q = $r->validate(['q' => ['nullable', 'string', 'max:60']])['q'] ?? null;

        $attestations = Attestation::with(['inscription.agent', 'inscription.session.action', 'emetteur'])
            ->when($q, fn ($w) => $w->where('reference', 'like', '%'.addcslashes($q, '%_').'%')
                ->orWhereHas('inscription.agent', fn ($a) => $a->where('matricule', 'like', '%'.addcslashes($q, '%_').'%')))
            ->latest('emise_le')->paginate(25)->withQueryString();

        // Nombre d'impressions (téléchargements du PDF) de chaque attestation de la page (28/10/2026)
        return view('attestations.index', ['attestations' => $attestations, 'q' => $q, 'impressions' => Attestation::impressions($attestations->pluck('id'))]);
    }

    public function store(Request $r, Inscription $inscription): RedirectResponse
    {
        $a = $this->service->emettre($inscription, $r->user());

        return back()->with('succes', "Attestation {$a->reference} émise.");
    }

    public function emettreSession(Request $r, SessionFormation $session): RedirectResponse
    {
        $emises = 0;
        $refus = [];
        foreach ($session->inscriptions()->with('agent')->where('resultat', 'achevee')->get() as $i) {
            if ($manque = $this->service->conditionsNonRemplies($i)) {
                $refus[] = $i->agent->nom_complet.' : '.implode(' ', $manque);

                continue;
            }
            $this->service->emettre($i, $r->user());
            $emises++;
        }

        return back()->with('succes', "$emises attestation(s) émise(s).")->with('avertissements', $refus);
    }

    public function pdf(Attestation $attestation, ExportService $export): Response
    {
        abort_unless($attestation->estValide(), 410, 'Attestation annulée.');
        Audit::enregistrer('attestation.telechargement', $attestation, "Téléchargement de l'attestation {$attestation->reference}");

        return $export->pdf('pdf.attestation', ['a' => $attestation->load('inscription.agent', 'inscription.session.action')], 'attestation-'.$attestation->reference);
    }

    public function annuler(Request $r, Attestation $attestation): RedirectResponse
    {
        $d = $r->validate(['motif' => ['required', 'string', 'max:500']]);
        $this->service->annuler($attestation, $d['motif'], $r->user());

        return back()->with('succes', 'Attestation annulée.');
    }
}
