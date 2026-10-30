#!/usr/bin/env bash
# Mise à jour en production (à exécuter en tant que www-data depuis le dossier de l'application).
# Usage : ./deploiement/mettre-a-jour.sh /chemin/vers/nouvelle-version.zip
set -euo pipefail
cd "$(dirname "$0")/.."
ARCHIVE="${1:?Indiquer le chemin de la nouvelle version (zip)}"

echo "1/7 Sauvegarde préalable (base + documents)"
php artisan inps:sauvegarde

echo "2/7 Copie de la version actuelle pour retour arrière"
VERSION_PRECEDENTE="../inps-formation-precedente-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$VERSION_PRECEDENTE"
rsync -a --exclude storage --exclude .env ./ "$VERSION_PRECEDENTE/"

echo "3/7 Mode maintenance"
php artisan down --retry=60

echo "4/7 Déploiement du code (storage/ et .env conservés)"
TMP="$(mktemp -d)"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$(find "$TMP" -maxdepth 2 -name artisan -printf '%h\n' | head -1)"
rsync -a --delete --exclude storage --exclude .env --exclude vendor --exclude node_modules --exclude public/build "$SRC/" ./
rm -rf "$TMP"

echo "5/7 Dépendances et ressources"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --ignore-scripts && npm run build

echo "6/7 Migrations et caches"
php artisan migrate --force
php artisan db:seed --class=ReferentielInitialSeeder --force   # ajoute les nouvelles permissions sans écraser les réglages
php artisan optimize:clear && php artisan optimize

echo "7/7 Remise en service et contrôle"
php artisan up
php artisan inps:sante || echo "ATTENTION : vérifier le contrôle de santé ci-dessus."
echo "Version précédente conservée dans $VERSION_PRECEDENTE (voir docs/DEPLOIEMENT.md pour le retour arrière)."
