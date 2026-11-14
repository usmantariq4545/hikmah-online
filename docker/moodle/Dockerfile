# syntax=docker/dockerfile:1
# Hikmah Online — Moodle 5.3 LTS on Debian PHP 8.4 FPM (glibc locales + iconv for Arabic/Urdu).
# Build context is the repository root. Targets: `moodle` (PHP-FPM + cron) and `nginx` (web).
# Multi-arch: builds unchanged on linux/amd64 and linux/arm64.

# ---------------------------------------------------------------- source
FROM debian:bookworm-slim AS source
RUN apt-get update \
    && apt-get install -y --no-install-recommends -o Acquire::Retries=10 ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*
COPY versions.lock scripts/fetch-moodle.sh /build/
# The cache mount keeps partial downloads between attempts, so a dropped connection resumes.
RUN --mount=type=cache,target=/dl sh /build/fetch-moodle.sh /build/versions.lock /src /dl

# ---------------------------------------------------------------- runtime
FROM php:8.4-fpm-bookworm AS runtime

ENV LANG=en_US.UTF-8 \
    OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_DISPLAY_ERRORS=0

# apt archives live in a cache mount so an interrupted build does not download them again.
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
    && apt-get update \
    && apt-get install -y --no-install-recommends -o Acquire::Retries=10 \
        locales git unzip ghostscript poppler-utils \
        fonts-noto-core fonts-noto-unhinted \
        libpq-dev libicu-dev libzip-dev libxml2-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libfcgi-bin procps \
    && sed -i -E 's/^# (en_US|en_AU|ar_SA|ur_PK)\.UTF-8 UTF-8/\1.UTF-8 UTF-8/' /etc/locale.gen \
    && grep -q '^ur_PK.UTF-8' /etc/locale.gen || echo 'ur_PK.UTF-8 UTF-8' >> /etc/locale.gen \
    && locale-gen \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pgsql pdo_pgsql intl gd zip soap opcache exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

RUN mkdir -p /var/moodledata /var/localcache /var/backups/hikmah \
    && chown www-data:www-data /var/moodledata /var/localcache

# ---------------------------------------------------------------- moodle
FROM runtime AS moodle
ARG COMPOSER_DEV=0
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=source /src/ /var/www/moodle/

# Composer's download cache lives in a cache mount, so rebuilding does not fetch packages again.
RUN --mount=type=cache,target=/root/.composer/cache \
    cd /var/www/moodle \
    && if [ "$COMPOSER_DEV" = "1" ]; then DEVFLAG=""; else DEVFLAG="--no-dev"; fi \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install $DEVFLAG \
        --no-interaction --prefer-dist --no-progress --optimize-autoloader

# Runtime configuration comes after the slow steps so that changing it rebuilds in seconds.
COPY docker/moodle/php.ini /usr/local/etc/php/conf.d/99-hikmah.ini
COPY docker/moodle/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-hikmah.conf
COPY docker/moodle/entrypoint.sh /usr/local/bin/hikmah-entrypoint
RUN chmod 755 /usr/local/bin/hikmah-entrypoint

# Hikmah code: plugins, theme, config and settings-as-code. Moodle core is never patched.
COPY plugins/payment/gateway/hikmahtransfer/ /var/www/moodle/public/payment/gateway/hikmahtransfer/
COPY plugins/local/hikmahattendance/ /var/www/moodle/public/local/hikmahattendance/
COPY plugins/theme/hikmah/ /var/www/moodle/public/theme/hikmah/
COPY plugins/mod/bigbluebuttonbn/extension/hikmah/ /var/www/moodle/public/mod/bigbluebuttonbn/extension/hikmah/
COPY config/config.php /var/www/moodle/config.php
COPY config/settings/ /opt/hikmah/settings/
COPY config/roles/ /opt/hikmah/roles/

WORKDIR /var/www/moodle
ENTRYPOINT ["hikmah-entrypoint"]
CMD ["fpm"]

# ---------------------------------------------------------------- nginx
FROM nginx:stable AS nginx
# Nginx serves moodledata files after Moodle's access check (X-Accel-Redirect). Those files belong
# to www-data (gid 33) and are not world-readable, so the nginx user joins that group.
RUN usermod -aG www-data nginx
COPY --from=moodle /var/www/moodle/public/ /var/www/moodle/public/
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
