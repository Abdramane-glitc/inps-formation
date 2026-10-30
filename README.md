# Gestion des formations du personnel — INPS (Mali)

Application web de gestion globale des formations du personnel de l'Institut National de Prévoyance Sociale :
demandes de formation, programmation des formations à l'acceptation des demandes, participants,
attestations, documents, rapports, notifications et journal d'audit.

> **Statut : version 2.6.0 (nouveau design et recherche générale ; nouvelle page de connexion ; formations diplômantes ; profil « Responsable de structure » ; accès des agents par code d'activation ; accès des agents par matricule, convocations ; évaluations simplifiées : ont-ils compris ? appliquent-ils ? ; analyse du besoin revue, aide à la décision, suppression des référentiels inutilisés ; droits personnalisables par compte ; évaluations à chaud et à 3–6 mois, rapport de réalisation, attestation du prestataire ; analyse du besoin dans la demande ; formateurs internes et externes ; choix direction puis service ; plan annuel simple ; import de la structure de l'INPS ; deux profils fixes RH / responsable de structure, journal en clair, suppression des erreurs et corbeille ; accepter une demande = programmer la formation ; formation en trois étapes : formation, participants, attestations ; sans budget ni plan annuel) — prête à être présentée pour recette.** La mise en production reste conditionnée
> à la configuration du serveur, à la validation des règles institutionnelles (`docs/DECISIONS_A_VALIDER.md`),
> à la recette par l'INPS (`docs/RECETTE_INPS.md`) et aux contrôles de sécurité finaux.

## Technologies retenues

| Composant | Version |
|---|---|
| PHP | 8.3 minimum, compatible 8.4 (testé sous 8.3.6) |
| Laravel | 13.x (13.37.0 verrouillée dans `composer.lock`) |
| MySQL | 8.0 (testé sous 8.0.46) |
| Interface | Blade + Tailwind CSS 4.3 + Vite 8 (JavaScript minimal, sans framework) ; police Public Sans embarquée (OFL) ; pictogrammes dérivés de Lucide (ISC) |
| PDF / Excel | dompdf 3.1 / PhpSpreadsheet 5.10 |
| Tests | PHPUnit 12.5 |
| Rôles et permissions | implémentation interne testée (aucune dépendance externe) |

Monolithe Laravel modulaire. Aucune ressource externe (CDN, polices en ligne) : l'application fonctionne sans accès Internet.

**Identité visuelle.** Le logo et la photographie du siège fournis par l'INPS sont dans `public/images/institution/`
(`logo.png`, `siege.jpg`). Pour les remplacer : même dossier ou Administration → Paramètres → Identité (prioritaire).
Après toute modification des fichiers `resources/css` ou `resources/js` : `npm run build`.

## Démarrage rapide (développement)

Prérequis : PHP 8.3/8.4 (extensions `pdo_mysql`, `mbstring`, `zip`, `gd`, `intl`, `fileinfo`), Composer 2, Node.js 20+, MySQL 8.0.

```bash
composer install
cp .env.example .env            # Windows : copy .env.example .env  — puis renseigner DB_*
php artisan key:generate
php artisan migrate --seed       # tables + référentiel initial (aucun compte, aucune donnée de personnel)
npm ci --ignore-scripts
npm run build
php artisan inps:creer-admin --drh   # compte RH : gestionnaire + administrateur (mot de passe saisi de façon masquée)
php artisan serve                # http://localhost:8000
```

Données **fictives** de démonstration (refusé en production) :

```bash
# Linux/macOS
DEMO_PASSWORD='Choisir-Un-Mot-De-Passe-2026!' php artisan db:seed --class=DemoSeeder
# Windows PowerShell
$env:DEMO_PASSWORD='Choisir-Un-Mot-De-Passe-2026!'; php artisan db:seed --class=DemoSeeder
```

Comptes créés : `demo.responsable_drh@exemple.invalid` (la RH, qui gère tout) et `demo.chef_service@exemple.invalid` (demandes de son service), avec le mot de passe `DEMO_PASSWORD`.

## Tests

```bash
# base MySQL distincte « inps_formation_test » (voir docs/TESTS.md)
php artisan test
```

Résultat de la dernière exécution : voir `docs/TESTS.md`.

## Commandes d'exploitation

| Commande | Rôle |
|---|---|
| `php artisan inps:creer-admin --drh` | Créer le compte RH (Responsable DRH + administrateur technique) ; sans `--drh` : administrateur technique seul |
| `php artisan inps:sante` | Contrôle de santé (code retour ≠ 0 en cas d'anomalie bloquante) |
| `php artisan inps:sauvegarde` | Sauvegarde base + documents (archive chiffrée si `BACKUP_PASSWORD`) |
| `php artisan inps:restaurer <archive>` | Restauration (confirmation requise) |
| `php artisan inps:echeances` | Rappels d'échéances (lancé chaque jour par le planificateur) |
| `php artisan inps:proteger-audit` | Installer les déclencheurs de protection du journal d'audit |

## Documentation

| Document | Contenu |
|---|---|
| `docs/INSTALLATION_WINDOWS.md` | Installation locale détaillée sous Windows |
| `docs/INSTALLATION_PRODUCTION.md` | Déploiement sur serveur Linux (HTTPS, Nginx/Apache, PHP-FPM, cron, Docker) |
| `docs/ARCHITECTURE.md` | Architecture technique |
| `docs/BASE_DE_DONNEES.md` | Tables, relations, diagramme |
| `docs/GUIDE_UTILISATEUR.md` | Utilisation des fonctionnalités et définitions des indicateurs |
| `docs/GUIDE_ADMINISTRATEUR.md` | Comptes, rôles, paramétrage |
| `docs/SECURITE.md` | Mesures de sécurité |
| `docs/SAUVEGARDE_RESTAURATION.md` | Sauvegarde et reprise |
| `docs/TESTS.md` | Commandes et résultats des tests |
| `docs/DEPLOIEMENT.md` | Mise à jour, retour arrière, exploitation |
| `docs/RECETTE_INPS.md` | Scénarios de recette |
| `docs/DECISIONS_A_VALIDER.md` | Règles institutionnelles à confirmer |
| `docs/formation/FORMATION_TECHNIQUE.md` | Formation technique complète pour les développeurs (architecture, code, base, modules, maintenance), avec extraits de code réels ; existe aussi en PDF |
| `CHANGELOG.md` | Historique des versions |

## Arborescence principale

```
app/            Code applicatif (Services = règles métier, Http = contrôleurs/requêtes/middleware, Policies, Models, Enums)
config/inps.php Configuration technique propre à l'application
database/       Migrations, seeders (référentiel initial, démonstration), script SQL de protection du journal
resources/      Vues Blade, CSS Tailwind, JavaScript
routes/         Routes web et tâches planifiées
tests/          Tests unitaires et fonctionnels
exemples/       Modèles et fichiers d'importation fictifs
deploiement/    Configurations serveur, cron, script de mise à jour
docker/, Dockerfile, docker-compose.yml   Configuration Docker de référence (facultative)
```
