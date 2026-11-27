#!/usr/bin/env bash
# Deploy steps from CLAUDE.md §19. Run on the ICT Office server as the deploy user.
set -euo pipefail
cd /var/www/tua-repository

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

analytics/.venv/bin/pip install -q -r analytics/requirements.txt
sudo supervisorctl restart tua-analytics
