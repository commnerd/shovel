# Use PHP 8.3 with Apache
FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_sqlite mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install npm dependencies and build assets
RUN npm install && npm run build

# Configure Apache
RUN a2enmod rewrite
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Set up SQLite database
RUN touch /var/www/html/database/database.sqlite

# Configure Laravel for SQLite
RUN echo 'DB_CONNECTION=sqlite\n\
DB_DATABASE=/var/www/html/database/database.sqlite\n\
APP_ENV=production\n\
APP_DEBUG=false\n\
APP_KEY=\n\
APP_URL=http://localhost\n\
LOG_CHANNEL=stack\n\
LOG_LEVEL=debug\n\
MAIL_MAILER=log\n\
CACHE_DRIVER=file\n\
QUEUE_CONNECTION=sync\n\
SESSION_DRIVER=file\n\
SESSION_LIFETIME=120' > .env

# Generate application key
RUN php artisan key:generate

# Run migrations
RUN php artisan migrate --force

# Create storage link for public files
RUN php artisan storage:link

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
