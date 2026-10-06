# syntax=docker/dockerfile:1
#
# Production image: nginx + php-fpm 8.3 (webdevops/php-nginx, which ships
# intl, pdo_pgsql, redis, opcache, zip and gd, so nothing is compiled at
# build time). The same image runs the web app, the queue worker and the
# scheduler; see deploy/entrypoint.sh and compose.production.yml.
#
# Behind a TLS-inspecting proxy, pass its CA for the build steps that
# download packages:  docker build --secret id=proxy_ca,src=/path/ca.crt .

# --- Front-end build -------------------------------------------------------
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=secret,id=proxy_ca,required=false \
    NODE_EXTRA_CA_CERTS=$([ -f /run/secrets/proxy_ca ] && echo /run/secrets/proxy_ca) npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- PHP dependencies ------------------------------------------------------
FROM webdevops/php-nginx:8.3 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=proxy_ca,required=false \
    if [ -f /run/secrets/proxy_ca ]; then export COMPOSER_CAFILE=/run/secrets/proxy_ca; fi; \
    composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# --- Runtime ---------------------------------------------------------------
FROM webdevops/php-nginx:8.3 AS app
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    WEB_DOCUMENT_ROOT=/app/public \
    PHP_DISMOD=ioncube,amqp,mongodb,imagick,ldap,imap,memcached,xmlrpc,vips,opentelemetry,excimer,soap,ftp,gmp,yaml,protobuf,xsl
WORKDIR /app
COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-madrasa.ini
COPY deploy/nginx.conf /opt/docker/etc/nginx/vhost.common.d/20-madrasa.conf
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R application:application storage bootstrap/cache \
    && chmod +x deploy/entrypoint.sh

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 CMD curl -fsS http://127.0.0.1/up || exit 1
ENTRYPOINT ["/app/deploy/entrypoint.sh"]
CMD ["web"]
