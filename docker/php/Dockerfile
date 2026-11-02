FROM php:8.4-cli-bookworm

ARG UID=1000
ARG GID=1000

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl pdo_mysql zip gd exif bcmath pcntl soap

RUN groupadd --gid ${GID} shopper \
    && useradd --uid ${UID} --gid ${GID} --create-home shopper \
    && mkdir -p /var/www/html/vendor \
    && chown -R shopper:shopper /var/www/html # The vendor volume inherits this owner, so Composer can write to it.

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-shopper.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
USER shopper

ENTRYPOINT ["entrypoint"]
CMD ["vendor/bin/testbench", "serve", "--host=0.0.0.0", "--port=8000"]
