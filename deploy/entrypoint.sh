#!/bin/sh
# Starts one role of the app: web (default), worker, scheduler or migrate.
# Artisan runs as the unprivileged "application" user that php-fpm uses.
set -e
cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

artisan() { gosu application php artisan "$@"; }

# Storage is a volume: make sure the app user owns it.
chown -R application:application storage bootstrap/cache

# Wait for the database (up to 60 s).
i=0
until php -r 'try { new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 5432, getenv("DB_DATABASE")), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }'; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then echo "Database not reachable" >&2; exit 1; fi
    sleep 2
done

cache() {
    artisan config:cache
    artisan route:cache
    artisan view:cache
    artisan event:cache
}

case "${1:-web}" in
    web)
        # Hosts without IPv6 cannot open nginx's [::] listener.
        if [ ! -f /proc/net/if_inet6 ]; then
            find /opt/docker/etc/nginx -name '*.conf' -exec sed -i '/listen \[::\]/d' {} +
        fi
        # The web container applies migrations unless told not to.
        if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then artisan migrate --force; fi
        cache
        exec /entrypoint supervisord
        ;;
    worker)
        cache
        exec gosu application php artisan queue:work --tries=3 --backoff=30 --max-time=3600 --sleep=3
        ;;
    scheduler)
        cache
        exec gosu application php artisan schedule:work
        ;;
    migrate)
        exec gosu application php artisan migrate --force
        ;;
    *)
        exec gosu application "$@"
        ;;
esac
