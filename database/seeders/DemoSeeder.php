<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AnneeReference;
use App\Models\Direction;
use App\Models\Domaine;
use App\Models\Formateur;
use App\Models\FormationDiplomante;
use App\Models\Nomenclature;
use App\Models\Organisme;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\ActionService;
use App\Services\AttestationService;
use App\Services\BesoinService;
use App\Services\GenerateurReference;
use App\Services\InscriptionService;
use App\Services\PlanService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * JEU DE DÉMONSTRATION ENTIÈREMENT FICTIF (noms, matricules, organismes inventés).
 * Interdit en production. Les comptes de démonstration utilisent le mot de passe défini
 * dans la variable d'environnement DEMO_PASSWORD (aucun mot de passe n'est codé en dur).
 *
 *   DEMO_PASSWORD='Un-Mot-De-Passe-Robuste-2026' php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le jeu de démonstration est interdit en production.');
        }
        $mdp = (string) config('inps.demo_mot_de_passe'); // via config : env() renvoie null après « php artisan optimize »
        if (! $mdp || strlen($mdp) < 12) {
            throw new RuntimeException('Définissez DEMO_PASSWORD (12 caractères minimum, majuscule, minuscule, chiffre, symbole) avant de charger la démonstration.');
        }
        if (Agent::where('matricule', 'like', 'DEMO%')->exists()) {
            $this->command?->warn('Données de démonstration déjà présentes : rien à faire.');

            return;
        }
        $this->call(ReferentielInitialSeeder::class);

        // Organisation fictive
        $dirs = [];
        foreach (['DEMO-DP' => 'Direction des Prestations (démo)', 'DEMO-DF' => 'Direction Financière (démo)', 'DEMO-DRH' => 'Direction des Ressources humaines (démo)'] as $c => $n) {
            $dirs[$c] = Direction::create(['code' => $c, 'nom' => $n, 'actif' => true]);
        }
        $srvDef = ['DEMO-SPR' => ['DEMO-DP', 'Service des Pensions (démo)'], 'DEMO-SAF' => ['DEMO-DP', 'Service des Allocations familiales (démo)'],
            'DEMO-SCO' => ['DEMO-DF', 'Service de la Comptabilité (démo)'], 'DEMO-SRE' => ['DEMO-DF', 'Service du Recouvrement (démo)'],
            'DEMO-SFO' => ['DEMO-DRH', 'Service de la Formation (démo)'], 'DEMO-SGP' => ['DEMO-DRH', 'Service de la Gestion du personnel (démo)']];
        $srv = [];
        foreach ($srvDef as $c => [$d, $n]) {
            $srv[$c] = Service::create(['direction_id' => $dirs[$d]->id, 'code' => $c, 'nom' => $n, 'actif' => true]);
        }
        $dom = [];
        foreach (['DEMO-INF' => 'Informatique et outils numériques', 'DEMO-PRE' => 'Réglementation des prestations sociales', 'DEMO-FIN' => 'Gestion financière et comptable',
            'DEMO-MAN' => 'Management et encadrement', 'DEMO-ACC' => 'Accueil et relation usager'] as $c => $n) {
            $dom[$c] = Domaine::create(['code' => $c, 'nom' => $n.' (démo)', 'actif' => true]);
        }
        AnneeReference::create(['annee' => 2025, 'statut' => 'cloturee']);
        AnneeReference::create(['annee' => (int) now()->year, 'statut' => 'ouverte', 'date_limite_besoins' => now()->addDays(10), 'date_cloture' => now()->endOfYear()]);
        AnneeReference::create(['annee' => (int) now()->year + 1, 'statut' => 'ouverte']);

        // 42 agents fictifs
        $noms = ['EXEMPLE', 'FICTIF', 'TESTEUR', 'MODELE', 'ESSAI', 'DEMONSTRA', 'SPECIMEN'];
        $prenoms = ['Awa', 'Moussa', 'Fatoumata', 'Ibrahim', 'Mariam', 'Oumar'];
        $statut = Nomenclature::where('type', 'statut_professionnel')->where('code', 'titulaire')->value('id');
        $codesSrv = array_keys($srv);
        $n = 1;
        foreach ($noms as $nom) {
            foreach ($prenoms as $p) {
                $s = $srv[$codesSrv[$n % count($codesSrv)]];
                $a = new Agent(['matricule' => sprintf('DEMO%04d', $n), 'nom' => $nom, 'prenom' => $p.' (démo)', 'direction_id' => $s->direction_id, 'service_id' => $s->id,
                    'fonction' => ['Agent administratif', 'Chef de section', 'Gestionnaire', 'Comptable', 'Assistant'][$n % 5], 'statut_professionnel_id' => $statut,
                    'email_pro' => sprintf('agent%04d@exemple.invalid', $n), 'source' => 'saisie']);
                $a->save();
                $n++;
            }
        }

        // Comptes fictifs : les deux profils retenus par l'INPS.
        //  - la RH gère tout (Responsable DRH / formation + administrateur technique) ;
        //  - le responsable de structure déclare les besoins de son service. Les agents n'ont pas de compte.
        $profils = [
            'responsable_drh' => ['Démo — Responsable RH', ['responsable_drh', 'admin_technique'], $srv['DEMO-SFO']->id, $dirs['DEMO-DRH']->id],
            'chef_service' => ['Démo — Responsable de structure', ['chef_service'], $srv['DEMO-SPR']->id, $dirs['DEMO-DP']->id],
        ];
        $comptes = [];
        foreach ($profils as $code => [$nom, $roles, $serviceId, $directionId]) {
            $u = new User(['name' => $nom, 'email' => 'demo.'.$code.'@exemple.invalid', 'password' => $mdp, 'service_id' => $serviceId, 'direction_id' => $directionId]);
            $u->actif = true;
            $u->mot_de_passe_modifie_le = now();
            $u->save();
            $u->roles()->attach(Role::whereIn('code', $roles)->pluck('id')->mapWithKeys(fn ($id) => [$id => ['attribue_le' => now()]])->all());
            $comptes[$code] = $u;
        }
        $drh = $comptes['responsable_drh'];
        $chef = $comptes['chef_service'];

        $orgs = [];
        foreach (['Cabinet Démo Formation Conseil', 'Institut Fictif du Numérique', 'Centre Exemple de Management'] as $i => $o) {
            $orgs[] = Organisme::create(['raison_sociale' => $o, 'sigle' => 'DEMO'.($i + 1), 'telephone' => '+223 00 00 00 0'.$i, 'email' => 'contact'.$i.'@exemple.invalid',
                'domaines_intervention' => 'Données fictives de démonstration', 'actif' => true]);
        }
        $fext = Formateur::create(['type' => 'externe', 'organisme_id' => $orgs[1]->id, 'nom' => 'FORMATEUR', 'prenom' => 'Externe (démo)', 'domaines_expertise' => 'Bureautique, outils numériques', 'actif' => true]);
        $agFormateur = Agent::where('service_id', $srv['DEMO-SFO']->id)->first();
        $fint = Formateur::create(['type' => 'interne', 'agent_id' => $agFormateur->id, 'nom' => $agFormateur->nom, 'prenom' => $agFormateur->prenom, 'domaines_expertise' => 'Réglementation des prestations', 'actif' => true]);

        // Demandes du responsable de structure : intitulé, raison, agents à former, période (envoyées directement à la RH)
        $besoins = app(BesoinService::class);
        $act = app(ActionService::class);
        $agentsSpr = Agent::where('service_id', $srv['DEMO-SPR']->id)->orderBy('id')->pluck('id')->all();
        $faits = [];
        foreach ([
            ['Maîtrise du tableur pour le suivi des dossiers', 'Les relevés sont tenus à la main : erreurs et retards (démo).', array_slice($agentsSpr, 0, 6), 'Avant juin',
                ['connaissances'], 'Les agents n\'ont jamais été formés au tableur (démo).', 'Tenir seuls le relevé mensuel dans un tableur, sans erreur (démo).'],
            ['Actualisation de la réglementation des pensions', 'Nouveaux textes à appliquer dans le service (démo).', array_slice($agentsSpr, 0, 4), '2e trimestre',
                ['nouveaute'], 'Les nouveaux textes n\'ont pas encore été présentés aux agents (démo).', 'Appliquer les nouveaux textes au calcul des pensions (démo).'],
            ['Techniques d\'accueil et de gestion des réclamations', 'Hausse des réclamations au guichet (démo).', array_slice($agentsSpr, 2, 3), 'Dès que possible',
                ['connaissances', 'effectif'], 'Les agents d\'accueil n\'ont pas de méthode pour traiter une réclamation (démo).', 'Traiter une réclamation selon une méthode commune (démo).'],
            ['Initiation au management d\'équipe', 'Nouveaux chefs de section (démo).', array_slice($agentsSpr, 5, 2), null,
                ['organisation'], 'Les nouveaux chefs de section n\'ont jamais encadré d\'équipe (démo).', 'Répartir le travail et conduire une réunion d\'équipe (démo).'],
        ] as [$int, $pourquoi, $agentsDemande, $periode, $causes, $justif, $resultat]) {
            // Diagnostic (2.1.0) déduit des causes de l'exemple : savoir-faire manquant sauf pour une cause d'organisation seule
            $diag = in_array('organisation', $causes, true) && count($causes) === 1 ? ['oui', 'non', 'oui'] : ['non', 'oui', in_array('effectif', $causes, true) ? 'non' : 'oui'];
            $faits[] = $besoins->creer(['intitule' => $int, 'contexte' => $pourquoi, 'periode_souhaitee' => $periode,
                'diag_savoir' => $diag[0], 'diag_moyens' => $diag[1], 'diag_temps' => $diag[2], 'gravite' => 'reguliere',
                'situation_actuelle' => 'Situation constatée (démo)', 'objectif_vise' => 'Objectif visé (démo)', 'solutions_essayees' => ['aucune'],
                'justification_formation' => $justif, 'resultat_attendu' => $resultat], $chef, $agentsDemande);
        }

        // 1. Demande acceptée et programmée (formation passée, terminée, attestations émises)
        $debut = now()->subDays(20)->startOfWeek();
        $a1 = $act->accepterEtProgrammer($faits[0]->fresh(), ['date_debut' => $debut->toDateString(), 'date_fin' => $debut->copy()->addDays(2)->toDateString(),
            'lieu' => 'Salle de formation (démo)', 'organisme_id' => $orgs[1]->id], $drh, 'Pertinent pour le service (démo).');
        $s1 = $a1->session()->first();
        $participants = $s1->participantsActifs()->orderBy('id')->get();
        $act->terminer($s1, $participants->slice(0, 5)->pluck('id')->all(), $drh); // le 6e agent n'a pas suivi la formation
        $att = app(AttestationService::class);
        foreach ($s1->inscriptions()->where('resultat', 'achevee')->get() as $i) {
            $att->emettre($i, $drh);
        }

        // 2. Demande acceptée et programmée (formation à venir) ; un agent d'un autre service est ajouté par la RH
        $d2 = now()->addDays(5)->startOfWeek()->addWeek();
        $a2 = $act->accepterEtProgrammer($faits[1]->fresh(), ['date_debut' => $d2->toDateString(), 'date_fin' => $d2->copy()->addDay()->toDateString(),
            'lieu' => 'Siège (démo)'], $drh);
        app(InscriptionService::class)->inscrire($a2->session()->first(), Agent::where('service_id', $srv['DEMO-SAF']->id)->limit(1)->pluck('id')->all(), $drh);

        // 3. Demande envoyée, en attente de décision ; 4. demande renvoyée au chef pour précisions
        $besoins->transition($faits[3], 'demande_complement', $drh, 'Préciser la période souhaitée (démo).');

        // Formation décidée par la RH, sans demande d'un service
        $d3 = now()->addMonth()->startOfWeek();
        $act->creerFormation(['intitule' => 'Atelier : sécurité des postes de travail (démo)', 'domaine_id' => $dom['DEMO-INF']->id, 'type' => 'externe',
            'organisme_id' => $orgs[0]->id, 'date_debut' => $d3->toDateString(), 'date_fin' => $d3->toDateString(), 'lieu' => 'En ligne'], $drh);

        // Plan annuel (1.7.0) : deux formations prévues, une programmée depuis le plan
        $plan = app(PlanService::class);
        $annee = (int) now()->year;
        $plan->ajouterLigne($annee, ['intitule' => 'Accueil et relation avec les assurés (démo)', 'periode' => 'T4', 'service_id' => $srv['DEMO-SAF']->id, 'nb_agents' => 8, 'type' => 'interne'], $drh);
        $plan->ajouterLigne($annee, ['intitule' => 'Gestion du temps et des priorités (démo)', 'periode' => 'annee', 'nb_agents' => 15], $drh);
        $l = $plan->ajouterLigne($annee, ['intitule' => 'Archivage numérique (démo)', 'periode' => 'M'.now()->addMonths(1)->format('m'), 'nb_agents' => 6, 'type' => 'externe', 'organisme_id' => $orgs[0]->id], $drh);
        $d4 = now()->addMonth()->startOfWeek()->addWeek();
        $plan->programmer($l, ['date_debut' => $d4->toDateString(), 'date_fin' => $d4->toDateString(), 'lieu' => 'Siège (démo)'], $drh);

        // Formations diplômantes (2.5.0) : établissements fictifs, aucun montant
        foreach ([[$agentsSpr[0], 'master', 'Gestion des ressources humaines (démo)', 'Université Exemple de Bamako', $annee - 1, 2, 'cours_du_soir', 'inps_partielle', 'en_cours', null],
            [$agentsSpr[1], 'licence', 'Comptabilité et finance (démo)', 'Institut Fictif de Gestion', $annee - 4, 3, 'presentiel', 'agent', 'obtenu', ($annee - 1).'-07-15']] as [$ag, $niv, $int, $etab, $an, $duree, $mode, $pec, $sit, $obt]) {
            $fd = new FormationDiplomante(['agent_id' => $ag, 'niveau' => $niv, 'intitule' => $int, 'etablissement' => $etab, 'ville' => 'Bamako', 'pays' => 'Mali',
                'annee_debut' => $an, 'duree_annees' => $duree, 'mode' => $mode, 'prise_en_charge' => $pec, 'situation' => $sit, 'date_obtention' => $obt]);
            $fd->reference = GenerateurReference::suivante('formations_diplomantes', 'reference', 'DIP', $an);
            $fd->cree_par = $drh->id;
            $fd->save();
        }

        $this->command?->info('Démonstration chargée. Comptes : demo.<code_role>@exemple.invalid (mot de passe : DEMO_PASSWORD).');
        $this->command?->table(['Rôle', 'Identifiant'], collect($comptes)->map(fn ($u, $c) => [$c, $u->email])->values()->all());
    }
}
