# Mise à jour et exploitation

## Mise à jour vers une nouvelle version

Script fourni (à exécuter dans le dossier de l'application, en tant que `www-data`) :

```bash
./deploiement/mettre-a-jour.sh /chemin/inps-formation-<version>.zip
```

Étapes réalisées : sauvegarde complète → copie de la version en place (retour arrière) → mode maintenance → remplacement du code
(`storage/` et `.env` conservés) → `composer install --no-dev` → compilation des ressources → `migrate --force` →
`ReferentielInitialSeeder` (ajoute les nouvelles permissions/paramètres sans écraser les réglages existants) → caches → remise en service → `inps:sante`.

Procédure manuelle équivalente :

```bash
php artisan inps:sauvegarde
php artisan down --retry=60
# remplacer le code (sauf storage/ et .env)
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan db:seed --class=ReferentielInitialSeeder --force
php artisan optimize:clear && php artisan optimize
php artisan up
php artisan inps:sante
```

Lire le `CHANGELOG.md` de la version avant toute mise à jour (migrations, nouveaux paramètres, actions manuelles).

## Retour à la version antérieure

1. `php artisan down`
2. Restaurer le code précédent (dossier `../inps-formation-precedente-AAAAMMJJ-HHMMSS` créé par le script, ou archive de la version précédente) en conservant `storage/` et `.env`.
3. Si la nouvelle version a appliqué des migrations : restaurer la **sauvegarde prise juste avant la mise à jour**
   (`php artisan inps:restaurer <archive>`). Les migrations disposent aussi de méthodes `down()`, mais la restauration de la sauvegarde est la méthode de référence (aucune perte de cohérence).
4. `composer install --no-dev --optimize-autoloader && php artisan optimize:clear && php artisan optimize`
5. `php artisan up && php artisan inps:sante`

Les données saisies entre la mise à jour et le retour arrière sont perdues si la sauvegarde est restaurée : décider rapidement.

## Exploitation courante

| Fréquence | Tâche |
|---|---|
| Quotidienne (automatique) | Rappels d'échéances 06:30, sauvegarde 01:30 (vérifier dans Administration → Système) |
| Quotidienne | Copie hors serveur des sauvegardes (à organiser par l'INPS) |
| Hebdomadaire | `php artisan inps:sante`, lecture des journaux `storage/logs` (niveau warning et plus) |
| Mensuelle | Revue des comptes actifs et des rôles ; revue du journal d'audit (connexions en échec, exports, changements de droits) |
| Mensuelle | Mises à jour de sécurité du système, PHP, MySQL ; `composer audit` et `npm audit` |
| Trimestrielle | Test de restauration sur serveur de recette |
| Annuelle | Clôture de l'année de référence, ouverture de la suivante ; revue des paramètres |

## Contrôles de santé

- `php artisan inps:sante` : base, migrations, protection du journal, administrateur actif, clé, stockage, configuration de production (débogage, HTTPS, cookies), sessions, messagerie, sauvegardes, planificateur. Code retour non nul si anomalie bloquante.
- `GET /up` : sonde de disponibilité HTTP (200 si l'application répond).

## Incidents fréquents

| Symptôme | Action |
|---|---|
| Erreur 500 | `storage/logs/laravel-<date>.log` ; vérifier droits d'écriture sur `storage/` et `bootstrap/cache/` |
| « Vite manifest not found » | `npm ci --ignore-scripts && npm run build` |
| Erreur 419 sur un formulaire | Session expirée (inactivité) : recharger la page ; vérifier l'horloge du serveur et `APP_URL`/HTTPS derrière proxy (`TRUSTED_PROXIES`) |
| Sauvegarde en échec | `mysqldump` absent ou chemin `MYSQLDUMP_PATH` ; droits sur `BACKUP_PATH` |
| Aucun rappel reçu | Cron `schedule:run` absent (`inps:sante` → planificateur) |
| E-mails non reçus | `MAIL_MAILER=log` (aucun envoi) ou SMTP en erreur : voir « Derniers envois d'e-mails » |
| Utilisateur bloqué après erreurs de mot de passe | Attendre une minute (limitation des tentatives) ; sinon mot de passe temporaire |
