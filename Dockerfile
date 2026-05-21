FROM php:8.2-apache

RUN a2enmod rewrite headers expires

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    default-mysql-client \
    unzip \
    curl \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        curl \
        gd \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY .docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/php/php-production.ini /usr/local/etc/php/conf.d/99-production.ini

WORKDIR /var/www/html
