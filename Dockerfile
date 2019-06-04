FROM syncxplus/php:7.3.6-cli-stretch

WORKDIR /data/

COPY . ./

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php", "index.php"]
