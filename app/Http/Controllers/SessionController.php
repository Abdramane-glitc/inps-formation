<?php

namespace App\Http\Controllers;

use App\Models\ActionFormation;
use App\Models\SessionFormation;
use App\Services\ActionService;
use App\Services\Audit;
use App\Services\ExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formation (session unique) : annulation et liste d'émargement imprimable.
 * L'affichage se fait sur la fiche « Formation » (FormationController).
 */
class SessionController extends Controller
{
    public function __construct(private ActionService $actions) {}

    /** Ancienne adresse d'une session : la session fait désormais partie de la fiche « Formation ». */
    public function show(SessionFormation $session): RedirectResponse
    {
        return redirect()->route('formations.show', $session->action_id);
    }

    public function annuler(Request $r, SessionFormation $session): RedirectResponse
    {
        $d = $r->validate(['motif' => ['required', 'string', 'max:500']]);
        $this->actions->annuler($session, $d['motif'], $r->user());

        return back()->with('succes', 'Formation annulée.');
    }

    public function emargement(Request $r, SessionFormation $session, ExportService $export): Response
    {
        $session->load(['action', 'formateurs', 'seances']);
        abort_unless($session->action->estVisiblePar($r->user()), 403);
        $sid = ActionFormation::serviceLimite($r->user());
        $participants = $session->participantsActifs()->with('agent.service')
            ->when($sid, fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('service_id', $sid)))->get()
            ->sortBy(fn ($i) => ($i->agent->service?->nom ?? '~').' '.$i->agent->nom.' '.$i->agent->prenom);
        Audit::enregistrer('export.emargement', $session, "Liste d'émargement {$session->code}");

        return $export->pdf('pdf.emargement', ['s' => $session, 'participants' => $participants], 'emargement-'.$session->code, 'landscape');
    }
}
