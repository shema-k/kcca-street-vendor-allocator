# syntax=docker/dockerfile:1
#
# KCCA Vendor Relocation & Space Allocation System (VRSP) 5.0.0
#
# Apache + PHP 8.4 with the one extension the application actually uses:
# pdo_mysql. There is no Composer, npm or asset build step — the shipped
# tree is the deployment.
#
#   docker build -t kcca-vrsp:5.0.0 .
#
FROM php:8.4-apache

# ---------------------------------------------------------------------------
# Extensions
# ---------------------------------------------------------------------------
# mb_* has UTF-8 fallbacks in lib/Helpers.php, so mbstring is deliberately
# not installed. Nothing else in the tree needs a compiled extension.
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql

# ---------------------------------------------------------------------------
# Server configuration
# ---------------------------------------------------------------------------
COPY docker/php.ini            /usr/local/etc/php/conf.d/zz-kcca.ini
COPY docker/apache-security.conf /etc/apache2/conf-available/kcca.conf

RUN set -eux; \
    a2enmod headers; \
    a2enconf kcca; \
    mkdir -p /var/log/php /var/lib/php/sessions; \
    chown -R www-data:www-data /var/log/php /var/lib/php/sessions; \
    chmod 700 /var/log/php /var/lib/php/sessions

# ---------------------------------------------------------------------------
# Application
# ---------------------------------------------------------------------------
COPY --chown=root:root . /var/www/html

COPY docker/entrypoint.sh  /usr/local/bin/kcca-entrypoint
COPY docker/healthcheck.sh /usr/local/bin/kcca-healthcheck
RUN chmod 755 /usr/local/bin/kcca-entrypoint /usr/local/bin/kcca-healthcheck

# Defaults point at the companion MariaDB service in docker-compose.yml.
# Every value can be overridden at run time.
ENV KCCA_DB_HOST=db \
    KCCA_DB_PORT=3306 \
    KCCA_DB_SOCKET="" \
    KCCA_DB_NAME=kcca_vendor_relocation \
    KCCA_DB_USER=kcca \
    KCCA_DB_PASS=kcca_dev_2026 \
    KCCA_AUTO_SEED=1 \
    KCCA_DEBUG=0 \
    APACHE_DOCUMENT_ROOT=/var/www/html

WORKDIR /var/www/html

EXPOSE 80

# The public portal needs no sign-in and reads the database, so it is a
# truthful liveness check: it fails if Apache, PHP or MariaDB is down.
HEALTHCHECK --interval=15s --timeout=5s --start-period=120s --retries=10 \
    CMD /usr/local/bin/kcca-healthcheck

ENTRYPOINT ["/usr/local/bin/kcca-entrypoint"]
CMD ["apache2-foreground"]
