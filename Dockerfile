FROM --platform=linux/amd64 syncxplus/php:7.4.33-cli-buster

WORKDIR /data/

COPY . ./

ENTRYPOINT ["docker-php-entrypoint"]

CMD ["php", "index.php"]

VOLUME ["/data/images/"]
