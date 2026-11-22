# syntax=docker/dockerfile:1.10
# 1.10 or newer: with Use Docker Build Secrets, Coolify adds `--mount=type=secret,id=…,env=…` to every RUN, and `env=`
# is unknown before 1.10 ("unexpected key 'env'").
#
# The production image of Fameja.app, on serversideup/php (nginx + PHP-FPM, supervised by S6 overlay):
# the web, the queue worker and the scheduler in one container. Built by Coolify from this file; locally:
#
#   export VITE_FORMKIT_PRO_KEY="$(grep ^VITE_FORMKIT_PRO_KEY= .env | cut -d= -f2-)"
#   docker build --secret id=VITE_FORMKIT_PRO_KEY,env=VITE_FORMKIT_PRO_KEY -t family-manager-app .
#
# At start the image's Laravel automations run `migrate --force` and `optimize` (config, routes, views and
# events cached), then docker/production/entrypoint.d runs `reminders:run` once; the queue worker and the
# scheduler start after the migrations (docker/production/s6). The files of the families live in
# /var/www/html/storage/app/private, a persistent volume.

ARG PHP_IMAGE=serversideup/php:8.5-fpm-nginx-v5.1.2
ARG NODE_IMAGE=node:22.23.5-trixie-slim

############################################
# Base: PHP with the extensions and the tools of the app
############################################
FROM ${PHP_IMAGE} AS base

USER root

# gd and exif for the photos, intl and bcmath for Laravel and Filament, redis for cache and sessions, pdo_mysql
# for MySQL. ghostscript (gs, with -dSAFER) and libheif-examples (heif-convert) make the previews of PDF and HEIC; on
# Debian 13 the HEIC decoder (libde265) is a separate plugin package.
RUN install-php-extensions gd exif intl bcmath redis pdo_mysql \
    && apt-get update \
    && apt-get install -y --no-install-recommends ghostscript libheif-examples libheif-plugin-libde265 \
    && rm -rf /var/lib/apt/lists/*

# Limits of the uploads and of the mail of Mailgun (loaded after the image's own ini)
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zzzz-family-manager.ini

USER www-data

############################################
# Node 22 for the build of the pages
############################################
FROM ${NODE_IMAGE} AS node

############################################
# Build: Composer, npm, Vite (Wayfinder runs PHP inside the Vite plugin, so it needs PHP and vendor)
############################################
FROM base AS build

USER root
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
USER www-data

WORKDIR /var/www/html

# The dependencies first, so a change of the code alone reuses these layers
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY --chown=www-data:www-data package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY --chown=www-data:www-data . .

# What the app writes at runtime (left out of the build context), owned by www-data; Artisan needs the cache
# folders from here on, and storage/app/private is the volume of the families' files
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache

# Runs package:discover and filament:upgrade (the assets of Filament in public/js|css|fonts/filament)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# The FormKit Pro key ends up only in the built JS: a build secret, never an ARG or ENV left in the history.
# npm run build builds the app and then the service worker (vite.sw.config.ts → public/build/sw.js).
RUN --mount=type=secret,id=VITE_FORMKIT_PRO_KEY,required=true,uid=33 \
    export VITE_FORMKIT_PRO_KEY="$(cat /run/secrets/VITE_FORMKIT_PRO_KEY)" && npm run build

RUN rm -rf node_modules

############################################
# Production
############################################
FROM base AS production

# NGINX_FASTCGI_BUFFER_SIZE: the room nginx keeps for the headers from PHP. With the image's 8k a page with many
# preloaded files (the Link header) and the cookies went over it: "upstream sent too big header", 502.
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION_TIMEOUT=60 \
    PHP_OPCACHE_ENABLE=1 \
    PHP_MEMORY_LIMIT=256M \
    PHP_FPM_PM_CONTROL=ondemand \
    PHP_FPM_PM_MAX_CHILDREN=6 \
    PHP_FPM_PM_START_SERVERS=2 \
    PHP_FPM_PM_MIN_SPARE_SERVERS=1 \
    PHP_FPM_PM_MAX_SPARE_SERVERS=3 \
    S6_SERVICES_GRACETIME=30000 \
    S6_KILL_GRACETIME=5000 \
    LOG_CHANNEL=stderr \
    TRUSTED_PROXY=off \
    NGINX_FASTCGI_BUFFER_SIZE=16k

# A small droplet shared with Directus: at most 6 PHP-FPM children of 256 MB (the image's default is 20), and the
# queue worker restarts past 256 MB (--memory). On SIGTERM S6 gives the services 30 s to end (an AI reading takes
# about 20 s) before killing them, so the stop timeout of Coolify must be at least 35 s. Logs go to the output of
# the container (Coolify shows them), not to files inside it; an environment variable can still override it.

# TRUSTED_PROXY=off: nginx leaves the address of Coolify's proxy to PHP, and Laravel trusts it from
# TRUSTED_PROXIES (config/app.php), so the client address and https come from X-Forwarded-* as before.

USER root

# The start script (reminders:run once, after the automations) becomes an S6 oneshot; the queue worker and
# the scheduler are S6 services of the "user" bundle, started after the migrations.
COPY --chmod=755 docker/production/entrypoint.d/ /etc/entrypoint.d/
COPY --chmod=755 docker/production/s6/ /etc/s6-overlay/s6-rc.d/
RUN docker-php-serversideup-s6-init \
    && touch /etc/s6-overlay/user-bundles.d/user/contents.d/laravel-queue \
        /etc/s6-overlay/user-bundles.d/user/contents.d/laravel-scheduler

USER www-data

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

# With the image's SIGQUIT, S6 did not bring the services down and Docker killed the container after its timeout
# (exit 137, the worker cut in the middle of a job); with SIGTERM it stops in about 3 s and the worker ends cleanly
STOPSIGNAL SIGTERM

# Laravel's own health route: the app answers, not only PHP-FPM
HEALTHCHECK --interval=10s --timeout=5s --start-period=90s --retries=3 \
    CMD curl --silent --show-error --fail http://localhost:8080/up || exit 1
