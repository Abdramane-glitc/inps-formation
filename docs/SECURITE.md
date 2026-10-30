# Sécurité

Ce document décrit les mesures implémentées. Il ne constitue **pas** une certification : la conformité aux textes applicables au Mali
(protection des données à caractère personnel) et aux politiques internes de l'INPS doit faire l'objet d'une vérification spécifique,
ainsi qu'un contrôle de sécurité indépendant avant la mise en production.

## Authentification et sessions

| Mesure | Mise en œuvre |
|---|---|
| Authentification native Laravel | `ConnexionController`, mots de passe hachés bcrypt (12 tours en production) |
| Politique de mot de passe | 12 caractères minimum (`PASSWORD_MIN_LENGTH`), majuscule, minuscule, chiffre, symbole ; contrôle des mots de passe compromis activable (`PASSWORD_CHECK_PWNED`, nécessite Internet) |
| Limitation des tentatives | 5 par minute par couple e-mail/IP, 30 par minute par IP (`LOGIN_MAX_ATTEMPTS`) ; 3 demandes de réinitialisation par minute |
| Fixation de session | Régénération de l'identifiant à la connexion ; invalidation à la déconnexion |
| Sessions | Stockées en base, **chiffrées**, expiration après 30 min d'inactivité et à la fermeture du navigateur, cookies `HttpOnly`, `SameSite=Lax`, `Secure` en production |
| Comptes désactivés | Connexion refusée ; session en cours interrompue à la requête suivante ; sessions supprimées lors de la désactivation |
| Récupération du mot de passe | Jeton à usage unique, haché, valable 60 min ; réponse identique que le compte existe ou non ; sans SMTP, réinitialisation par l'administrateur avec mot de passe temporaire à changer |
| Changement de mot de passe | Mot de passe actuel exigé ; autres sessions fermées |
| Aucun compte par défaut | Premier administrateur créé en console (`inps:creer-admin`), mot de passe saisi de façon masquée ; aucun mot de passe codé en dur |

## Contrôle d'accès

- Refus par défaut : un compte sans rôle n'accède à rien.
- Vérification **côté serveur** sur chaque route (`permission:` middleware) et sur chaque objet cloisonné (policies besoins, agents, documents).
- Cloisonnement par service (besoins, agents) ; résultats individuels, motifs d'absence, données contractuelles et documents confidentiels soumis à des permissions dédiées.
- Prévention de l'élévation de privilèges : deux profils fixes (RH, Responsable de structure) ; les rôles ne sont plus modifiables depuis l'application (1.6.0) ; pas de modification de son propre profil ; profil RH attribuable seulement par un détenteur de « Attribuer le profil RH » ; dernier administrateur protégé.
- Cloisonnement par service : un responsable de structure ne voit que les demandes, agents et formations de son service (1.5.1).
- Suppressions limitées aux erreurs de saisie sans suite, journalisées ; demandes et formations en suppression logique (corbeille) ; téléchargement des sauvegardes réservé à la supervision, limité aux fichiers listés et journalisé (1.6.0).
- Séparation des tâches : l'administrateur technique n'a pas d'accès métier par défaut ; le responsable de structure ne peut ni décider des demandes ni organiser les formations.

## Protection des applications web

