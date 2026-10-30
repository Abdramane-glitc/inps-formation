# Sauvegarde et restauration

## Contenu d'une sauvegarde

`php artisan inps:sauvegarde` produit `inps-formation-AAAAMMJJ-HHMMSS.zip` dans `BACKUP_PATH` (par défaut `storage/app/sauvegardes`) :

| Élément | Détail |
|---|---|
| `base.sql` | Export complet MySQL (`mysqldump --single-transaction`, cohérent sans interruption de service) |
| `fichiers/` | Documents privés (`storage/app/private` : pièces jointes, logo) |
| `manifeste.json` | Date, base, empreinte SHA-256 de chaque fichier |

- Chiffrement **AES-256** de chaque entrée si `BACKUP_PASSWORD` est défini (obligatoire en production). Sans phrase secrète, la sauvegarde est impossible à relire : la conserver **hors du serveur** (coffre, gestionnaire de mots de passe).
- Identifiants MySQL transmis par fichier d'options temporaire (jamais visibles dans la liste des processus).
- Archive en droits 0600 ; rétention des `BACKUP_RETENTION` dernières archives (14 par défaut).
- Fréquence : quotidienne à 01:30 via le planificateur (production/recette), ou manuelle (commande ou bouton Administration → Système).
- **À compléter par l'INPS** : copie quotidienne hors serveur (autre machine, autre site), voir `deploiement/crontab`.
- La clé `APP_KEY` (fichier `.env`) doit également être sauvegardée séparément.

## Restauration

```bash
php artisan down
php artisan inps:restaurer /var/backups/inps-formation/inps-formation-20261020-013000.zip
#   → demande de taper le nom de la base pour confirmer (ou --force)
#   options : --base=autre_base (restaurer ailleurs), --sans-fichiers
php artisan migrate --force
php artisan optimize:clear
php artisan up
php artisan inps:sante
```

Déroulement : extraction (phrase secrète requise), **vérification de toutes les empreintes** (arrêt si altération), création de la base si besoin,
import SQL, restauration des documents, réinstallation des déclencheurs de protection du journal d'audit.

Sous Windows, si `mysql`/`mysqldump` ne sont pas dans le PATH : renseigner `MYSQLDUMP_PATH` et `MYSQL_CLIENT_PATH` dans `.env`.

## Restauration manuelle (sans l'application)

```bash
unzip -P '<BACKUP_PASSWORD>' inps-formation-AAAAMMJJ-HHMMSS.zip -d /tmp/restauration
# (utiliser 7-Zip si unzip ne gère pas l'AES : 7z x -p'<BACKUP_PASSWORD>' archive.zip)
mysql -u root -p inps_formation < /tmp/restauration/base.sql
cp -a /tmp/restauration/fichiers/. /var/www/inps-formation/storage/app/private/
mysql -u root -p inps_formation < /var/www/inps-formation/database/sql/protection_audit.sql
shred -u /tmp/restauration/base.sql; rm -rf /tmp/restauration
```

## Vérifications réalisées

Le test automatisé `tests/Feature/SauvegardeRestaurationTest.php` exécute réellement, sur MySQL 8.0.46 :
1. une sauvegarde chiffrée (contrôle du chiffrement AES-256 de `base.sql`) ;
2. sa restauration dans une base distincte `inps_formation_restore_test` ;
3. la comparaison des données restaurées (donnée témoin, nombre de permissions) et la présence du document témoin dans le manifeste ;
4. le refus de restaurer avec une phrase secrète erronée.

Résultat : réussi (voir `docs/TESTS.md`).

## Procédure à tester par l'INPS (recette)

Au moins une fois avant la mise en production, puis périodiquement (ex. trimestriellement) :
restaurer la dernière sauvegarde de production sur un serveur de recette, se connecter, vérifier quelques dossiers et un document, consigner le résultat et la durée.
