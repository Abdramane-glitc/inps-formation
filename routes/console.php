<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tâches planifiées. Sur le serveur, une seule entrée cron est nécessaire :
|   * * * * * cd /var/www/inps-formation && php artisan schedule:run >> /dev/null 2>&1
| (voir docs/INSTALLATION_PRODUCTION.md ; sous Windows : Planificateur de tâches).
*/
Schedule::command('inps:echeances')->dailyAt('06:30')->withoutOverlapping();
Schedule::command('inps:sauvegarde')->dailyAt('01:30')->withoutOverlapping()->environments(['production', 'staging']);
Schedule::command('auth:clear-resets')->daily();