| Menace | Protection |
|---|---|
| Injection SQL | Requêtes paramétrées (Eloquent / Query Builder) ; aucun SQL concaténé avec des entrées utilisateur ; caractères `%` `_` échappés dans les recherches |
| XSS | Échappement automatique Blade `{{ }}` ; aucune sortie HTML brute de données saisies ; CSP stricte sans script en ligne |
| CSRF | Jeton obligatoire sur tout formulaire `POST/PUT/DELETE` (vérifié : requête sans jeton → HTTP 419) |
| Affectation massive | `$fillable` explicites ; statuts, auteurs, références, rôles et indicateurs d'activité jamais affectables en masse ; détection stricte hors production |
| Clickjacking | `X-Frame-Options: DENY`, `frame-ancestors 'none'` |
| Divers en-têtes | `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `Permissions-Policy`, HSTS en HTTPS, `Cache-Control: no-store` sur les pages authentifiées |
| Redirection ouverte | Les liens des notifications ne redirigent que vers l'application |
| Fuites dans les erreurs | `APP_DEBUG=false` en production : page générique ; détails uniquement dans les journaux serveur |
| Injection de formules Excel | Valeurs commençant par `= + - @` neutralisées dans les exports |

## Fichiers

- Téléversements : extension autorisée **et** type réel détecté par le contenu (finfo) ; taille plafonnée (10 Mo par défaut) ; pas d'exécutables ni de scripts.
- Stockage hors racine web (`storage/app/private`), nom aléatoire (UUID), empreinte SHA-256, droits 0640/0750.
- Téléchargement uniquement via l'application après autorisation ; téléchargements de documents confidentiels journalisés.
- Imports : fichier analysé puis non conservé ; écriture en transaction unique.
- Exports PDF/Excel générés en mémoire, servis en « no-store », journalisés ; PDF produits sans accès réseau (chargement distant désactivé).

## Journal d'audit

- Acteur, date, action, type et identifiant d'objet, contexte strictement nécessaire, IP.
- Nettoyage automatique des clés sensibles (`password`, `token`, `motif_absence`…).
- Non modifiable par l'application (exception levée) **et** par la base : déclencheurs MySQL interdisant `UPDATE` / `DELETE` sur `audit_logs`.
  Si le compte applicatif ne peut pas créer de déclencheurs (journalisation binaire sans privilège SUPER), exécuter `database/sql/protection_audit.sql` avec un compte administrateur MySQL.
  Un administrateur de base disposant des droits `DROP TRIGGER` peut toujours lever cette protection : restreindre ces comptes.

## Secrets et environnements

- Secrets exclusivement dans `.env` (permissions 640, jamais versionné, absent de l'archive livrée) ou dans un gestionnaire de secrets.
- Environnements séparés : `local`, `testing` (base `*_test` obligatoire, contrôlée par les tests), `staging`, `production`.
- Aucune donnée réelle dans le dépôt ou les tests ; données de démonstration fictives, interdites en production.

## Dépendances

- `composer.lock` et `package-lock.json` figent les versions.
- Vérification réalisée : `npm audit` → 0 vulnérabilité. `composer audit` n'a **pas pu** être exécuté dans l'environnement de développement (accès au service Packagist bloqué) : à exécuter avant la mise en production (`composer audit`) puis régulièrement.

## Sauvegardes

Archives chiffrées AES-256 (`BACKUP_PASSWORD`), droits 0600, manifeste d'intégrité SHA-256 vérifié avant restauration. Voir `SAUVEGARDE_RESTAURATION.md`.

## Conservation et suppression des données (à valider par l'INPS)

Proposition : aucune suppression automatique tant que l'INPS n'a pas fixé les durées (Administration → Paramètres → Conservation).
Les agents et comptes sont désactivés, jamais supprimés ; les documents retirés sont conservés jusqu'à application de la politique validée.
Une fois les durées fixées, une procédure d'archivage/purge devra être définie et outillée (évolution à prévoir).

## Contrôles à réaliser avant production

- Test d'intrusion / revue de sécurité indépendante ; analyse de la configuration TLS du serveur.
- `composer audit` ; mises à jour de sécurité du système, de PHP et de MySQL.
- Vérification de la conformité juridique (données personnelles, archivage).

## Revue du 25/10/2026 (version 1.7.3)

Contrôles réellement effectués sur le code de la version 1.7.2, et résultats :

| Contrôle | Résultat |
|---|---|
| Syntaxe PHP de tous les fichiers (`php -l`) | Aucune erreur |
| Analyse statique (PHPStan 2.1.17, niveau 4, sans extension Laravel) | Aucune anomalie réelle ; signalements restants : faux positifs liés aux mécanismes de Laravel et remarques de style sans effet |
| Routes : connexion et droits (`route:list`) | Toutes les routes, sauf connexion, mot de passe oublié, logo/photo et `/up`, exigent une connexion ; droits par permission sur chaque module |
| Contrôle par objet (changer un numéro dans l'adresse) | Demandes, agents, formations, liste PDF, documents, notifications, attestations de l'espace agent : vérifiés (politiques ou contrôles explicites) ; responsable de structure limité à son service |
| Injection SQL | Aucune requête construite avec une donnée saisie ; les fragments SQL bruts sont des textes fixes |
| XSS | Aucun affichage non échappé (`{!! !!}`) ; politique CSP stricte, sans script en ligne (un cas trouvé et corrigé) |
| Fichiers déposés | Type vérifié par le contenu et l'extension, taille plafonnée, stockage hors du dossier public, nom aléatoire, empreinte SHA-256 ; logo et photo limités aux images, SVG servi isolé |
| Redirections | Après connexion : interne ; après notification : renforcée (cas de contournement corrigé) |
| Sessions et authentification | Session régénérée à la connexion, invalidée à la déconnexion, chiffrée, cookie HttpOnly/SameSite, limitation des tentatives (connexion et mot de passe oublié), mots de passe hachés |
| Code de débogage oublié, liens et vues manquants, code mort | Aucun |
| Pages : tous les rôles | Aucune erreur serveur (nouveau test) ; navigateur : 0 erreur JavaScript sur 27 pages |

Limites : l'audit des dépendances (`composer audit`) n'a pas pu être exécuté (accès à Packagist bloqué dans l'environnement de
construction) : à lancer par l'INPS sur le serveur (`composer audit`). Cette revue ne remplace pas le contrôle de sécurité
indépendant prévu avant la mise en production.

## Version 2.5.1 — vérification d'ensemble

- Importation : `IOFactory::createReaderForFile` limité aux lecteurs Xlsx, Xls et Ods (un fichier HTML ou XML déguisé
  est refusé ; voir la faille CVE-2025-54370 du lecteur HTML de PhpSpreadsheet). Test `test_un_faux_classeur_est_refuse`.
- Contrôles réalisés sans autre correction nécessaire : middleware de droits sur chaque route, contrôle objet par objet
  (demandes, formations, documents, convocations, Mon espace), requêtes SQL brutes (aucune donnée saisie insérée),
  absence de sortie Blade non échappée, commandes externes passées en tableau (sauvegarde), téléchargement des
  sauvegardes limité aux fichiers listés, redirections des notifications limitées à l'application, en-têtes de sécurité.
- Dépendances : `npm audit` (0 faille) ; versions PHP du `composer.lock` comparées à la base FriendsOfPHP
  security-advisories (aucune faille connue au 28/10/2026). Relancer `composer audit` sur un poste relié à Internet.
