# Installation locale sous Windows (développement / démonstration)

Docker n'est **pas** nécessaire. Cette procédure installe l'application sur un poste Windows 10/11.

## 1. Logiciels à installer

| Logiciel | Version | Source | Remarque |
|---|---|---|---|
| PHP | 8.3 ou 8.4 (« Non Thread Safe », x64) | https://windows.php.net/download | Décompresser dans `C:\php`, ajouter `C:\php` au PATH |
| Composer | 2.x | https://getcomposer.org/download | L'installateur détecte `C:\php\php.exe` |
| Node.js | 20 LTS ou plus | https://nodejs.org | Fournit `npm` |
| MySQL Server | 8.0 | https://dev.mysql.com/downloads/installer/ | Noter le mot de passe `root` choisi |

Alternative : une distribution tout-en-un (Laragon, XAMPP récent) fournissant PHP 8.3+ et MySQL 8.0 convient également.

### Extensions PHP à activer

Copier `C:\php\php.ini-development` en `C:\php\php.ini`, puis retirer le `;` devant :

```ini
extension_dir = "ext"
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
```

Vérifier : `php -m` doit lister `pdo_mysql`, `mbstring`, `zip`, `gd`, `intl`, `fileinfo`.

## 2. Créer la base de données

Ouvrir « MySQL 8.0 Command Line Client » (ou `mysql -u root -p`) :

