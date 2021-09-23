FROM syncxplus/php:7.3.29-cli-buster

WORKDIR /data/

COPY . ./

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php", "index.php"]

VOLUME ["/data/images/"]
