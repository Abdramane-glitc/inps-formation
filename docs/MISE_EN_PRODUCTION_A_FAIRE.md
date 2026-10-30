# À faire le jour de la mise en service sur le serveur (point du 29/10/2026)

Décidé avec l'utilisateur : le rattachement des agents à leurs services et la messagerie (SMTP) seront réglés
**après** la mise en service. Le reste ci-dessous se fait **sur le serveur**, dans cet ordre.

1. **Sauvegarde** : `php artisan inps:sauvegarde` (mysqldump de Laragon est trouvé automatiquement sous Windows ;
   sinon renseigner `MYSQLDUMP_PATH` dans `.env`).
2. **Comptes de test** : `php artisan inps:comptes-test` (liste), puis `php artisan inps:comptes-test --desactiver`.
   Désactive les comptes en `@exemple.invalid` (52 au 29/10/2026), sans les supprimer. Refusé s'il ne reste aucun
   administrateur réel actif (au 29/10/2026 : « abou dramane sogodogo »).
3. **Protection du journal d'audit** : `php artisan inps:proteger-audit` (absente du poste au 29/10/2026).
4. **`.env` de production** : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`,
   `BACKUP_PASSWORD=…` (le noter en lieu sûr : sans lui, une sauvegarde chiffrée ne se restaure pas).
   Puis `php artisan optimize`.
5. **Serveur web et HTTPS** : modèles dans `deploiement/` (Apache ou Nginx) ; voir `docs/INSTALLATION_PRODUCTION.md`
   (Linux) ou `docs/INSTALLATION_WINDOWS.md`.
6. **Tâche planifiée** chaque minute : `php artisan schedule:run` (rappels à 6 h 30, sauvegarde à 1 h 30).
   Copier les sauvegardes hors du serveur.
7. **Affichage** : `npm ci` puis `npm run build` sur le serveur.
8. **Contrôle** : `php artisan inps:sante` doit être OK partout, sauf la messagerie (réglée plus tard).

Après la mise en service : liste du personnel avec les services (import de rattachement à prévoir),
paramètres SMTP de l'INPS, validations de `docs/DECISIONS_A_VALIDER.md`, noms des 48 services, sigle du bureau de Paris.
