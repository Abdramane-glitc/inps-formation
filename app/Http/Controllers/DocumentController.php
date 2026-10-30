<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Policies\DocumentPolicy;
use App\Services\Audit;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Module K — Dépôt et téléchargement contrôlés (aucune URL publique). */
class DocumentController extends Controller
{
    public function store(Request $r, string $type, int $id, DocumentService $service, DocumentPolicy $policy): RedirectResponse
    {
        $classe = Document::RATTACHEMENTS[$type];
        $objet = $classe::findOrFail($id);
        abort_unless($policy->peutGererObjet($r->user(), $objet), 403);
        $d = $r->validate([
            'fichier' => ['required', 'file', 'max:'.DocumentService::tailleMaxKo(), 'extensions:'.implode(',', array_keys(DocumentService::TYPES_AUTORISES))],
            'titre' => ['nullable', 'string', 'max:255'],
            'categorie_id' => ['nullable', 'integer', Rule::exists('nomenclatures', 'id')->where('type', 'categorie_document')],
            'confidentiel' => ['nullable', 'boolean'],
        ]);
        if ($r->boolean('confidentiel') && ! $r->user()->aPermission('documents.confidentiels')) {
            abort(403, 'Vous ne pouvez pas déposer de document confidentiel.');
        }
        $service->deposer($objet, $d['fichier'], ['titre' => $d['titre'] ?? null, 'categorie_id' => $d['categorie_id'] ?? null, 'confidentiel' => $r->boolean('confidentiel')], $r->user());

        return back()->with('succes', 'Document déposé.');
    }

    public function telecharger(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_unless(Storage::disk('prive')->exists($document->chemin), 404);
        if ($document->confidentiel) {
            Audit::enregistrer('document.telechargement_confidentiel', $document, "Téléchargement du document confidentiel « {$document->titre} »");
        }

        return Storage::disk('prive')->download($document->chemin, $document->nom_original, [
            'Content-Type' => $document->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private',
        ]);
    }

    public function destroy(Document $document, DocumentService $service): RedirectResponse
    {
        $this->authorize('delete', $document);
        $service->retirer($document);

        return back()->with('succes', 'Document retiré.');
    }
}
