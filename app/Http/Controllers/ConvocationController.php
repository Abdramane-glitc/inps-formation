<?php

namespace App\Http\Controllers;

use App\Models\ActionFormation;
use App\Models\Inscription;
use App\Models\InscriptionEvenement;
use App\Services\Audit;
use App\Services\ConvocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Convocation des participants d'une formation (2.3.0). */
class ConvocationController extends Controller
{
    public function __construct(private ConvocationService $convocations) {}

    public function convoquer(Request $r, ActionFormation $formation): RedirectResponse
    {
        abort_unless($formation->estVisiblePar($r->user()), 403);
        $res = $this->convocations->convoquer($formation->session()->firstOrFail(), $r->user(), $r->boolean('nouveaux'));

        return redirect()->to(route('formations.show', $formation).'#participants')->with('succes', $this->convocations->resume($res));
    }

    /** Toutes les convocations de la formation (une page par participant convoqué). */
    public function pdf(Request $r, ActionFormation $formation): Response
    {
        abort_unless($formation->estVisiblePar($r->user()), 403);
        $s = $formation->session()->firstOrFail();
        $liste = $s->participantsActifs()->whereNotNull('convoque_le')->with('agent.service')->get()->sortBy(fn ($i) => $i->agent->nom.' '.$i->agent->prenom)->values();
        abort_if($liste->isEmpty(), 404);
        Audit::enregistrer('export.convocations', $formation, "Convocations de la formation {$formation->reference} imprimées");

        return $this->reponse($this->convocations->pdf($s, $liste, $this->rectificative($s)), 'convocations-'.$formation->reference);
    }

    /** Mon espace : la convocation de l'agent connecté. */
    public function agent(Request $r, Inscription $inscription): Response
    {
        abort_unless($r->user()->agent_id && $inscription->agent_id === $r->user()->agent_id && $inscription->convoque_le, 403);
        $s = $inscription->session;

        return $this->reponse($this->convocations->pdf($s, collect([$inscription]), $this->rectificative($s)), 'convocation-'.$s->action->reference);
    }

    /** Une convocation rectificative a été envoyée pour cette formation : les PDF réimprimés le restent. */
    private function rectificative($s): bool
    {
        return InscriptionEvenement::whereIn('inscription_id', $s->inscriptions()->select('id'))->where('evenement', 'convocation_rectificative')->exists();
    }

    private function reponse(string $pdf, string $nom): Response
    {
        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.Str::slug($nom).'.pdf"', 'Cache-Control' => 'no-store, private']);
    }
}
