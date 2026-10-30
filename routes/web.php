<?php

use App\Http\Controllers\AccesAgentController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ParametreController;
use App\Http\Controllers\Admin\ReferentielController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemeController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ApresFormationController;
use App\Http\Controllers\AttestationController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\MotDePasseController;
use App\Http\Controllers\BesoinController;
use App\Http\Controllers\ConvocationController;
use App\Http\Controllers\DiplomanteController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EspaceAgentController;
use App\Http\Controllers\FormateurController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\IdentiteController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganismeController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\RechercheController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SuppressionController;
use App\Http\Controllers\TableauBordController;
use App\Models\Document;
use Illuminate\Support\Facades\Route;

/*
| Toutes les routes métier exigent une session authentifiée ; chaque groupe exige en plus
| une permission vérifiée côté serveur (middleware « permission ») et, pour les objets
| cloisonnés, une policy dans le contrôleur.
*/

// Logo et photographie institutionnels (publics : affichés sur la page de connexion)
Route::get('/identite/{type}', [IdentiteController::class, 'fichier'])->whereIn('type', ['logo', 'photo'])->name('identite.fichier');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('login');
    Route::post('/connexion', [ConnexionController::class, 'store'])->middleware('throttle:connexion')->name('login.store');
    // Activation d'un compte d'agent avec son code (2.4.0)
    Route::get('/activation', [ActivationController::class, 'create'])->name('activation');
    Route::post('/activation', [ActivationController::class, 'store'])->middleware('throttle:activation')->name('activation.store');
    Route::get('/mot-de-passe-oublie', [MotDePasseController::class, 'demande'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [MotDePasseController::class, 'envoyerLien'])->middleware('throttle:reinitialisation')->name('password.email');
    Route::get('/reinitialisation/{token}', [MotDePasseController::class, 'formulaireReinitialisation'])->name('password.reset');
    Route::post('/reinitialisation', [MotDePasseController::class, 'reinitialiser'])->middleware('throttle:reinitialisation')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('logout');
    Route::get('/mon-mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
    Route::put('/mon-mot-de-passe', [MotDePasseController::class, 'update'])->name('mot-de-passe.update');

    Route::get('/', [TableauBordController::class, 'accueil'])->name('accueil');
    Route::get('/tableau-de-bord', [TableauBordController::class, 'index'])->middleware('permission:tableau_bord.voir')->name('tableau-bord');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::get('/recherche', [RechercheController::class, 'index'])->middleware('throttle:60,1')->name('recherche');
    Route::post('/notifications/{id}/lue', [NotificationController::class, 'lue'])->name('notifications.lue');
    Route::post('/notifications/tout-lire', [NotificationController::class, 'toutLire'])->name('notifications.tout-lire');

    Route::middleware('permission:espace.personnel')->group(function () {
        Route::get('/mon-espace', [EspaceAgentController::class, 'index'])->name('espace.index');
        // 28/10/2026 : l'agent ne télécharge plus son attestation (remise par la RH) ; il voit seulement qu'elle est délivrée
        Route::get('/mon-espace/convocations/{inscription}', [ConvocationController::class, 'agent'])->name('espace.convocation');
    });

    // Accès des agents à l'application (2.3.0) — avant /agents/{agent}
    Route::middleware('permission:utilisateurs.gerer')->group(function () {
        Route::get('/agents/acces', [AccesAgentController::class, 'index'])->name('agents.acces');
        Route::post('/agents/acces', [AccesAgentController::class, 'generer'])->name('agents.acces.generer');
        Route::get('/agents/acces/codes.pdf', [AccesAgentController::class, 'liste'])->name('agents.acces.liste');
        Route::post('/agents/{agent}/acces/nouveau-code', [AccesAgentController::class, 'nouveauCode'])->name('agents.acces.nouveau-code');
    });

    // Module B — Référentiel du personnel
    Route::middleware('permission:agents.voir_tous|agents.voir_service')->group(function () {
        Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
        Route::get('/agents/choisir', [AgentController::class, 'choisir'])->name('agents.choisir'); // recherche des formulaires (29/10/2026)
        Route::get('/agents/export', [AgentController::class, 'export'])->middleware('permission:agents.exporter')->name('agents.export');
        Route::get('/agents/creer', [AgentController::class, 'create'])->middleware('permission:agents.gerer')->name('agents.create');
        Route::post('/agents', [AgentController::class, 'store'])->middleware('permission:agents.gerer')->name('agents.store');
        Route::get('/agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
        Route::get('/agents/{agent}/historique.pdf', [AgentController::class, 'historiquePdf'])->name('agents.historique-pdf');
        Route::get('/agents/{agent}/modifier', [AgentController::class, 'edit'])->middleware('permission:agents.gerer')->name('agents.edit');
        Route::put('/agents/{agent}', [AgentController::class, 'update'])->middleware('permission:agents.gerer')->name('agents.update');
        Route::post('/agents/{agent}/activation', [AgentController::class, 'activation'])->middleware('permission:agents.gerer')->name('agents.activation');
    });
    Route::middleware('permission:agents.importer')->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/imports/modele/{type}', [ImportController::class, 'modele'])->whereIn('type', ['agents', 'historique', 'structure'])->name('imports.modele');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::post('/imports/{import}/confirmer', [ImportController::class, 'confirmer'])->name('imports.confirmer');
        Route::post('/imports/{import}/abandonner', [ImportController::class, 'abandonner'])->name('imports.abandonner');
    });

    // Module C — Besoins (cloisonnement par policy)
    Route::middleware('permission:besoins.voir_tous|besoins.voir_service')->group(function () {
        Route::get('/besoins', [BesoinController::class, 'index'])->name('besoins.index');
        Route::get('/besoins/creer', [BesoinController::class, 'create'])->name('besoins.create');
        Route::post('/besoins', [BesoinController::class, 'store'])->name('besoins.store');
        Route::get('/besoins/{besoin}', [BesoinController::class, 'show'])->name('besoins.show');
        Route::get('/besoins/{besoin}/modifier', [BesoinController::class, 'edit'])->name('besoins.edit');
        Route::put('/besoins/{besoin}', [BesoinController::class, 'update'])->name('besoins.update');
        Route::delete('/besoins/{besoin}', [BesoinController::class, 'destroy'])->name('besoins.destroy');
        Route::post('/besoins/{besoin}/transition', [BesoinController::class, 'transition'])->name('besoins.transition');
        Route::post('/besoins/{besoin}/accepter', [BesoinController::class, 'accepter'])->middleware('permission:actions.gerer')->name('besoins.accepter');
    });

    // Formations (depuis 1.3.0 : plus de plan annuel ; une formation = une action + une session)
    Route::middleware('permission:actions.voir')->group(function () {
        Route::get('/formations', [FormationController::class, 'index'])->name('formations.index');
        Route::get('/formations/creer', [FormationController::class, 'create'])->middleware('permission:actions.gerer')->name('formations.create');
        Route::post('/formations', [FormationController::class, 'store'])->middleware('permission:actions.gerer')->name('formations.store');
        Route::get('/formations/{formation}', [FormationController::class, 'show'])->name('formations.show');
        Route::get('/formations/{formation}/modifier', [FormationController::class, 'edit'])->middleware('permission:actions.gerer')->name('formations.edit');
        Route::put('/formations/{formation}', [FormationController::class, 'update'])->middleware('permission:actions.gerer')->name('formations.update');

        // Parcours simplifié (1.5.0) : participants puis fin de formation
        Route::post('/formations/{formation}/participants', [FormationController::class, 'ajouterParticipants'])->middleware('permission:inscriptions.gerer')->name('formations.participants');
        Route::post('/participants/{inscription}/retrait', [FormationController::class, 'retirerParticipant'])->middleware('permission:inscriptions.gerer')->name('formations.participants.retirer');
        Route::post('/formations/{formation}/terminer', [FormationController::class, 'terminer'])->middleware('permission:actions.gerer')->name('formations.terminer');
        Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show'); // ancienne adresse → fiche formation
        Route::get('/sessions/{session}/emargement.pdf', [SessionController::class, 'emargement'])->name('sessions.emargement');
        // Convocations (2.3.0)
        Route::post('/formations/{formation}/convocation', [ConvocationController::class, 'convoquer'])->middleware('permission:inscriptions.gerer')->name('formations.convoquer');
        Route::get('/formations/{formation}/convocations.pdf', [ConvocationController::class, 'pdf'])->middleware('permission:inscriptions.gerer')->name('formations.convocations');
        Route::post('/sessions/{session}/annulation', [SessionController::class, 'annuler'])->middleware('permission:actions.gerer')->name('sessions.annuler');
    });

    // Après la formation (1.9.0) : évaluations à chaud et à 3–6 mois, rapport de réalisation, attestation d'intervention
    Route::middleware('permission:evaluations.gerer')->group(function () {
        Route::get('/formations/{formation}/evaluation-chaud', [ApresFormationController::class, 'chaud'])->name('evaluations.chaud');
        Route::post('/formations/{formation}/evaluation-chaud', [ApresFormationController::class, 'enregistrerChaud'])->name('evaluations.chaud.enregistrer');
    });
    Route::middleware('permission:evaluations.gerer|evaluations.froid')->group(function () {
        Route::get('/evaluations', [ApresFormationController::class, 'index'])->name('evaluations.index');
        Route::get('/formations/{formation}/evaluation-froid', [ApresFormationController::class, 'froid'])->name('evaluations.froid');
        Route::post('/formations/{formation}/evaluation-froid', [ApresFormationController::class, 'enregistrerFroid'])->name('evaluations.froid.enregistrer');
    });
    Route::middleware('permission:actions.gerer')->group(function () {
        Route::post('/formations/{formation}/delai-evaluation', [ApresFormationController::class, 'delaiFroid'])->name('evaluations.delai');
        Route::get('/formations/{formation}/rapport-realisation', [ApresFormationController::class, 'rapport'])->name('rapport-realisation.edit');
        Route::put('/formations/{formation}/rapport-realisation', [ApresFormationController::class, 'enregistrerRapport'])->name('rapport-realisation.update');
        Route::get('/formations/{formation}/rapport-realisation.pdf', [ApresFormationController::class, 'rapportPdf'])->name('rapport-realisation.pdf');
    });
    // Formations diplômantes des agents (2.5.0) — RH seulement
    Route::middleware('permission:diplomantes.gerer')->group(function () {
        Route::get('/formations-diplomantes', [DiplomanteController::class, 'index'])->name('diplomantes.index');
        Route::get('/formations-diplomantes/export', [DiplomanteController::class, 'export'])->name('diplomantes.export');
        Route::get('/formations-diplomantes/creer', [DiplomanteController::class, 'create'])->name('diplomantes.create');
        Route::post('/formations-diplomantes', [DiplomanteController::class, 'store'])->name('diplomantes.store');
        Route::get('/formations-diplomantes/{diplomante}', [DiplomanteController::class, 'show'])->whereNumber('diplomante')->name('diplomantes.show');
        Route::get('/formations-diplomantes/{diplomante}/modifier', [DiplomanteController::class, 'edit'])->name('diplomantes.edit');
        Route::put('/formations-diplomantes/{diplomante}', [DiplomanteController::class, 'update'])->name('diplomantes.update');
        Route::delete('/formations-diplomantes/{diplomante}', [DiplomanteController::class, 'destroy'])->name('diplomantes.destroy');
    });
    Route::middleware('permission:attestations.emettre')->group(function () {
        Route::post('/formations/{formation}/formateurs/{formateur}/attestation', [ApresFormationController::class, 'attester'])->name('attestations-intervenants.emettre');
        Route::get('/attestations-intervenants/{attestation}/pdf', [ApresFormationController::class, 'pdfIntervenant'])->name('attestations-intervenants.pdf');
    });

    // Attestations
    Route::middleware('permission:attestations.emettre')->group(function () {
        Route::get('/attestations', [AttestationController::class, 'index'])->name('attestations.index');
        Route::post('/inscriptions/{inscription}/attestation', [AttestationController::class, 'store'])->name('attestations.store');
        Route::post('/sessions/{session}/attestations', [AttestationController::class, 'emettreSession'])->name('attestations.session');
        Route::get('/attestations/{attestation}/pdf', [AttestationController::class, 'pdf'])->name('attestations.pdf');
        Route::post('/attestations/{attestation}/annulation', [AttestationController::class, 'annuler'])->name('attestations.annuler');
    });

    // Module F — Organismes et formateurs
    Route::middleware('permission:organismes.voir')->group(function () {
        Route::get('/organismes', [OrganismeController::class, 'index'])->name('organismes.index');
        Route::get('/organismes/creer', [OrganismeController::class, 'create'])->middleware('permission:organismes.gerer')->name('organismes.create');
        Route::post('/organismes', [OrganismeController::class, 'store'])->middleware('permission:organismes.gerer')->name('organismes.store');
        Route::get('/organismes/{organisme}', [OrganismeController::class, 'show'])->name('organismes.show');
        Route::get('/organismes/{organisme}/modifier', [OrganismeController::class, 'edit'])->middleware('permission:organismes.gerer')->name('organismes.edit');
        Route::put('/organismes/{organisme}', [OrganismeController::class, 'update'])->middleware('permission:organismes.gerer')->name('organismes.update');
        Route::post('/organismes/{organisme}/contacts', [OrganismeController::class, 'ajouterContact'])->middleware('permission:organismes.gerer')->name('organismes.contacts.store');
        Route::delete('/contacts/{contact}', [OrganismeController::class, 'retirerContact'])->middleware('permission:organismes.gerer')->name('organismes.contacts.destroy');
        Route::post('/organismes/{organisme}/conventions', [OrganismeController::class, 'ajouterConvention'])->middleware('permission:organismes.contrats')->name('organismes.conventions.store');
        Route::post('/organismes/{organisme}/appreciations', [OrganismeController::class, 'apprecier'])->middleware('permission:organismes.gerer')->name('organismes.appreciations.store');
        Route::get('/formateurs', [FormateurController::class, 'index'])->name('formateurs.index');
        Route::get('/formateurs/creer', [FormateurController::class, 'create'])->middleware('permission:organismes.gerer')->name('formateurs.create');
        Route::post('/formateurs', [FormateurController::class, 'store'])->middleware('permission:organismes.gerer')->name('formateurs.store');
        Route::get('/formateurs/{formateur}/modifier', [FormateurController::class, 'edit'])->middleware('permission:organismes.gerer')->name('formateurs.edit');
        Route::put('/formateurs/{formateur}', [FormateurController::class, 'update'])->middleware('permission:organismes.gerer')->name('formateurs.update');
    });

    // Module K — Documents (autorisation par policy, jamais d'URL publique)
    Route::post('/documents/{type}/{id}', [DocumentController::class, 'store'])->whereIn('type', array_keys(Document::RATTACHEMENTS))->whereNumber('id')->name('documents.store');
    Route::get('/documents/{document}/telecharger', [DocumentController::class, 'telecharger'])->name('documents.telecharger');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Module L — Rapports
    Route::middleware('permission:rapports.voir')->group(function () {
        Route::get('/rapports', [RapportController::class, 'index'])->name('rapports.index');
        Route::get('/rapports/{code}', [RapportController::class, 'show'])->name('rapports.show');
        Route::get('/rapports/{code}/export/{format}', [RapportController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('rapports.export');
    });

    // Plan annuel simple (1.7.0) : consultation (RH ; responsable de structure limité à son service), gestion par la RH
    Route::middleware('permission:actions.voir')->get('/plan-annuel', [PlanController::class, 'index'])->name('plan.index');
    Route::middleware('permission:actions.voir')->get('/plan-annuel/{annee}/export/{format}', [PlanController::class, 'export'])->where('annee', '20[0-9]{2}|2100')->whereIn('format', ['xlsx', 'pdf'])->name('plan.export');
    Route::middleware('permission:actions.gerer')->group(function () {
        Route::post('/plan-annuel/lignes', [PlanController::class, 'store'])->name('plan.lignes.store');
        Route::put('/plan-annuel/lignes/{ligne}', [PlanController::class, 'update'])->name('plan.lignes.update');
        Route::delete('/plan-annuel/lignes/{ligne}', [PlanController::class, 'destroy'])->name('plan.lignes.destroy');
        Route::post('/plan-annuel/lignes/{ligne}/programmation', [PlanController::class, 'programmer'])->name('plan.lignes.programmer');
        Route::post('/plan-annuel/{annee}/validation', [PlanController::class, 'validerPlan'])->where('annee', '20[0-9]{2}|2100')->name('plan.valider');
        Route::post('/besoins/{besoin}/plan', [PlanController::class, 'inscrireDemande'])->name('besoins.plan');
    });

    // Suppression des erreurs de saisie et corbeille (1.6.0) — droits vérifiés dans le contrôleur selon le type
    Route::delete('/suppression/{type}/{id}', [SuppressionController::class, 'unique'])->whereIn('type', SuppressionController::TYPES)->whereNumber('id')->name('suppression.unique');
    Route::post('/suppression/{type}', [SuppressionController::class, 'selection'])->whereIn('type', SuppressionController::TYPES)->name('suppression.selection');
    Route::middleware('permission:actions.gerer')->group(function () {
        Route::get('/corbeille', [SuppressionController::class, 'corbeille'])->name('corbeille.index');
        Route::post('/corbeille/{type}/{id}/restauration', [SuppressionController::class, 'restaurer'])->whereIn('type', ['besoins', 'formations'])->whereNumber('id')->name('corbeille.restaurer');
    });

    // Administration et paramétrage (Module N, section 5)
    Route::prefix('administration')->name('admin.')->group(function () {
        Route::middleware('permission:utilisateurs.gerer')->group(function () {
            Route::get('/utilisateurs', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
            Route::get('/utilisateurs/creer', [UtilisateurController::class, 'create'])->name('utilisateurs.create');
            Route::post('/utilisateurs', [UtilisateurController::class, 'store'])->name('utilisateurs.store');
            Route::get('/utilisateurs/{utilisateur}/modifier', [UtilisateurController::class, 'edit'])->name('utilisateurs.edit');
            Route::put('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update'])->name('utilisateurs.update');
            Route::post('/utilisateurs/{utilisateur}/activation', [UtilisateurController::class, 'activation'])->name('utilisateurs.activation');
            Route::post('/utilisateurs/{utilisateur}/reinitialiser', [UtilisateurController::class, 'reinitialiserMotDePasse'])->name('utilisateurs.reinitialiser');
        });
        Route::middleware('permission:roles.gerer')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index'); // « Profils et droits », en lecture seule (1.6.0)
        });
        Route::middleware('permission:audit.voir')->group(function () {
            Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
            Route::get('/audit/export', [AuditController::class, 'export'])->middleware('permission:audit.exporter')->name('audit.export');
        });
        Route::middleware('permission:systeme.superviser')->group(function () {
            Route::get('/systeme', [SystemeController::class, 'index'])->name('systeme.index');
            Route::post('/systeme/sauvegarde', [SystemeController::class, 'sauvegarder'])->name('systeme.sauvegarde');
            Route::get('/systeme/sauvegardes/{fichier}', [SystemeController::class, 'telecharger'])->where('fichier', 'inps-formation-[0-9]{8}-[0-9]{6}\.zip')->name('systeme.telecharger');
        });
        Route::middleware('permission:parametres.gerer')->group(function () {
            Route::get('/parametres', [ParametreController::class, 'index'])->name('parametres.index');
            Route::put('/parametres', [ParametreController::class, 'update'])->name('parametres.update');
            Route::get('/referentiels/{type}', [ReferentielController::class, 'index'])->name('referentiels.index');
            Route::post('/referentiels/{type}', [ReferentielController::class, 'store'])->name('referentiels.store');
            Route::put('/referentiels/{type}/{id}', [ReferentielController::class, 'update'])->whereNumber('id')->name('referentiels.update');
            Route::delete('/referentiels/{type}/{id}', [ReferentielController::class, 'destroy'])->whereNumber('id')->name('referentiels.destroy');
        });
    });
});
