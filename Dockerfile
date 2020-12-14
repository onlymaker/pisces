FROM syncxplus/php:7.3.18-cli-stretch

WORKDIR /data/

COPY . ./

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php", "index.php"]

VOLUME ["/data/images/"]
