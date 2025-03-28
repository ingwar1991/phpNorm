FROM php:8.3-cli
WORKDIR /var/www/project
RUN apt-get update && \
    apt-get install -y \
        git unzip vim wget libmariadb-dev && \
    docker-php-ext-install pdo pdo_mysql mysqli 
RUN wget -O phpunit.phar https://phar.phpunit.de/phpunit-10.phar && \
    chmod +x phpunit.phar && \
    mkdir /var/www/project/tools && mv phpunit.phar /var/www/project/tools
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
COPY project/composer.json /var/www/project/composer.json
RUN composer install