```sql
CREATE DATABASE inps_formation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE inps_formation_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- pour les tests
CREATE USER 'inps_formation'@'localhost' IDENTIFIED BY 'choisir-un-mot-de-passe';
GRANT ALL PRIVILEGES ON inps_formation.* TO 'inps_formation'@'localhost';
GRANT ALL PRIVILEGES ON inps_formation_test.* TO 'inps_formation'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Installer l'application

Décompresser l'archive (ex. `C:\inps-formation`) puis, dans PowerShell :

```powershell
cd C:\inps-formation
copy .env.example .env
notepad .env        # renseigner DB_DATABASE, DB_USERNAME, DB_PASSWORD
composer install
php artisan key:generate
php artisan migrate --seed
npm ci --ignore-scripts
npm run build
php artisan inps:creer-admin --drh
php artisan serve
```

Ouvrir http://localhost:8000 et se connecter avec le compte administrateur créé.

Ces commandes se tapent une par une ; aucun script d'installation automatique n'est fourni.

### Message « déclencheurs de protection du journal d'audit non créés »

Si MySQL a la journalisation binaire activée (cas par défaut) et que le compte applicatif n'a pas le privilège
`SUPER`, la migration affiche cet avertissement **et continue**. Exécuter ensuite, avec le compte `root` :

```powershell
mysql -u root -p inps_formation < database\sql\protection_audit.sql
```

## 4. Données de démonstration (facultatif)

```powershell
$env:DEMO_PASSWORD='Choisir-Un-Mot-De-Passe-2026!'
php artisan db:seed --class=DemoSeeder
```

Deux comptes, conformes à l'organisation retenue : `demo.responsable_drh@exemple.invalid` (la RH, qui gère tout, y compris
l'administration) et `demo.chef_service@exemple.invalid` (déclare les besoins de son service) — mot de passe : la valeur de `DEMO_PASSWORD`.

Pour repartir d'une base vide : `php artisan migrate:fresh --seed` (**efface toutes les données** — jamais en production).

## 5. Développement de l'interface

`npm run dev` (rechargement automatique des styles) dans un second terminal, en parallèle de `php artisan serve`.
Ou `composer dev` qui lance les deux.

## 6. Tests

Créer `.env.testing` à partir de `.env.testing.example`, puis : `php artisan test`. Voir `docs/TESTS.md`.

Le test de sauvegarde/restauration utilise `mysqldump` et `mysql` : ajouter `C:\Program Files\MySQL\MySQL Server 8.0\bin`
au PATH, ou renseigner `MYSQLDUMP_PATH` / `MYSQL_CLIENT_PATH` dans `.env.testing`. Il crée et supprime la base
`inps_formation_restore_test` (droit à accorder : `GRANT ALL ON inps_formation_restore_test.* TO 'inps_formation'@'localhost';`).

## 7. Tâches planifiées sous Windows (facultatif en développement)

Planificateur de tâches Windows → tâche exécutée chaque minute :
programme `C:\php\php.exe`, arguments `artisan schedule:run`, dossier de départ `C:\inps-formation`.

## Problèmes fréquents

| Symptôme | Solution |
|---|---|
| `could not find driver` | Activer `extension=pdo_mysql` dans `php.ini` |
| `Vite manifest not found` | Exécuter `npm run build` |
| Page blanche / erreur 500 | Consulter `storage\logs\laravel-AAAA-MM-JJ.log` |
| `SQLSTATE[HY000] [1045]` | Identifiants MySQL de `.env` incorrects |
| Exports PDF sans accents | Vérifier que `vendor\dompdf` est complet (`composer install`) |

## Mise à jour d'une installation 1.0.0 vers 1.1.0 (refonte visuelle)

Copier les fichiers de la nouvelle archive par-dessus l'installation existante **sans écraser `.env`**, puis dans `cmd` :

```bat
cd C:\dev\inps-formation-1.0.0\inps-formation
composer install
npm ci --ignore-scripts
npm run build
php artisan migrate
php artisan optimize:clear
php artisan serve
```

`npm ci` installe la police embarquée (nouvelle dépendance) ; `php artisan migrate` applique la migration d'identité visuelle
(couleur #003B73 et paramètre « Photographie du siège »). Rafraîchir ensuite le navigateur avec Ctrl+F5.

## Mise à jour vers la version 1.3.0 (parcours simplifié, sans plan annuel)

Décompresser la nouvelle archive dans un **nouveau dossier**, y copier le fichier `.env` et le dossier `storage\app\private`
de l'ancienne installation, puis dans le terminal :

```bat
cd C:\chemin\vers\inps-formation-1.3.0\inps-formation
composer install
php artisan migrate
php artisan optimize:clear
php artisan serve
```

`php artisan migrate` doit afficher `2026_10_21_000900_formations_sans_plan_annuel ... DONE`. Cette migration ne supprime aucune donnée :
voir `docs/BASE_DE_DONNEES.md`, section « Tables conservées sans usage ». Les styles compilés sont fournis : `npm` n'est pas nécessaire.
Faire d'abord une sauvegarde (`php artisan inps:sauvegarde`) si la base contient des données réelles.

## Mise à jour vers la version 1.4.0 (demandes simplifiées, sans finances)

Même procédure que pour la 1.3.0 (nouveau dossier, copie de `.env`, `composer install`, `php artisan migrate`, `php artisan optimize:clear`).
`php artisan migrate` doit afficher `2026_10_22_001000_demandes_simplifiees_sans_finances ... DONE`. Aucune donnée n'est supprimée.

## Mise à jour vers la version 1.5.0 (formation en trois étapes)

Décompresser l'archive dans un **nouveau dossier**, puis taper **une ligne à la fois** dans le terminal (Laragon › Terminal).
Remplacer les chemins par les vôtres ; l'exemple suppose que l'archive est dans `C:\laragon\www` et que l'ancienne installation
est `C:\laragon\www\inps-formation-1.3.0`.

```bat
cd /d C:\laragon\www && mkdir inps-formation-1.5.0 && C:\Windows\System32\tar.exe -xf inps-formation-1.5.0.zip -C inps-formation-1.5.0
cd /d C:\laragon\www\inps-formation-1.5.0\inps-formation && copy ..\..\inps-formation-1.3.0\inps-formation\.env .env
xcopy /E /I /Y ..\..\inps-formation-1.3.0\inps-formation\storage\app\private storage\app\private
composer install && php artisan migrate --force && php artisan optimize:clear && php artisan serve
```

`php artisan migrate` doit afficher `2026_10_22_001000_demandes_simplifiees_sans_finances` (si la 1.4.0 n'était pas installée)
et `2026_10_22_001100_formation_en_trois_etapes ... DONE`. Aucune donnée n'est supprimée. Les styles compilés sont fournis : `npm` n'est pas nécessaire.

## Mise à jour vers la version 1.5.1 (participants par service)

Aucune migration. Décompresser dans un nouveau dossier `inps-formation-1.5.1`, copier le `.env` et `storage\app\private` de la 1.5.0,
puis lancer `composer install`, `php artisan optimize:clear` et démarrer le serveur.

## Mise à jour vers la version 1.6.0 (profils, journal, sauvegardes, corbeille)

Comme pour la 1.5.1 (nouveau dossier `inps-formation-1.6.0`, copie du `.env` et de `storage\app\private`), puis
`composer install && php artisan migrate --force && php artisan optimize:clear` : `migrate` doit afficher
`profils_fixes_et_libelles` et `corbeille_des_formations ... DONE`. Pour que « Faire une sauvegarde » fonctionne sur Laragon,
ajouter dans `.env` le chemin de mysqldump, par exemple `MYSQLDUMP_PATH="C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"`
(adapter le numéro de version au dossier présent sur le poste).

## Mise à jour vers la version 1.6.1 (structure de l'INPS)

Aucune migration : nouveau dossier `inps-formation-1.6.1`, copie du `.env` et de `storage\app\private` de la 1.6.0,
puis `composer install && php artisan optimize:clear && php artisan serve`. Ensuite : Personnel › Importations, type
« Structure de l'INPS », fichier `structure-inps-organisee.xlsx` → Analyser → vérifier l'aperçu → Confirmer.

## Mise à jour vers la version 1.7.0 (plan annuel)

Nouveau dossier `inps-formation-1.7.0`, copie du `.env` et de `storage\app\private` de la version précédente, puis
`composer install && php artisan migrate --force && php artisan optimize:clear && php artisan serve`.
`migrate` doit afficher `plan_annuel_simple ... DONE`.

## Mise à jour vers la version 1.7.1 (correctif)

Aucune migration. Nouveau dossier `inps-formation-1.7.1`, copie du `.env` et de `storage\app\private` de la 1.7.0, puis
`composer install && php artisan optimize:clear && php artisan serve`.

## Mise à jour vers la version 1.7.2

Aucune migration : même procédure que la 1.7.1 (nouveau dossier, copie du `.env` et de `storage\app\private`,
`composer install && php artisan optimize:clear && php artisan serve`).

## Mise à jour vers la version 2.6.0

Aucune migration : `composer install`, puis `php artisan optimize`.

## Mise à jour vers la version 2.5.2

Aucune migration : `composer install`, puis `php artisan optimize`. Pour une photographie du siège plus nette, déposer
une version d'au moins 1600 px de large (Administration › Paramètres › Identité institutionnelle).

## Mise à jour vers la version 2.5.1

**Une migration** (correctif du plan annuel) : même procédure que la 2.5.0 (`composer install`, `php artisan migrate`,
`php artisan optimize`).

## Mise à jour vers la version 2.5.0

**Une migration** : extraire, copier le `.env` de la version précédente, `composer install`, `php artisan migrate`,
`php artisan optimize`. Si la page Sauvegardes affichait « mkdir(): Invalid path », la 2.5.0 corrige ce cas
(ligne `BACKUP_PATH=` vide).

## Mise à jour vers la version 2.4.0

**Une migration** : extraire, copier le `.env` de la version précédente, `composer install`, `php artisan migrate`,
`php artisan optimize`.

## Mise à jour vers la version 2.3.3

Aucune migration : même procédure que la 2.3.1 (`composer install`, puis `php artisan optimize`).

## Mise à jour vers la version 2.3.2

Aucune migration : même procédure que la 2.3.1 (`composer install`, puis `php artisan optimize`).

## Mise à jour vers la version 2.3.1

Aucune migration. Après `composer install`, lancer **`php artisan optimize`** (au lieu de `optimize:clear`) : l'application
charge alors beaucoup moins de fichiers à chaque page, ce qui évite les pages de plus de 30 secondes sur un poste lent.
Après toute modification du fichier `.env`, relancer `php artisan optimize`.
Autres accélérations possibles : activer l'extension **opcache** de PHP (Laragon › PHP › Extensions) et exclure `C:\laragon`
de l'analyse en temps réel de l'antivirus.

## Mise à jour vers la version 2.3.0

**Une migration** : même procédure. Vérifier dans `.env` que `APP_URL` contient l'adresse que les agents utiliseront
(elle est imprimée sur leurs feuilles d'accès).

## Mise à jour vers la version 2.2.0

**Une migration** : même procédure que la 2.1.0.

## Mise à jour vers la version 2.1.0

**Une migration** : même procédure (sauvegarde, copie du `.env` **depuis le dossier de la version utilisée jusqu'ici**,
`composer install`, `php artisan migrate`, `php artisan optimize:clear`). Si `php artisan migrate` propose de créer une base
SQLite, répondre **no** : le `.env` n'a pas été copié.

## Mise à jour vers la version 2.0.0

**Une migration** : même procédure (sauvegarde, copie du `.env`, `composer install`, `php artisan migrate`,
`php artisan optimize:clear`). Aucun compte n'est modifié : tous gardent exactement les droits de leur profil.

## Mise à jour vers la version 1.9.0

**Une migration** : même procédure que la 1.8.0 (sauvegarde, copie du `.env`, `composer install`, `php artisan migrate`,
`php artisan optimize:clear`). Pour les rappels automatiques, la tâche `php artisan inps:echeances` doit être planifiée
chaque jour (Planificateur de tâches de Windows en production).

## Mise à jour vers la version 1.8.0

**Deux migrations** : après avoir extrait la nouvelle version et copié le `.env` de la version précédente,
faire une sauvegarde de la base (menu Sauvegardes), puis `composer install`, `php artisan migrate`, `php artisan optimize:clear`.

## Mise à jour vers la version 1.7.4

Aucune migration : extraire la nouvelle version, copier le fichier `.env` de la version précédente, puis
`composer install`, `php artisan optimize:clear` (les fichiers `public/build` sont déjà dans le ZIP).
Ensuite, dans Paramètres › Référentiels (Directions, Services, Domaines), recocher « Actif » sur les lignes désactivées
par erreur (défaut corrigé en 1.7.4) et cliquer sur « Enregistrer » de chaque ligne.

## Mise à jour vers la version 1.7.3

Aucune migration : même procédure que la 1.7.2.
