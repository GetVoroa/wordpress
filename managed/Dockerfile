# Voroa Managed WordPress - WordPress + MariaDB in ONE container, all data on one disk at /data (ADR-0379).
# Build context is the repository root: `docker build -f managed/Dockerfile .`
# Shares every WordPress/PHP/nginx layer with the standard image (same rootfs/), then adds MariaDB.
FROM php:8.4-fpm-bookworm

ARG WP_VERSION=7.1.4
ARG WP_SHA1=b175879c4fb2d35300f9abe1153da9b543a35a2b
ARG WPCLI_VERSION=2.12.0

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends nginx curl ca-certificates less mariadb-server mariadb-client \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libzip-dev libicu-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql gd intl zip exif bcmath opcache; \
    pecl install redis apcu; \
    docker-php-ext-enable redis apcu; \
    apt-get purge -y --auto-remove libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libzip-dev libicu-dev; \
    apt-get install -y --no-install-recommends libpng16-16 libjpeg62-turbo libfreetype6 libwebp7 libzip4 libicu72; \
    rm -rf /var/lib/apt/lists/* /tmp/pear /var/lib/mysql/*

RUN set -eux; \
    curl -fsSL -o /tmp/wp.tar.gz "https://downloads.wordpress.org/release/wordpress-${WP_VERSION}.tar.gz"; \
    echo "${WP_SHA1}  /tmp/wp.tar.gz" | sha1sum -c -; \
    rm -rf /var/www/html; mkdir -p /var/www/html; \
    tar -xzf /tmp/wp.tar.gz -C /var/www/html --strip-components=1; \
    rm /tmp/wp.tar.gz; \
    mv /var/www/html/wp-content /usr/src/wp-content-seed; \
    ln -s /data/wp-content /var/www/html/wp-content; \
    chown -h root:root /var/www/html/wp-content; chown -R root:root /var/www/html; \
    find /var/www/html -type d -exec chmod 755 {} +; \
    find /var/www/html -type f -exec chmod 644 {} +

RUN set -eux; \
    curl -fsSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar"; \
    curl -fsSL -o /tmp/wp.sha512 "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar.sha512"; \
    echo "$(cat /tmp/wp.sha512)  /usr/local/bin/wp" | sha512sum -c -; \
    chmod 755 /usr/local/bin/wp; rm /tmp/wp.sha512

COPY rootfs/ /
COPY managed/rootfs/ /
RUN set -eux; \
    rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf.default /usr/local/etc/php-fpm.d/docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf /etc/nginx/sites-enabled/default; \
    chmod 755 /usr/local/bin/voroa-entrypoint /usr/local/bin/voroa-cron /usr/local/bin/voroa-managed-entrypoint /usr/local/bin/voroa-db-dump /usr/local/bin/voroa-fm /usr/local/bin/voroa-wp; \
    chown root:root /var/www/html/wp-config.php; chmod 644 /var/www/html/wp-config.php; \
    mkdir -p /var/cache/nginx/wp; chown -R www-data:www-data /var/cache/nginx

# Database browser (Voroa plan 2026-11-13 PR E): Adminer, pinned by version AND checksum, outside the docroot - only
# /usr/share/voroa-adminer/gate.php can load it, and only for a one-time link from the panel.
ARG ADMINER_VERSION=6.3.0
ARG ADMINER_SHA256=8bc0c48b26d0fbbe2698a1766c7afc65a4681c8efbb2f18e2dc57a9530367bbd
RUN set -eux; \
    curl -fsSL -o /usr/share/voroa-adminer/adminer.php "https://github.com/vrana/adminer/releases/download/v${ADMINER_VERSION}/adminer-${ADMINER_VERSION}-mysql-en.php"; \
    echo "${ADMINER_SHA256}  /usr/share/voroa-adminer/adminer.php" | sha256sum -c -; \
    chown -R root:root /usr/share/voroa-adminer; chmod 644 /usr/share/voroa-adminer/*.php

ENV PORT=3000
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/voroa-managed-entrypoint"]
