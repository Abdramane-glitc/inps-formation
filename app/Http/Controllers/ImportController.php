<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Services\Audit;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Importation contrôlée : téléversement → aperçu/validation → confirmation → rapport. */
class ImportController extends Controller
{
    public function index(): View
    {
        return view('imports.index', ['imports' => Import::with('user')->latest()->paginate(20), 'colonnes' => ImportService::COLONNES, 'libelles' => ImportService::LIBELLES]);
    }

    public function modele(string $type): StreamedResponse
    {
        $c = ImportService::COLONNES[$type];
        $exemple = match ($type) {
            'agents' => ['DEMO0001', 'EXEMPLE', 'Prénom fictif', 'DIR-CODE', 'SRV-CODE', 'Fonction', 'titulaire', 'prenom.nom@exemple.invalid', ''],
            'structure' => ['DIR-CODE', 'Nom de la direction', 'SIGLE', 'DIR-CODE-01', 'Nom du service ou de la division', '', ''],
            default => ['DEMO0001', 'Intitulé de la formation', 'externe', '15/03/2024', '17/03/2024', 'achevee', 'DOM-CODE', 'Nom de l\'organisme', 'Bamako', '21'],
        };

        return response()->streamDownload(function () use ($c, $exemple) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, array_merge($c['obligatoires'], $c['facultatives']), ';', '"', '');
            fputcsv($h, $exemple, ';', '"', '');
            fclose($h);
        }, "modele-import-$type.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $r, ImportService $service): RedirectResponse
    {
        $d = $r->validate([
            'type' => ['required', 'in:agents,historique,structure'],
            'fichier' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
        ]);
        abort_if($d['type'] === 'structure' && ! $r->user()->aPermission('parametres.gerer'), 403, 'L\'import de la structure est réservé aux gestionnaires des référentiels.');
        $import = $service->analyser($d['fichier'], $d['type'], $r->user());

        return redirect()->route('imports.show', $import)->with('succes', 'Analyse terminée : vérifiez l\'aperçu avant de confirmer.');
    }

    public function show(Request $r, Import $import): View
    {
        $statut = $r->validate(['statut' => ['nullable', 'in:valide,erreur,doublon,importee']])['statut'] ?? null;

        return view('imports.show', ['import' => $import, 'statut' => $statut,
            'lignes' => $import->lignes()->when($statut, fn ($q) => $q->where('statut', $statut))->paginate(50)->withQueryString()]);
    }

    public function confirmer(Request $r, Import $import, ImportService $service): RedirectResponse
    {
        abort_if($import->type === 'structure' && ! $r->user()->aPermission('parametres.gerer'), 403, 'L\'import de la structure est réservé aux gestionnaires des référentiels.');
        $import = $service->confirmer($import, $r->user());

        return redirect()->route('imports.show', $import)->with($import->statut === 'termine' ? 'succes' : 'erreur', $import->message);
    }

    public function abandonner(Import $import): RedirectResponse
    {
        if ($import->statut === 'analyse') {
            $import->update(['statut' => 'abandonne', 'message' => 'Import abandonné avant écriture.']);
            Audit::enregistrer('import.abandon', $import, 'Abandon d\'un import');
        }

        return redirect()->route('imports.index')->with('succes', 'Import abandonné : aucune donnée n\'a été écrite.');
    }
}
