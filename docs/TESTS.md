# Tests

## Exécution

Prérequis : base MySQL **distincte** nommée `inps_formation_test` (le nom doit se terminer par `_test`, sinon les tests s'arrêtent),
accessible avec les identifiants de `.env` ou de `.env.testing` (modèle : `.env.testing.example`).

```bash
php artisan test                         # suite complète
php artisan test tests/Feature/BesoinPlanTest.php
php artisan test --filter=attestation
php artisan test --log-junit rapport-tests.xml
vendor/bin/pint --test                   # contrôle du style de code (Laravel Pint)
```

Le test de sauvegarde/restauration exige `mysqldump`/`mysql` et le droit de créer la base `inps_formation_restore_test`
(`GRANT ALL ON inps_formation_restore_test.* TO '<utilisateur>'@'localhost';`). Il est marqué « ignoré » si `mysqldump` est introuvable.

Les tests utilisent `RefreshDatabase` (migrations rejouées sur base vierge, transaction annulée après chaque test) et uniquement des données fictives.

## Résultats réels de la dernière exécution

Date : 28/10/2026 (version 2.6.0) — Environnement : Ubuntu 24.04, PHP 8.3.6, MySQL 8.0.46, Laravel 13.37.0, PHPUnit 12.5.40.

| Indicateur | Valeur |
|---|---|
| Tests exécutés | **157** |
| Réussis | **157** |
| Échoués | 0 |
| En erreur | 0 |
| Ignorés / non exécutés | 0 |
| Assertions | 1963 |
| Durée | ≈ 70 s |

| Fichier | Tests | Couverture fonctionnelle |
|---|---|---|
| `Unit/FormatTest` | 2 | Formats FCFA, dates, pourcentages |
| `Unit/EnumsTest` | 2 | Libellés des statuts ; cohérence du catalogue rôles/permissions ; absence de rôle universel |
| `Feature/DiplomanteTest` | 3 | Formations diplômantes : saisie, champs obligatoires, référence, date d'obtention, filtres, export Excel, pièce jointe, suppression journalisée, agent non supprimable ; accès refusé au responsable de structure ; « Mon espace » de l'agent ; nouveau nom du profil |
| `Feature/AuthentificationTest` | 14 | Connexion, échec journalisé sans identifiant en clair, compte désactivé (connexion et session en cours), limitation des tentatives, déconnexion, réinitialisation par lien (jeton à usage unique), non-énumération des comptes, absence de SMTP, politique de mot de passe, changement obligatoire, aucun compte par défaut, création du premier administrateur en console |
| `Feature/AutorisationTest` | 15 | Refus par défaut, profil agent, auditeur en lecture seule, cloisonnement des besoins et des agents par service, déclaration limitée à son service, instruction réservée, élévation de privilèges (propres rôles, rôles d'administration, rôle détenu), dernier administrateur protégé, fermeture des sessions, mot de passe temporaire, retrait de permission effectif |
| `Feature/AgentImportTest` | 15 | Faux classeur (HTML renommé en .xlsx) refusé ; Création/modification/unicité du matricule, cohérence service/direction, désactivation logique, recherche multicritère et injection neutralisée, export Excel, import agents (aperçu, doublons fichier/base, erreurs, références inconnues, confirmation, non-modification de l'existant, double confirmation refusée), colonnes manquantes, CSV Windows-1252, Excel, vraies cellules date d'Excel, historique des formations, modèle téléchargeable, import de la structure (feuille active, codes, doublons, direction jamais renommée, rien d'écrit avant confirmation, réservé), personnel limité au service du chef |
| `Feature/BesoinFormationTest` | 11 | Demande via HTTP (agents à cocher obligatoires, agent d'un autre service écarté, nombre calculé et non saisi, envoi direct, service et année automatiques, champs non autorisés ignorés, précisions avec motif et renvoi, refus motivé, acceptation sans dates refusée, décisions tracées) ; retrait limité aux demandes non envoyées ; année automatique ; **« Accepter et programmer »** (réservé à la RH, dates obligatoires, formation créée avec intitulé, raison, organisme et lieu, agents inscrits, statut « Formation programmée », une seule notification, une seule formation par demande) ; demande acceptée avant 1.5.0 ; `besoin_id` ignoré par le formulaire de formation ; formation sans demande ; « Demandes à organiser » et rapport « Suivi des demandes » |
| `Feature/SessionParticipationTest` | 10 | Création d'une formation en un formulaire (validation, référence, année, modalité par défaut, étapes affichées sans convocation ni présences, ancienne adresse redirigée, une seule période), modification et « formation similaire », ajout de participants sans limite de places (agents cochés, tout un service, déjà inscrits signalés, liste PDF), agent inactif / doublon / chevauchement refusés, retrait d'un participant (historique, réinscription), **« Formation terminée »** avec les participants cochés (réservé à la RH, identifiant étranger ignoré, statuts, liste figée), attestations pour ceux qui ont suivi uniquement et correction (refusée tant qu'une attestation est émise), terminer sans participant ou une formation annulée refusé, annulation motivée, routes des étapes retirées absentes |
| `Feature/DocumentTest` | 4 | Dépôt sécurisé (nom aléatoire, empreinte, disque privé), téléchargement autorisé/refusé, fichier déguisé refusé, document confidentiel, justificatif joint par le service demandeur après l'envoi, cloisonnement des documents d'une demande |
| `Feature/AuditSecuriteTest` | 12 | Contenu du journal et nettoyage des secrets, journal non modifiable/supprimable (application **et** déclencheurs MySQL), consultation/export selon droits, XSS, en-têtes de sécurité, présence du middleware CSRF, affectation massive, erreurs sans fuite, redirection ouverte, contrôle de santé |
| `Feature/RapportNotificationTest` | 6 | Indicateurs sans double comptage (agents uniques / inscriptions / participations), absence de chiffres fictifs, génération et export PDF/Excel des 10 rapports, rapports agents formés / sans formation, rappels d'échéances sans doublon (plus de rappel de convocation ; formation passée à marquer terminée), e-mail désactivé / « non remis » sans SMTP / « échec » réel, notifications lues |
| `Feature/InterfaceIdentiteTest` | 7 | Fichiers officiels déposés sur le serveur, page de connexion institutionnelle (intitulés, bascule du mot de passe, absence de « se souvenir de moi »), erreur reliée au champ, logo et photographie absents puis déposés (route publique, types contrôlés), photographie trop petite refusée, menu filtré selon les droits, couleur par défaut et valeur invalide neutralisée |
| `Feature/OrganisationDeuxProfilsTest` | 8 | Plus de plan annuel (permissions, paramètre, routes, menu), la RH accepte et programme seule la formation demandée, la RH administre (comptes, paramètres, système) et plus aucun montant à l'écran (menus, routes, permissions, rôle, rapports), création du compte RH en console (`--drh`), le responsable de structure limité aux demandes et à la consultation des formations, migration 1.3.0 sur une base 1.2.0 (rattachement aux demandes, statuts, séparation d'une action à deux sessions, données du plan conservées) ; migration 1.4.0 (demandes « en examen », permissions et rôle financiers retirés, montants conservés) ; migration 1.5.0 (formations en cours/confirmées → programmées, terminées inchangées, permissions et paramètres retirés, tables conservées) ; permissions exactes du responsable de structure |
| `Feature/ParcoursPagesTest` | 2 | Avec les données de démonstration, toutes les pages (y compris avec identifiant) sans erreur serveur pour la RH, le responsable de structure et l'agent ; Chaque page (sans paramètre) ouverte par chacun des 7 rôles : 200, 302, 403 ou 404, jamais d'erreur serveur |
| `Feature/RechercheTableauBordTest` | 2 | Recherche générale : droits, cloisonnement par service, profil agent, saisie trop courte ou spéciale ; tableau de bord : formations par mois, calcul des évolutions, nouveaux graphiques |
| `Feature/ReferentielTest` | 8 | Type stable de chaque modèle pour le journal, journal robuste à un type manquant, création et modification de chaque référentiel (directions, services, domaines, années, listes) avec journalisation ; rattachement de chaque champ à sa ligne ; suppression d'un élément jamais utilisé ; années simplifiées (année + demandes ouvertes) ; dossier de sauvegarde vide dans le `.env` |
| `Feature/PlanAnnuelTest` | 7 | Validation effacée quand le plan devient vide (et correction des données existantes) ; Ajout, modification, programmation d'une formation prévue (validation des champs, formation rattachée, double programmation refusée, ligne programmée non retirable) ; « Accepter et inscrire au plan » (réservé à la RH, statut « Inscrite au plan », notification, période visible du chef, suppression et seconde acceptation refusées) puis programmation (demande « Formation programmée », agents inscrits) ; suppression / restauration de la formation ; retrait d'une demande du plan avec motif ; validation simple, ajout après validation, exports PDF et Excel ; responsable de structure limité à son service ; reprogrammation après annulation |
| `Feature/SuppressionCorbeilleTest` | 7 | Suppression d'une demande selon son état (auteur / RH, formation programmée refusée) et restauration ; suppression d'une formation créée par erreur (participants libérés, demande remise « Envoyée », exclue des listes et rapports) puis restauration (participants et demande retrouvés) ; reprogrammation après suppression ; formation terminée ou attestée non supprimable ; « Supprimer la sélection » (formations, agents, demandes, raisons des refus, droits) ; compte jamais utilisé seul supprimable ; agent cité dans une demande non supprimable |
| `Feature/SauvegardeRestaurationTest` | 2 | Sauvegarde chiffrée AES-256 réelle, restauration dans une base distincte et comparaison des données, refus avec phrase secrète erronée |

## Autres vérifications réellement exécutées

**Version 1.7.0** — `npm run build` ; `vendor/bin/pint` ; migration `001400` sur une copie d'une base 1.6.1 ; base vierge + démonstration ;
parcours dans un navigateur : plan de l'année, « Accepter et inscrire au plan » sur une demande, « Programmer » la ligne, « Valider le plan »,
vue du responsable de structure ; parcours automatique de tous les liens (RH et responsable de structure) et contrôle qu'aucune donnée d'un autre service
n'apparaît pour le responsable de structure.

**Version 1.6.0** — `npm run build` ; `vendor/bin/pint` ; migrations `001200` et `001300` exécutées sur une copie d'une base 1.5.0 ;
parcours dans un navigateur : demande de 3 agents dont un déjà pris aux mêmes dates → avertissement et encadré sur la fiche ;
écrans Profils et droits, Nouveau compte, Journal, Sauvegardes (sauvegarde réelle faite) ; « Supprimer la sélection » sur les
formations ; corbeille ; parcours automatique de tous les liens (RH et responsable de structure) sans erreur sur la base neuve et la base migrée.

**Version 1.5.0 (formation en trois étapes)** — `npm run build` ; `vendor/bin/pint` ; migration `2026_10_22_001100` exécutée sur une
copie de la base de démonstration 1.4.0 ; base vierge + démonstration ; parcours complet dans un navigateur (Chromium) : le responsable de
structure coche deux agents et envoie la demande, la RH « Accepte et programme » (dates, lieu), décoche un participant, « Formation terminée »,
« Émettre les attestations », puis le responsable de structure voit la formation programmée ; parcours automatique de tous les liens (124 pages RH,
26 pages responsable de structure) sur la base neuve et sur la base 1.4.0 migrée, sans erreur.

**Version 1.4.0 (demandes simplifiées, sans finances)** — `npm run build` ; `vendor/bin/pint` ; migration `2026_10_22_001000`
exécutée sur une copie de la base de démonstration 1.3.0 (dont une demande remise « en examen » pour l'essai) ; base vierge +
démonstration ; parcours dans un navigateur : le responsable de structure remplit et envoie une demande, la RH l'accepte depuis l'encadré
« Prochaine étape » ; captures du formulaire, des fiches et du tableau de bord.

**Version 1.3.0 (parcours simplifié)** — `npm run build` réussi ; `vendor/bin/pint` sur les fichiers modifiés ; migration
`2026_10_21_000900` exécutée sur des copies des bases de démonstration 1.1.0 et 1.2.0 (dont une action à deux sessions ajoutée
pour l'essai), puis annulée et rejouée ; base vierge + démonstration chargées ; parcours complet dans un navigateur (Chromium)
en ne cliquant que sur les boutons « Prochaine étape » : organiser la demande → inscrire les agents de la demande → convoquer →
démarrer → présences des 2 jours → valider le relevé → clôturer → attestations ; captures des écrans RH et responsable de structure.

**Version 1.1.0 (refonte visuelle)** — `npm run build` réussi (Vite 8) ; `vendor/bin/pint` appliqué ; parcours automatisé de
toutes les pages accessibles par les 8 comptes de démonstration (liens internes suivis, aucune erreur HTTP ni exception) ;
captures d'écran contrôlées en 1440 px et 390 px (connexion avec/sans photographie, erreurs, tiroir mobile, menu réduit, menu du compte).

| Vérification | Résultat |
|---|---|
| Migrations sur base vierge (`migrate:fresh --seed`) | Réussi (8 fichiers de migration, 53 tables) |
| Chargement complet du jeu de démonstration (enchaîne tous les services métier) | Réussi |
| Parcours automatisé de toutes les pages accessibles pour chacun des 8 profils (≈ 150 pages pour le profil DRH) | Aucune erreur 500 ; aucun lien affiché vers une page refusée |
| Génération des PDF (plan, émargement, convocations, attestation, historique agent, rapports) et Excel | Fichiers valides (signatures `%PDF` / `PK`) |
| Requête `POST` sans jeton CSRF sur le serveur réel | HTTP 419 (refusée) |
| Accès direct à `/.env` et au stockage privé par URL | HTTP 404 |
| Captures d'écran (Chromium) bureau 1366 px et mobile 390 px | Mise en page correcte, aucune erreur JavaScript en console |
| `php -l` sur tout le code PHP | Aucune erreur |
| Laravel Pint (style PSR-12/Laravel) | Conforme |
| `composer validate` | Valide, `composer.lock` à jour |
| `npm audit` | 0 vulnérabilité |
| `php artisan optimize` (caches de configuration, routes, vues) | Réussi |
| `bash -n deploiement/mettre-a-jour.sh` | Syntaxe valide |

## Non exécuté dans l'environnement de développement (à réaliser par l'INPS)

| Élément | Raison | Action |
|---|---|---|
| `composer audit` | Service Packagist inaccessible depuis l'environnement de construction | Exécuter `composer audit` sur le poste/serveur d'installation |
| Installation par `composer install` depuis Packagist | Même raison : les dépendances ont été récupérées depuis leurs dépôts GitHub officiels aux versions verrouillées | Vérifier `composer install` lors de l'installation (le `composer.lock` pointe vers les sources officielles) |
| Construction et exécution Docker | Démon Docker indisponible | Tester `docker compose up -d --build` en recette |
| Installation sous Windows | Environnement Linux | Suivre `docs/INSTALLATION_WINDOWS.md` sur un poste Windows |
| PHP 8.4 | PHP 8.3.6 disponible | Exécuter `php artisan test` sous PHP 8.4 |
| Envoi réel d'e-mails | Aucun serveur SMTP | Tester avec le serveur de l'INPS |
| Test de charge, test d'intrusion, audit TLS | Hors périmètre automatisé | Contrôles de sécurité finaux avant production |

## Contrôles manuels restant à effectuer

Voir les 50 scénarios de `docs/RECETTE_INPS.md` (notamment l'ergonomie, l'accessibilité clavier, l'impression des documents officiels,
le paramétrage des circuits réels et la restauration sur serveur de recette).
