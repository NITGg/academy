FROM php:7.4-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libxml2-dev \
    libicu-dev \
    libonig-dev \
    libexif-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-jpeg --with-freetype

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    soap \
    intl \
    opcache \
    mbstring \
    xml \
    exif

RUN echo "max_input_vars = 5000" > /usr/local/etc/php/conf.d/moodle.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "realpath_cache_size = 4096K" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "realpath_cache_ttl = 600" >> /usr/local/etc/php/conf.d/moodle.ini

RUN echo "opcache.enable = 1" > /usr/local/etc/php/conf.d/opcache-tuning.ini \
    && echo "opcache.memory_consumption = 256" >> /usr/local/etc/php/conf.d/opcache-tuning.ini \
    && echo "opcache.interned_strings_buffer = 16" >> /usr/local/etc/php/conf.d/opcache-tuning.ini \
    && echo "opcache.max_accelerated_files = 20000" >> /usr/local/etc/php/conf.d/opcache-tuning.ini \
    && echo "opcache.revalidate_freq = 60" >> /usr/local/etc/php/conf.d/opcache-tuning.ini \
    && echo "opcache.save_comments = 1" >> /usr/local/etc/php/conf.d/opcache-tuning.ini \
    && echo "opcache.enable_file_override = 1" >> /usr/local/etc/php/conf.d/opcache-tuning.ini \
    && echo "opcache.validate_timestamps = 1" >> /usr/local/etc/php/conf.d/opcache-tuning.ini \
    && echo "opcache.fast_shutdown = 1" >> /usr/local/etc/php/conf.d/opcache-tuning.ini

RUN a2enmod rewrite

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html

RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/html /var/www/moodledata \
    && chmod -R 755 /var/www/moodledata
