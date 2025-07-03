<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Daylight - Laravel Application

This is a Laravel application configured to work with PostgreSQL database for optimal performance and smaller memory usage.

## PostgreSQL Deployment Guide

### Prerequisites

-   VPS server with Ubuntu/Debian
-   Root or sudo access
-   Domain name pointing to your server (optional)

### Quick Deployment

1. **Upload files to your VPS:**

    ```bash
    # Clone your repository or upload files to /var/www/your-app-name/
    ```

2. **Run the deployment script:**

    ```bash
    chmod +x deploy.sh
    ./deploy.sh
    ```

3. **Configure your database credentials:**

    - Edit the `.env` file with your actual PostgreSQL credentials
    - Update the database name, username, and password

4. **Set up Nginx:**
    ```bash
    sudo cp nginx-config.conf /etc/nginx/sites-available/your-app-name
    sudo ln -s /etc/nginx/sites-available/your-app-name /etc/nginx/sites-enabled/
    sudo nginx -t
    sudo systemctl reload nginx
    ```

### Manual Setup Steps

If you prefer to set up manually:

1. **Install PostgreSQL:**

    ```bash
    sudo apt update
    sudo apt install postgresql postgresql-contrib
    ```

2. **Create database and user:**

    ```bash
    sudo -u postgres psql
    CREATE DATABASE your_database_name;
    CREATE USER your_database_user WITH ENCRYPTED PASSWORD 'your_secure_password';
    GRANT ALL PRIVILEGES ON DATABASE your_database_name TO your_database_user;
    ALTER USER your_database_user CREATEDB;
    \q
    ```

3. **Install PHP and required extensions:**

    ```bash
    sudo apt install php8.2-fpm php8.2-cli php8.2-pgsql php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-bcmath
    ```

4. **Configure your application:**
    - Copy `.env.production` to `.env`
    - Update database credentials in `.env`
    - Run `php artisan key:generate`
    - Run `php artisan migrate`

### Environment Configuration

The application is configured to use PostgreSQL with the following benefits:

-   **Lower memory usage** compared to MySQL
-   **Better performance** for complex queries
-   **Advanced data types** and JSON support
-   **ACID compliance** and better concurrency

### Database Configuration

The application uses these PostgreSQL settings:

-   **Port:** 5432 (default)
-   **Charset:** utf8
-   **SSL Mode:** prefer
-   **Search Path:** public

### Security Considerations

1. **Database Security:**

    - Use strong passwords for database users
    - Limit database access to localhost only
    - Regular backups

2. **Application Security:**
    - Set `APP_DEBUG=false` in production
    - Use HTTPS (configure SSL certificates)
    - Keep dependencies updated

### Monitoring and Maintenance

-   Monitor PostgreSQL performance with `pg_stat_activity`
-   Set up regular database backups
-   Monitor application logs in `storage/logs/`
-   Use tools like `htop` and `iotop` for system monitoring

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

-   [Simple, fast routing engine](https://laravel.com/docs/routing).
-   [Powerful dependency injection container](https://laravel.com/docs/container).
-   Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
-   Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
-   Database agnostic [schema migrations](https://laravel.com/docs/migrations).
-   [Robust background job processing](https://laravel.com/docs/queues).
-   [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

-   **[Vehikl](https://vehikl.com/)**
-   **[Tighten Co.](https://tighten.co)**
-   **[WebReinvent](https://webreinvent.com/)**
-   **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
-   **[64 Robots](https://64robots.com)**
-   **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
-   **[Cyber-Duck](https://cyber-duck.co.uk)**
-   **[DevSquad](https://devsquad.com/hire-laravel-developers)**
-   **[Jump24](https://jump24.co.uk)**
-   **[Redberry](https://redberry.international/laravel/)**
-   **[Active Logic](https://activelogic.com)**
-   **[byte5](https://byte5.de)**
-   **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
