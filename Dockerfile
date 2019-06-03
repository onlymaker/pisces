FROM syncxplus/php:7.3.6-cli-stretch

WORKDIR /data/

COPY . ./

RUN composer install --prefer-dist --optimize-autoloader && composer clear-cache

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php", "index.php"]
