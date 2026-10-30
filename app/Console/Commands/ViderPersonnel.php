<?php

namespace App\Console\Commands;

use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\text;

/**
 * Passage aux vraies données (29/10/2026) : vide le référentiel du personnel et la structure (agents, directions, services)
 * sans toucher au reste (comptes RH et administrateurs, formations, organismes, formateurs, paramètres, journal d'audit).
 *
 * Ce qui dépend des agents et des services disparaît avec eux, car il n'a plus de sens sans eux :
 * inscriptions (présences, évaluations, attestations), formations diplômantes, demandes de formation (et leurs décisions et documents).
 * Les comptes d'utilisateurs sont tous conservés tels quels (décision de l'utilisateur, 29/10/2026) : ils perdent seulement
 * leur lien vers un agent, un service ou une direction supprimés (à refaire dans « Utilisateurs »).
 * Tout se fait dans une seule transaction : en cas d'erreur, rien n'est supprimé.
 */
class ViderPersonnel extends Command
{
    protected $signature = 'inps:vider-personnel {--confirmer : ne pas demander de confirmation}';

    protected $description = 'Vider les agents, directions et services (données de test) avant l\'import des vraies listes';

    public function handle(): int
    {
        $agents = DB::table('agents')->pluck('id');
        $inscriptions = DB::table('inscriptions')->pluck('id');
        $besoins = DB::table('besoins_formation')->pluck('id');
        $comptesLies = DB::table('users')->where(fn ($q) => $q->whereNotNull('agent_id')->orWhereNotNull('service_id')->orWhereNotNull('direction_id'))->orderBy('name')->get(['name', 'email']);

        $this->info('Seront supprimés :');
        $this->table(['Élément', 'Nombre'], [
            ['Agents', $agents->count()],
            ['Directions', DB::table('directions')->count()],
            ['Services', DB::table('services')->count()],
            ['Inscriptions aux formations (présences, évaluations comprises)', $inscriptions->count()],
            ['Attestations', DB::table('attestations')->count()],
            ['Formations diplômantes', DB::table('formations_diplomantes')->count()],
            ['Demandes de formation', $besoins->count()],
        ]);
        $this->line('Restent en place : tous les comptes d\'utilisateurs, formations (sans participants), organismes, formateurs, paramètres, journal d\'audit.');
        if ($comptesLies->isNotEmpty()) {
            $this->warn('Ces comptes sont conservés mais perdront leur lien vers un agent, un service ou une direction (à refaire dans Administration → Utilisateurs) :');
            foreach ($comptesLies as $u) {
                $this->line("  - {$u->name} ({$u->email})");
            }
        }
        if (! $this->option('confirmer') && text('Tapez OUI pour supprimer (toute autre réponse annule)') !== 'OUI') {
            $this->info('Annulé : rien n\'a été supprimé.');

            return self::SUCCESS;
        }

        $fichiers = [];
        DB::transaction(function () use ($agents, $inscriptions, $besoins, &$fichiers) {
            $types = ['agent' => $agents, 'inscription' => $inscriptions, 'besoin' => $besoins,
                'attestation' => DB::table('attestations')->pluck('id'), 'diplomante' => DB::table('formations_diplomantes')->pluck('id')];
            foreach ($types as $type => $ids) {
                $docs = DB::table('documents')->where('documentable_type', $type)->whereIn('documentable_id', $ids);
                $fichiers = array_merge($fichiers, (clone $docs)->pluck('chemin')->all());
                $docs->delete();
            }
            DB::table('decisions')->where('decidable_type', 'besoin')->whereIn('decidable_id', $besoins)->delete();
            DB::table('attestations')->delete();
            DB::table('inscriptions')->update(['remplace_par_id' => null]);
            DB::table('inscriptions')->delete();          // présences, évaluations, historique : supprimés en cascade
            DB::table('formations_diplomantes')->delete();
            DB::table('besoins_formation')->delete();     // agents cités : supprimés en cascade ; plan et formations : lien remis à vide
            DB::table('agents')->delete();                // comptes et formateurs : lien remis à vide
            DB::table('services')->delete();
            DB::table('directions')->delete();
            Audit::enregistrer('referentiel.vidage_personnel', null, 'Vidage des agents, directions et services (passage aux vraies données)', [
                'agents' => $agents->count(), 'inscriptions' => $inscriptions->count(), 'demandes' => $besoins->count(),
            ]);
        });
        foreach ($fichiers as $f) {
            Storage::disk('prive')->delete($f);
        }

        $this->info('Terminé. Agents, directions et services vidés ; comptes conservés.');
        $this->line('Importez maintenant les directions, puis le personnel (menu Importations).');

        return self::SUCCESS;
    }
}
