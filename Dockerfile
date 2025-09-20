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

# Install npm dependencies (but don't build yet)
RUN npm install

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

# Set environment variables directly (no .env file needed)
ENV APP_NAME="Foca"
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_URL=http://localhost
ENV LOG_CHANNEL=stack
ENV LOG_LEVEL=debug
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=/var/www/html/database/database.sqlite
ENV MAIL_MAILER=log
ENV CACHE_DRIVER=file
ENV QUEUE_CONNECTION=sync
ENV SESSION_DRIVER=file
ENV SESSION_LIFETIME=120

# Generate application key first
RUN php artisan key:generate --show > app_key.txt

# Create a minimal .env file for Laravel commands
RUN echo "APP_NAME=Foca" > .env && \
    echo "APP_ENV=production" >> .env && \
    echo "APP_DEBUG=false" >> .env && \
    echo "APP_URL=http://localhost" >> .env && \
    echo "APP_KEY=$(cat app_key.txt)" >> .env && \
    echo "LOG_CHANNEL=stack" >> .env && \
    echo "LOG_LEVEL=debug" >> .env && \
    echo "DB_CONNECTION=sqlite" >> .env && \
    echo "DB_DATABASE=/var/www/html/database/database.sqlite" >> .env && \
    echo "MAIL_MAILER=log" >> .env && \
    echo "CACHE_DRIVER=file" >> .env && \
    echo "QUEUE_CONNECTION=sync" >> .env && \
    echo "SESSION_DRIVER=file" >> .env && \
    echo "SESSION_LIFETIME=120" >> .env && \
    rm app_key.txt

# Run migrations
RUN php artisan migrate --force

# Create storage link for public files
RUN php artisan storage:link

# Generate wayfinder files and build frontend assets in single layer
RUN echo "=== WAYFINDER GENERATION START ===" && \
    echo "Working directory: $(pwd)" && \
    echo "Laravel version: $(php artisan --version)" && \
    echo "Environment check: $(php artisan env)" && \
    echo "Route list before generation:" && \
    WAYFINDER_BUILD=true APP_ENV=local php artisan route:list --name=register && \
    echo "=== RUNNING WAYFINDER GENERATION ===" && \
    php artisan config:clear && \
    php artisan route:clear && \
    WAYFINDER_BUILD=true APP_ENV=local php artisan wayfinder:generate --verbose --env=local || (echo "WAYFINDER GENERATION FAILED!" && exit 1) && \
    echo "=== CHECKING GENERATION RESULTS ===" && \
    ls -la resources/js/ && \
    if [ -d "resources/js/actions" ]; then \
        echo "✓ Actions directory exists" && \
        echo "Actions structure:" && \
        find resources/js/actions -type f -name "*.ts" | sort && \
        echo "=== CRITICAL FILE CHECK ===" && \
        if [ -f "resources/js/actions/App/Http/Controllers/Auth/RegisteredUserController.ts" ]; then \
            echo "✓ SUCCESS: RegisteredUserController.ts found!" && \
            ls -la resources/js/actions/App/Http/Controllers/Auth/RegisteredUserController.ts; \
        else \
            echo "✗ CRITICAL ERROR: RegisteredUserController.ts NOT FOUND!" && \
            echo "Auth directory contents:" && \
            ls -la resources/js/actions/App/Http/Controllers/Auth/ || echo "Auth directory doesn't exist" && \
            exit 1; \
        fi; \
    else \
        echo "✗ FATAL ERROR: Actions directory was not created!" && \
        exit 1; \
    fi && \
    echo "=== WAYFINDER GENERATION COMPLETE ===" && \
    echo "=== PRE-BUILD FILE VERIFICATION ===" && \
    echo "Checking if RegisteredUserController.ts still exists before npm build:" && \
    ls -la resources/js/actions/App/Http/Controllers/Auth/RegisteredUserController.ts && \
    echo "File contents preview:" && \
    head -5 resources/js/actions/App/Http/Controllers/Auth/RegisteredUserController.ts && \
    echo "Working directory: $(pwd)" && \
    echo "Full actions directory structure:" && \
    find resources/js/actions -name "*.ts" | grep -E "(Auth|RegisteredUser)" && \
    echo "=== STARTING NPM BUILD ===" && \
    npm run build

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
