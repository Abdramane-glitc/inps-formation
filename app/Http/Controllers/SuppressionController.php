<?php

namespace App\Http\Controllers;

use App\Exceptions\RegleMetierException;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\BesoinFormation;
use App\Models\User;
use App\Services\SuppressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Suppression des erreurs de saisie (une ligne ou une sélection cochée) et corbeille de la RH (version 1.6.0).
 * Les conditions sont dans SuppressionService ; ici, les droits d'accès et les messages.
 */
class SuppressionController extends Controller
{
    public const TYPES = ['besoins', 'formations', 'agents', 'comptes'];

    public function __construct(private SuppressionService $service) {}

    /** Suppression d'un seul élément. */
    public function unique(Request $r, string $type, int $id): RedirectResponse
    {
        $objet = $this->trouver($type, $id, $r->user());
        try {
            $this->supprimer($type, $objet, $r->user());
        } catch (RegleMetierException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route($this->retour($type))->with('succes', $this->nom($type, $objet).' supprimé(e).'
            .(in_array($type, ['besoins', 'formations'], true) ? ' La RH peut la restaurer depuis la corbeille.' : ''));
    }

    /** Suppression des lignes cochées dans une liste : les lignes non supprimables sont signalées avec leur raison. */
    public function selection(Request $r, string $type): RedirectResponse
    {
        $ids = $r->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']], ['ids.required' => 'Cochez au moins une ligne.'])['ids'];
        $faits = 0;
        $refus = [];
        foreach (array_unique($ids) as $id) {
            try {
                $objet = $this->trouver($type, (int) $id, $r->user());
                $this->supprimer($type, $objet, $r->user());
                $faits++;
            } catch (RegleMetierException $e) {
                $refus[] = $e->getMessage();
            } catch (HttpException) {
                $refus[] = "Élément n° $id : accès refusé ou introuvable.";
            }
        }

        return back()->with($faits ? 'succes' : 'erreur', "$faits élément(s) supprimé(s) sur ".count(array_unique($ids)).'.')->with('avertissements', $refus);
    }

    public function corbeille(): View
    {
        $auteurs = fn (string $morph, $ids) => AuditLog::with('user')->where('objet_type', $morph)->whereIn('objet_id', $ids)
            ->where('action', 'like', '%.suppression')->latest('id')->get()->unique('objet_id')->keyBy('objet_id');
        $besoins = BesoinFormation::onlyTrashed()->with('service')->latest('deleted_at')->get();
        $formations = ActionFormation::onlyTrashed()->with('session')->latest('deleted_at')->get();

        return view('corbeille.index', ['besoins' => $besoins, 'formations' => $formations,
            'parBesoin' => $auteurs('besoin', $besoins->pluck('id')), 'parFormation' => $auteurs('action', $formations->pluck('id'))]);
    }

    public function restaurer(Request $r, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['besoins', 'formations'], true), 404);
        if ($type === 'besoins') {
            $b = BesoinFormation::onlyTrashed()->findOrFail($id);
            $this->service->restaurerBesoin($b, $r->user());

            return redirect()->route('besoins.show', $b)->with('succes', "Demande {$b->reference} restaurée.");
        }
        $a = ActionFormation::onlyTrashed()->findOrFail($id);
        $refus = $this->service->restaurerFormation($a, $r->user());

        return redirect()->route('formations.show', $a)->with('succes', "Formation {$a->reference} restaurée.")->with('avertissements', $refus);
    }

    private function trouver(string $type, int $id, User $u): BesoinFormation|ActionFormation|Agent|User
    {
        $objet = match ($type) {
            'besoins' => BesoinFormation::findOrFail($id),
            'formations' => ActionFormation::findOrFail($id),
            'agents' => Agent::findOrFail($id),
            'comptes' => User::findOrFail($id),
            default => abort(404),
        };
        $autorise = match ($type) {
            'besoins' => $u->can('view', $objet) && ($u->aPermission('besoins.creer') || $u->aPermission('besoins.instruire')),
            'formations' => $u->aPermission('actions.gerer'),
            'agents' => $u->aPermission('agents.gerer'),
            'comptes' => $u->aPermission('utilisateurs.gerer'),
            default => false,
        };
        abort_unless($autorise, 403);

        return $objet;
    }

    private function supprimer(string $type, $objet, User $u): void
    {
        match ($type) {
            'besoins' => $this->service->supprimerBesoin($objet, $u),
            'formations' => $this->service->supprimerFormation($objet, $u),
            'agents' => $this->service->supprimerAgent($objet),
            'comptes' => $this->service->supprimerCompte($objet, $u),
            default => abort(404),
        };
    }

    private function retour(string $type): string
    {
        return ['besoins' => 'besoins.index', 'formations' => 'formations.index', 'agents' => 'agents.index', 'comptes' => 'admin.utilisateurs.index'][$type] ?? 'accueil';
    }

    private function nom(string $type, $o): string
    {
        return match ($type) {
            'besoins' => "Demande {$o->reference}",
            'formations' => "Formation {$o->reference}",
            'agents' => "Agent {$o->matricule}",
            'comptes' => "Compte {$o->identifiant}",
            default => 'Élément',
        };
    }
}
