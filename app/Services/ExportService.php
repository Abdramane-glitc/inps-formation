<?php

namespace App\Services;

use App\Models\Parametre;
use App\Support\Identite;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports Excel (PhpSpreadsheet) et PDF (Dompdf). Les fichiers sont générés en mémoire et
 * transmis directement (aucune copie conservée sur le serveur), avec en-têtes « no-store ».
 * Dompdf fonctionne sans accès réseau (chargement distant désactivé).
 */
class ExportService
{
    /** @param list<string> $colonnes @param list<list<mixed>> $lignes @param list<int> $montants index des colonnes monétaires */
    public function excel(string $titre, array $colonnes, array $lignes, string $nomFichier, array $montants = [], ?string $note = null): StreamedResponse
    {
        $ss = new Spreadsheet;
        $ss->getProperties()->setCreator((string) Parametre::valeur('institution.nom', 'INPS'))->setTitle($titre);
        $f = $ss->getActiveSheet();
        $f->setTitle(Str::limit(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $titre), 28, ''));
        $f->setCellValue('A1', $titre);
        $f->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $f->setCellValue('A2', 'Édité le '.now()->format('d/m/Y à H:i').' — '.Parametre::valeur('institution.nom', 'INPS'));
        $ligneEntete = 4;
        foreach ($colonnes as $i => $c) {
            $f->setCellValue([$i + 1, $ligneEntete], $c);
        }
        $derniereCol = Coordinate::stringFromColumnIndex(max(1, count($colonnes)));
        $f->getStyle("A{$ligneEntete}:{$derniereCol}{$ligneEntete}")->getFont()->setBold(true);
        $f->getStyle("A{$ligneEntete}:{$derniereCol}{$ligneEntete}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');
        $r = $ligneEntete + 1;
        foreach ($lignes as $ligne) {
            foreach (array_values($ligne) as $i => $v) {
                // Neutralise l'injection de formules (CSV/Excel injection)
                if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
                    $v = "'".$v;
                }
                $f->setCellValueExplicit([$i + 1, $r], $v, is_int($v) || is_float($v) ? DataType::TYPE_NUMERIC : ($v === null ? DataType::TYPE_NULL : DataType::TYPE_STRING));
            }
            $r++;
        }
        foreach ($montants as $idx) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $f->getStyle("{$col}".($ligneEntete + 1).":{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
        }
        if ($note) {
            $f->setCellValue('A'.($r + 1), $note);
        }
        foreach (range(1, max(1, count($colonnes))) as $i) {
            $f->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        return new StreamedResponse(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($nomFichier).'.xlsx"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function pdfBinaire(string $vue, array $donnees, string $orientation = 'portrait'): string
    {
        // Un PDF de plusieurs dizaines de pages dépasse vite 30 s sur un poste lent (2.3.1)
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $opt = new Options;
        $opt->set('isRemoteEnabled', false);
        $opt->set('isPhpEnabled', false);
        $opt->set('defaultFont', 'DejaVu Sans');
        $opt->setChroot([public_path(), storage_path('app/private/identite')]);
        $pdf = new Dompdf($opt);
        $pdf->loadHtml(view($vue, $donnees + ['institution' => $this->institution()])->render(), 'UTF-8');
        $pdf->setPaper('A4', $orientation);
        $pdf->render();

        return $pdf->output();
    }

    public function pdf(string $vue, array $donnees, string $nomFichier, string $orientation = 'portrait'): Response
    {
        return new Response($this->pdfBinaire($vue, $donnees, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($nomFichier).'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Données d'en-tête institutionnel (logo configurable : aucun logo officiel n'est fourni). */
    public function institution(): array
    {

        return [
            'nom' => Parametre::valeur('institution.nom', 'Institut National de Prévoyance Sociale'),
            'sigle' => Parametre::valeur('institution.sigle', 'INPS'),
            'direction' => Parametre::valeur('institution.direction_rh', 'Direction des Ressources humaines'),
            'adresse' => Parametre::valeur('institution.adresse'),
            'telephone' => Parametre::valeur('institution.telephone'),
            'pied_page' => Parametre::valeur('exports.pied_de_page'),
            'logo_data' => Identite::dataUri('logo'),
            'mention_demo' => app()->environment('production') ? null : 'Environnement de '.app()->environment().' — document non officiel',
        ];
    }
}
