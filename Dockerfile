FROM syncxplus/php:7.4.28-cli-buster

WORKDIR /data/

COPY . ./

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php", "index.php"]

VOLUME ["/data/images/"]
