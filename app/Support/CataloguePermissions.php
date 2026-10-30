<?php

namespace App\Support;

/**
 * Catalogue des permissions applicatives et proposition initiale de rôles.
 * Les rôles sont une PROPOSITION : l'administrateur technique adapte les attributions
 * depuis l'écran « Rôles et permissions » (voir docs/DECISIONS_A_VALIDER.md).
 *
 * Organisation retenue par l'INPS (21/10/2026) : deux profils seulement.
 *  - la RH gère tout : son compte cumule « Responsable DRH / formation » et « Administrateur technique » ;
 *  - le responsable de structure déclare les besoins de son service.
 * Les autres rôles restent disponibles pour une organisation future, sans être attribués.
 *
 * Depuis la version 1.5.0, une formation se déroule en trois étapes (formation, participants, attestations) :
 * plus de présences jour par jour (permissions « presences.* » retirées). Les évaluations reviennent en 1.9.0 sous une
 * forme simple : « evaluations.gerer » (RH) et « evaluations.froid » (responsable de structure).
 * Depuis la version 1.4.0, l'application ne traite plus de budget ni de dépenses (permissions « finances.* » et rôle
 * « Gestionnaire financier » retirés).
 * Depuis la version 1.3.0, il n'y a plus de plan annuel : une demande acceptée est organisée directement
 * en formation. Les permissions techniques « actions.* » couvrent les formations (action + session).
 */
final class CataloguePermissions
{
    /** code => [module, libellé, sensible] */
    public const PERMISSIONS = [
        'tableau_bord.voir' => ['Tableau de bord', 'Consulter le tableau de bord institutionnel', false],

        'agents.voir_tous' => ['Personnel', 'Voir tous les agents', false],
        'agents.voir_service' => ['Personnel', 'Voir les agents de son service', false],
        'agents.gerer' => ['Personnel', 'Créer, modifier et désactiver des agents', true],
        'agents.importer' => ['Personnel', 'Importer des agents et des historiques', true],
        'agents.exporter' => ['Personnel', 'Exporter le référentiel du personnel', true],

        'besoins.voir_tous' => ['Demandes', 'Voir les demandes de tous les services', false],
        'besoins.voir_service' => ['Demandes', 'Voir les demandes de son service', false],
        'besoins.creer' => ['Demandes', 'Faire une demande de formation', false],
        'besoins.creer_tous_services' => ['Demandes', 'Faire une demande pour n\'importe quel service', false],
        'besoins.instruire' => ['Demandes', 'Décider des demandes (accepter et programmer, demander des précisions, refuser)', true],

        'actions.voir' => ['Formations', 'Voir les formations', false],
        'actions.gerer' => ['Formations', 'Créer, modifier, terminer et annuler les formations', true],

        'organismes.voir' => ['Organismes', 'Consulter les organismes et formateurs', false],
        'organismes.gerer' => ['Organismes', 'Gérer les organismes, contacts et formateurs', false],
        'organismes.contrats' => ['Organismes', 'Consulter et gérer les conventions et données contractuelles', true],

        'inscriptions.gerer' => ['Formations', 'Ajouter et retirer les participants', true],

        'attestations.emettre' => ['Formations', 'Émettre et annuler les attestations', true],
        'diplomantes.gerer' => ['Formations', 'Enregistrer et suivre les formations diplômantes des agents (licence, master, doctorat…)', false],

        // 1.9.0 : évaluations à chaud (RH, depuis les fiches papier) et à 3–6 mois (responsable de structure)
        'evaluations.gerer' => ['Évaluations', 'Vérifier en fin de formation que les participants ont compris, et suivre les évaluations à 3–6 mois', false],
        'evaluations.froid' => ['Évaluations', 'Indiquer, 3 à 6 mois après, si les agents de son service appliquent ce qu\'ils ont appris', false],

        'documents.confidentiels' => ['Documents', 'Accéder aux documents confidentiels', true],

        'rapports.voir' => ['Rapports', 'Consulter et exporter les rapports statistiques', false],

        'espace.personnel' => ['Espace agent', 'Consulter ses propres formations et attestations', false],

        'parametres.gerer' => ['Paramétrage', 'Gérer les référentiels et paramètres métier', true],
        'utilisateurs.gerer' => ['Administration', 'Gérer les comptes utilisateurs', true],
        'roles.gerer' => ['Administration', 'Attribuer le profil RH et consulter les droits', true],
        'audit.voir' => ['Administration', 'Consulter le journal d\'audit', true],
        'audit.exporter' => ['Administration', 'Exporter le journal d\'audit', true],
        'systeme.superviser' => ['Administration', 'Superviser l\'état technique (santé, sauvegardes)', true],
    ];

    /** Proposition initiale : code => [nom, description, permissions] */
    public const ROLES = [
        'admin_technique' => ['Administrateur technique', 'Configuration technique, comptes, permissions et supervision.', [
            'utilisateurs.gerer', 'roles.gerer', 'audit.voir', 'audit.exporter', 'systeme.superviser', 'tableau_bord.voir',
        ]],
        'admin_fonctionnel' => ['Administrateur fonctionnel', 'Référentiels, paramètres métier et organisation.', [
            'parametres.gerer', 'tableau_bord.voir', 'agents.voir_tous', 'agents.gerer', 'agents.importer', 'organismes.voir', 'organismes.gerer',
        ]],
        'responsable_drh' => ['Responsable DRH / formation', 'Gestionnaire principal : demandes, organisation des formations, participants, évaluations, attestations, paramètres et rapports.', [
            'tableau_bord.voir', 'agents.voir_tous', 'agents.gerer', 'agents.importer', 'agents.exporter',
            'besoins.voir_tous', 'besoins.creer', 'besoins.creer_tous_services', 'besoins.instruire',
            'actions.voir', 'actions.gerer',
            'organismes.voir', 'organismes.gerer', 'organismes.contrats', 'inscriptions.gerer',
            'attestations.emettre', 'documents.confidentiels', 'rapports.voir',
            'parametres.gerer', 'evaluations.gerer', 'diplomantes.gerer',
        ]],
        'chef_service' => ['Responsable de structure', 'Création et suivi des demandes relevant de son service.', [
            'agents.voir_service', 'besoins.voir_service', 'besoins.creer', 'actions.voir', 'evaluations.froid',
        ]],
        'autorite_validation' => ['Autorité de validation', 'Consultation des demandes, des formations et des rapports.', [
            'tableau_bord.voir', 'besoins.voir_tous', 'actions.voir', 'rapports.voir',
        ]],
        'agent' => ['Agent', 'Consultation de ses propres formations et documents autorisés.', [
            'espace.personnel',
        ]],
        'auditeur' => ['Auditeur / contrôleur', 'Consultation des éléments autorisés, sans modification métier.', [
            'tableau_bord.voir', 'agents.voir_tous', 'besoins.voir_tous', 'actions.voir', 'organismes.voir',
            'rapports.voir', 'audit.voir', 'audit.exporter',
        ]],
    ];
}
