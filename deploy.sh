#!/bin/bash

# Deployment script for Laravel application with PostgreSQL
# Run this script on your VPS server

echo "Starting deployment process..."

# 1. Update system packages
echo "Updating system packages..."
sudo apt update && sudo apt upgrade -y

# 2. Install required packages
echo "Installing required packages..."
sudo apt install -y nginx postgresql postgresql-contrib php8.2-fpm php8.2-cli php8.2-common php8.2-pgsql php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-bcmath php8.2-intl git composer nodejs npm

# 3. Setup PostgreSQL database
echo "Setting up PostgreSQL database..."
sudo -u postgres psql -c "CREATE DATABASE your_database_name;"
sudo -u postgres psql -c "CREATE USER your_database_user WITH ENCRYPTED PASSWORD 'your_database_password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE your_database_name TO your_database_user;"
sudo -u postgres psql -c "ALTER USER your_database_user CREATEDB;"

# 4. Clone or update your application
echo "Setting up application directory..."
cd /var/www/
sudo git clone https://github.com/yourusername/your-repository.git your-app-name
sudo chown -R www-data:www-data /var/www/your-app-name
cd /var/www/your-app-name

# 5. Install PHP dependencies
echo "Installing PHP dependencies..."
sudo -u www-data composer install --no-dev --optimize-autoloader

# 6. Install and build frontend assets
echo "Installing and building frontend assets..."
sudo -u www-data npm install
sudo -u www-data npm run build

# 7. Set up environment file
echo "Setting up environment file..."
sudo -u www-data cp .env.production .env
# Make sure to edit .env with your actual database credentials

# 8. Generate application key
echo "Generating application key..."
sudo -u www-data php artisan key:generate

# 9. Run database migrations
echo "Running database migrations..."
sudo -u www-data php artisan migrate --force

# 10. Optimize Laravel
echo "Optimizing Laravel application..."
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# 11. Set proper permissions
echo "Setting proper permissions..."
sudo chown -R www-data:www-data /var/www/your-app-name
sudo chmod -R 755 /var/www/your-app-name
sudo chmod -R 775 /var/www/your-app-name/storage
sudo chmod -R 775 /var/www/your-app-name/bootstrap/cache

echo "Deployment completed! Don't forget to configure your Nginx virtual host."
echo "You'll also need to update your .env file with the correct database credentials."
