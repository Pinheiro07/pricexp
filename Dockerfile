FROM php:8.2-apache

# Install PDO and MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli && docker-php-ext-enable pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy the app files
COPY . /var/www/html/

# Expose port 80
EXPOSE 80
