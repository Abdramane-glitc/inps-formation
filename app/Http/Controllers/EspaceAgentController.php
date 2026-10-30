<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace personnel : un agent ne voit que SES formations, SES convocations et l'état de SES attestations.
 * Depuis le 28/10/2026 (demande de la RH), l'agent ne télécharge plus son attestation : elle lui est remise par la RH.
 */
class EspaceAgentController extends Controller
{
    public function index(Request $r): View
    {
        $agent = $r->user()->agent;
        $inscriptions = $agent ? $agent->inscriptions()->with(['session.action.domaine', 'attestation'])
            ->join('sessions_formation', 'sessions_formation.id', '=', 'inscriptions.session_id')
            ->join('actions_formation', fn ($j) => $j->on('actions_formation.id', '=', 'sessions_formation.action_id')->whereNull('actions_formation.deleted_at'))
            ->orderByDesc('sessions_formation.date_debut')->select('inscriptions.*')->get() : collect();

        return view('espace.index', compact('agent', 'inscriptions'));
    }
}
