FROM php:8.4-fpm

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_pgsql pgsql redis pcov zip bcmath opcache

# PCOV só liga quando pedimos (php -d pcov.enabled=1), para não pesar no dia a dia
RUN echo "pcov.enabled=0" > /usr/local/etc/php/conf.d/zz-pcov.ini

# Performance: OPcache guarda o código compilado em memória (evita recompilar a cada request)
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=1"; \
    echo "opcache.revalidate_freq=2"; \
    echo "realpath_cache_size=4096K"; \
    echo "realpath_cache_ttl=600"; \
    } > /usr/local/etc/php/conf.d/zz-opcache.ini

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
