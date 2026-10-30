# Déploiement en production sur serveur Linux

Procédure de référence pour Ubuntu Server 24.04 LTS (adaptable à Debian 12 / RHEL 9).
**Ne pas exposer** `php artisan serve` : il s'agit d'un serveur de développement.

## 1. Paquets système

```bash
sudo apt update
sudo apt install -y nginx mysql-server unzip git curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-intl php8.3-curl php8.3-bcmath
# PHP 8.4 (recommandé) via le dépôt ondrej/php : remplacer 8.3 par 8.4 dans les noms de paquets et de socket.
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

## 2. Base de données MySQL

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE inps_formation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'inps_formation'@'localhost' IDENTIFIED BY '<mot de passe long et aléatoire>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, LOCK TABLES, CREATE TEMPORARY TABLES
  ON inps_formation.* TO 'inps_formation'@'localhost';
FLUSH PRIVILEGES;
```

- MySQL doit écouter uniquement en local : vérifier `bind-address = 127.0.0.1` dans `/etc/mysql/mysql.conf.d/mysqld.cnf`.
- Aucun port 3306 ouvert dans le pare-feu.

## 3. Code de l'application

```bash
sudo mkdir -p /var/www/inps-formation && sudo chown $USER:www-data /var/www/inps-formation
cd /var/www/inps-formation
unzip /chemin/inps-formation-1.1.0.zip -d /tmp/inps && cp -a /tmp/inps/inps-formation/. .
cp .env.example .env && chmod 640 .env
```

Éditer `.env` (valeurs **obligatoires** en production) :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://formation.<domaine-inps>
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
DB_USERNAME=inps_formation
DB_PASSWORD=<mot de passe MySQL>
BACKUP_PATH=/var/backups/inps-formation
BACKUP_PASSWORD=<phrase secrète longue, conservée aussi hors serveur>
# Si un proxy inverse termine le HTTPS en amont :
TRUSTED_PROXIES=127.0.0.1
```

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan migrate --seed --force          # référentiel initial uniquement — aucun compte, aucune donnée fictive
sudo mysql inps_formation < database/sql/protection_audit.sql   # protège le journal d'audit (compte root)
npm ci --ignore-scripts && npm run build
php artisan optimize
php artisan inps:creer-admin --drh          # compte RH (gestionnaire + administrateur technique)
```

**Ne jamais exécuter `DemoSeeder` en production** (il est d'ailleurs refusé lorsque `APP_ENV=production`).

## 4. Droits sur les fichiers

```bash
sudo chown -R $USER:www-data /var/www/inps-formation
sudo find /var/www/inps-formation -type f -exec chmod 640 {} \;
sudo find /var/www/inps-formation -type d -exec chmod 750 {} \;
sudo chmod -R ug+rwX storage bootstrap/cache
sudo chmod 640 .env
sudo mkdir -p /var/backups/inps-formation && sudo chown www-data:www-data /var/backups/inps-formation && sudo chmod 700 /var/backups/inps-formation
```

Le dossier `storage/app/private` (documents) n'est **jamais** servi par le serveur web : seul `public/` est la racine web.

## 5. Serveur web et HTTPS

```bash
sudo cp deploiement/nginx-inps-formation.conf /etc/nginx/sites-available/inps-formation.conf
sudo nano /etc/nginx/sites-available/inps-formation.conf   # nom de domaine, certificats, version de PHP-FPM
sudo ln -s /etc/nginx/sites-available/inps-formation.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Certificat : fourni par l'INPS (autorité interne) ou Let's Encrypt (`sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx`)
si le serveur est joignable publiquement. Variante Apache : `deploiement/apache-inps-formation.conf`.

PHP-FPM (`/etc/php/8.3/fpm/php.ini`) : `expose_php = Off`, `display_errors = Off`, `upload_max_filesize = 12M`, `post_max_size = 14M`,
`opcache.enable = 1`, puis `sudo systemctl restart php8.3-fpm`.

Pare-feu : `sudo ufw allow 'Nginx Full' && sudo ufw allow OpenSSH && sudo ufw enable`.

## 6. Tâches planifiées

```bash
sudo crontab -u www-data -e
# ajouter :
* * * * * cd /var/www/inps-formation && php artisan schedule:run >> /dev/null 2>&1
```

Le planificateur exécute : rappels d'échéances (06:30), sauvegarde quotidienne (01:30), purge des jetons de réinitialisation.
Aucune file d'attente n'est nécessaire (`QUEUE_CONNECTION=sync`).

## 7. Journaux

- Application : `storage/logs/laravel-AAAA-MM-JJ.log` (rotation quotidienne, `LOG_DAILY_DAYS` jours).
- Nginx : `/var/log/nginx/inps-formation.*.log`.
- Les journaux techniques ne contiennent ni mots de passe ni jetons. Le journal d'audit métier est en base (table `audit_logs`).

## 8. Contrôles après installation

```bash
php artisan inps:sante          # tout doit être « OK » ; code retour 0
curl -I https://formation.<domaine>/up      # sonde de disponibilité (HTTP 200)
curl -I http://formation.<domaine>/         # doit rediriger vers https
```

Intégrer `php artisan inps:sante` et `/up` à la supervision de l'INPS (alerte si code ≠ 0 / HTTP ≠ 200).

## 9. Docker (référence facultative)

Fichiers fournis : `Dockerfile` (cibles `app` PHP 8.4-FPM et `web` Nginx), `docker-compose.yml` (app, web, planificateur, MySQL 8.0
sans port publié), `docker/`. **Non testés dans l'environnement de développement** (démon Docker indisponible) : à valider lors de la recette technique.

```bash
cp .env.example .env    # + DB_ROOT_PASSWORD, APP_ENV=production, APP_DEBUG=false, BACKUP_PASSWORD…
docker compose up -d --build
docker compose exec app php artisan key:generate --force && docker compose restart app planificateur
docker compose exec app php artisan migrate --seed --force
docker compose exec app php artisan inps:creer-admin
```

Le conteneur `web` écoute sur `127.0.0.1:8080` : le publier derrière un proxy inverse HTTPS.

## 10. Checklist de mise en production

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`
- [ ] `APP_KEY` générée et sauvegardée hors serveur (nécessaire pour relire les sessions chiffrées)
- [ ] `.env` en 640, absent de tout dépôt Git ; aucun secret dans le code
- [ ] Base MySQL non accessible depuis le réseau ; compte applicatif à privilèges limités
- [ ] Déclencheurs du journal d'audit installés (`inps:sante` → OK)
- [ ] Certificat HTTPS valide ; HTTP redirigé vers HTTPS
- [ ] Racine web = `public/` ; `storage/` et `.env` inaccessibles par URL (vérifié avec `curl`)
- [ ] Cron `schedule:run` actif (`inps:sante` → planificateur OK après 1 minute)
- [ ] `BACKUP_PASSWORD` défini ; première sauvegarde réalisée ; **restauration testée** sur un serveur de recette
- [ ] Copie des sauvegardes hors serveur organisée
- [ ] Administrateur technique créé ; aucun compte de démonstration ; aucune donnée fictive
- [ ] Rôles configurés selon les décisions de l'INPS
- [ ] Paramètres « à valider » confirmés (`docs/DECISIONS_A_VALIDER.md`)
- [ ] SMTP configuré et testé, ou notifications e-mail laissées désactivées
- [ ] Recette fonctionnelle signée (`docs/RECETTE_INPS.md`) et contrôles de sécurité finaux réalisés
