<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit;
use App\Services\ExportService;
use App\Support\JournalLisible;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AuditController extends Controller
{
    private function requete(Request $r)
    {
        $f = $r->validate(['action' => ['nullable', 'string', 'max:80'], 'user_id' => ['nullable', 'integer'], 'objet_type' => ['nullable', 'string', 'max:80'],
            'objet_id' => ['nullable', 'integer'], 'du' => ['nullable', 'date'], 'au' => ['nullable', 'date'],
            'categorie' => ['nullable', Rule::in(array_keys(JournalLisible::CATEGORIES))], 'q' => ['nullable', 'string', 'max:100']]);

        return [$f, AuditLog::with('user')
            ->when($f['categorie'] ?? null, fn ($q, $v) => $q->where(fn ($w) => collect(JournalLisible::prefixes($v))
                ->each(fn ($p) => $w->orWhere('action', 'like', addcslashes($p, '%_').'%'))))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('description', 'like', '%'.addcslashes($v, '%_').'%'))
            ->when($f['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', addcslashes($v, '%_').'%'))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['objet_type'] ?? null, fn ($q, $v) => $q->where('objet_type', $v))
            ->when($f['objet_id'] ?? null, fn ($q, $v) => $q->where('objet_id', $v))
            ->when($f['du'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['au'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')];
    }

    public function index(Request $r): View
    {
        [$f, $q] = $this->requete($r);

        return view('admin.audit.index', ['logs' => $q->paginate(50)->withQueryString(), 'f' => $f,
            'personnes' => User::whereIn('id', AuditLog::select('user_id')->whereNotNull('user_id')->distinct())->orderBy('name')->get(['id', 'name'])]);
    }

    public function export(Request $r, ExportService $export): Response
    {
        [$f, $q] = $this->requete($r);
        $logs = $q->limit(20000)->get();
        Audit::enregistrer('audit.export', 'audit', 'Export du journal d\'audit', ['filtres' => $f, 'lignes' => $logs->count()]);

        return $export->excel('Journal d\'audit', ['Date', 'Qui', 'Ce qui s\'est passé', 'Détail', 'Code technique', 'Objet', 'Identifiant', 'Contexte', 'IP'],
            $logs->map(fn ($l) => [$l->created_at->format('d/m/Y H:i:s'), $l->user?->identifiant ?? 'Système', JournalLisible::libelle($l), $l->description, $l->action, $l->objet_type, $l->objet_id,
                $l->contexte ? json_encode($l->contexte, JSON_UNESCAPED_UNICODE) : null, $l->ip])->all(), 'journal-audit-'.now()->format('Ymd-His'));
    }
}
