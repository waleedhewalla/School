#!/usr/bin/env bash
# Prepares Nbras with the demo school. Safe to run again.
set -e
cd "$(dirname "$0")/.."

# Hijri dates and Arabic sorting need the intl extension.
if ! php -m | grep -qi '^intl$'; then
    sudo apt-get update -y && sudo apt-get install -y libicu-dev
    sudo docker-php-ext-install intl
fi

composer install --no-interaction
npm ci --no-audit --no-fund
npm run build

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
    # Codespaces serves the app over HTTPS through its own proxy.
    echo "TRUSTED_PROXIES=*" >> .env
fi

if [ ! -f database/database.sqlite ]; then
    php -r "touch('database/database.sqlite');"
    php artisan migrate --seed --force
fi

echo "Nbras is ready. Sign in as admin@example.com / password."
