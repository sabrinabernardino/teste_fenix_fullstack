#!/bin/sh
set -e
cd /var/www

if [ ! -f artisan ]; then
  echo "!! Laravel ainda não foi instalado em ./backend. Rode ./setup.sh"
  exec "$@"
fi

[ -f .env ] || cp .env.example .env
[ -d vendor ] || composer install --no-interaction --prefer-dist

# Autoload otimizado (classmap) = menos I/O por request
composer dump-autoload --optimize --quiet

grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

php artisan migrate --force
php artisan db:seed --force

chmod -R 777 storage bootstrap/cache

exec "$@"
