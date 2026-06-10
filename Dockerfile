FROM php:8.2-apache

# Install system dependencies required for extensions and Composer
RUN apt-get update && apt-get install -y \
    libssl-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install and enable the MongoDB PHP extension
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Enable Apache mod_rewrite and configure AllowOverride for .htaccess
RUN a2enmod rewrite headers \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory to Apache's default web root
WORKDIR /var/www/html

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock* ./

# Install PHP dependencies via Composer
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of the application code
COPY . .

# Adjust permissions so Apache can serve the files
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 (Render forwards external traffic to this port)
EXPOSE 80
