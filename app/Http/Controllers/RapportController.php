<?php

namespace App\Http\Controllers;

use App\Models\Direction;
use App\Models\Domaine;
use App\Models\Service;
use App\Services\Audit;
use App\Services\ExportService;
use App\Services\RapportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Module L — Rapports et statistiques (écran, impression, PDF, Excel). */
class RapportController extends Controller
{
    public function index(Request $r): View
    {
        return view('rapports.index', ['rapports' => collect(RapportService::RAPPORTS),
            'annee' => (int) $r->query('annee', now()->year)]);
    }

    public function show(Request $r, string $code, RapportService $rapports): View
    {
        $this->verifier($r, $code);
        $f = $this->filtres($r) + ['annee' => (int) now()->year];

        return view('rapports.show', ['code' => $code, 'f' => $f, 'r' => $rapports->generer($code, $f), 'directions' => Direction::actives()->get(),
            'services' => Service::actifs()->get(), 'domaines' => Domaine::actifs()->get(), 'impression' => $r->boolean('imprimer'),
            'institution' => app(ExportService::class)->institution()]);
    }

    public function export(Request $r, string $code, string $format, RapportService $rapports, ExportService $export): Response
    {
        $this->verifier($r, $code);
        $f = $this->filtres($r) + ['annee' => (int) now()->year];
        $res = $rapports->generer($code, $f);
        Audit::enregistrer('export.rapport', 'rapport', "Export $format du rapport « {$res['titre']} »", ['filtres' => $f, 'lignes' => count($res['lignes'])]);
        $nom = 'rapport-'.$code.'-'.($f['annee'] ?? '');

        return $format === 'xlsx'
            ? $export->excel($res['titre'], $res['colonnes'], $res['lignes'], $nom, $res['montants'], $res['note'])
            : $export->pdf('pdf.rapport', ['r' => $res, 'f' => $f], $nom, count($res['colonnes']) > 5 ? 'landscape' : 'portrait');
    }

    private function verifier(Request $r, string $code): void
    {
        abort_unless(array_key_exists($code, RapportService::RAPPORTS), 404);
    }
}
