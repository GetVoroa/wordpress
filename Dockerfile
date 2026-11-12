# Voroa WordPress - optimised WordPress for Voroa (php-fpm + nginx, page cache, hardened).
# One container, one listening port ($PORT, default 3000). Uploads/plugins/themes live in
# /var/www/html/wp-content - attach a persistent disk there or they are lost on redeploy.
FROM php:8.4-fpm-bookworm

ARG WP_VERSION=7.1.4
ARG WP_SHA1=b175879c4fb2d35300f9abe1153da9b543a35a2b
ARG WPCLI_VERSION=2.12.0

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends nginx curl ca-certificates less default-mysql-client \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libzip-dev libicu-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql gd intl zip exif bcmath opcache; \
    pecl install redis apcu; \
    docker-php-ext-enable redis apcu; \
    apt-get purge -y --auto-remove libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libzip-dev libicu-dev; \
    apt-get install -y --no-install-recommends libpng16-16 libjpeg62-turbo libfreetype6 libwebp7 libzip4 libicu72; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

# WordPress core, verified by checksum, owned by root (read-only for the web user).
RUN set -eux; \
    curl -fsSL -o /tmp/wp.tar.gz "https://downloads.wordpress.org/release/wordpress-${WP_VERSION}.tar.gz"; \
    echo "${WP_SHA1}  /tmp/wp.tar.gz" | sha1sum -c -; \
    rm -rf /var/www/html; mkdir -p /var/www/html; \
    tar -xzf /tmp/wp.tar.gz -C /var/www/html --strip-components=1; \
    rm /tmp/wp.tar.gz; \
    mv /var/www/html/wp-content /usr/src/wp-content-seed; \
    mkdir -p /var/www/html/wp-content; \
    chown -R root:root /var/www/html; \
    find /var/www/html -type d -exec chmod 755 {} +; \
    find /var/www/html -type f -exec chmod 644 {} +

# WP-CLI (used by the in-container cron loop and for maintenance over SSH).
RUN set -eux; \
    curl -fsSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar"; \
    curl -fsSL -o /tmp/wp.sha512 "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar.sha512"; \
    echo "$(cat /tmp/wp.sha512)  /usr/local/bin/wp" | sha512sum -c -; \
    chmod 755 /usr/local/bin/wp; rm /tmp/wp.sha512

COPY rootfs/ /
RUN set -eux; \
    rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf.default /usr/local/etc/php-fpm.d/docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf /etc/nginx/sites-enabled/default; \
    chmod 755 /usr/local/bin/voroa-entrypoint /usr/local/bin/voroa-cron; \
    chown root:root /var/www/html/wp-config.php; chmod 644 /var/www/html/wp-config.php; \
    mkdir -p /var/cache/nginx/wp; chown -R www-data:www-data /var/cache/nginx

ENV PORT=3000
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/voroa-entrypoint"]
