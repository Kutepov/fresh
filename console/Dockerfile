FROM yiisoftware/yii2-php:7.4-fpm

RUN docker-php-ext-install pcntl
RUN docker-php-ext-install sockets
RUN docker-php-ext-configure sockets
RUN docker-php-ext-enable sockets

RUN apt-get update && apt-get install -y \
    git \
    zip \
    curl \
    sudo \
    unzip \
    libzip-dev \
    libicu-dev \
    libbz2-dev \
    libpng-dev \
    libjpeg-dev \
    libmcrypt-dev \
    libreadline-dev \
    libfreetype6-dev \
    g++