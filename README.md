# Laravel Admin Dashboard - Bot Web Universal

Web-based admin panel for managing multi-website bot automation configuration, data entries, and monitoring built with Laravel 11 and Filament.

---

## Overview

This Laravel application serves as the central control panel for the Bot Web Universal system. It provides a comprehensive admin interface to:

- **Configure target websites** with custom form steps and fields
- **Manage data entries** to be processed by the bot engine
- **Monitor job execution** with detailed logs and statistics
- **Manage proxies** for rotation and IP masking
- **Configure CAPTCHA services** (2Captcha, CapSolver)
- **Track bot sessions** and performance metrics

The admin panel communicates with the Node.js Bot Engine via REST API and uses Redis for queue management.

---

## Tech Stack

- **Framework**: Laravel 11
- **Admin Panel**: Filament 3.x
- **Database**: MySQL 8.0+ / MariaDB 10.6+
- **Cache/Queue**: Redis 6+
- **PHP**: 8.2+

---

## Features

### Data Management
- **Website Configuration**: Define target websites with custom headers, cookies, timeout settings
- **Form Steps & Fields**: Configure multi-step automation flows with dynamic field mapping
- **Data Entries**: Import CSV, create manually, view grouped by website
- **Bulk Operations**: Queue multiple entries, reset, cancel, delete in batch

### Monitoring & Logging
- **Job Logs**: Track every execution with timestamps, status, errors, and screenshots
- **Bot Sessions**: Monitor active browser sessions with metrics
- **Dashboard Statistics**: Real-time overview of pending, processing, success, and failed jobs

### Infrastructure Management
- **Proxy Pool**: Add HTTP/HTTPS/SOCKS5 proxies with rotation strategies
- **CAPTCHA Services**: Configure multiple CAPTCHA solving services with priority
- **Rate Limiting**: Control concurrency and jobs per minute per website

---

## Quick Start (Local Development)

### Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- MySQL 8.0+ or MariaDB 10.6+
- Redis 6+
- Node.js 18+ (for asset compilation)

### Installation Steps

1. **Install PHP Dependencies**
   ```bash
   composer install
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   ```

   Edit `.env` with your settings:
   ```env
   APP_NAME="Bot Web Universal"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=bot_web_universal
   DB_USERNAME=root
   DB_PASSWORD=your_password

   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379

   BOT_ENGINE_URL=http://localhost:3001
   BOT_ENGINE_API_KEY=your-secret-api-key

   TWOCAPTCHA_API_KEY=
   CAPSOLVER_API_KEY=
   ```

3. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

4. **Create Database**
   ```sql
   CREATE DATABASE bot_web_universal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. **Run Migrations & Seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Create Storage Link**
   ```bash
   php artisan storage:link
   ```

7. **Install Frontend Assets (Optional)**
   ```bash
   npm install
   npm run build
   ```

8. **Start Development Server**
   ```bash
   php artisan serve
   ```

9. **Access Admin Panel**
   
   Open browser: `http://localhost:8000/admin`
   
   **Default Login:**
   - Email: `admin@admin.com`
   - Password: `password`

10. **Start Queue Worker**
    ```bash
    php artisan queue:work redis
    ```

---

## Production Deployment

### Server Requirements

- Ubuntu 20.04+ / Debian 11+
- PHP 8.2-FPM
- Nginx or Apache
- MySQL 8.0+ / MariaDB 10.6+
- Redis 6+
- Supervisor (for queue workers)
- SSL Certificate (Let's Encrypt recommended)

### Deployment Steps

1. **Upload Project Files**
   ```bash
   rsync -avz --exclude 'vendor' --exclude 'node_modules' \
     ./website/ user@server:/var/www/bot-web-universal/website/
   ```

2. **Install Dependencies**
   ```bash
   cd /var/www/bot-web-universal/website
   composer install --no-dev --optimize-autoloader
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   nano .env
   ```

   Production `.env`:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=bot_web_universal
   DB_USERNAME=botuser
   DB_PASSWORD=secure_password

   REDIS_PASSWORD=secure_redis_password

   BOT_ENGINE_URL=http://127.0.0.1:3001
   BOT_ENGINE_API_KEY=secure-api-key
   ```

4. **Setup Application**
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. **Set Permissions**
   ```bash
   sudo chown -R www-data:www-data /var/www/bot-web-universal/website
   sudo chmod -R 755 /var/www/bot-web-universal/website
   sudo chmod -R 775 /var/www/bot-web-universal/website/storage
   sudo chmod -R 775 /var/www/bot-web-universal/website/bootstrap/cache
   ```

6. **Configure Nginx**

   Create `/etc/nginx/sites-available/bot-web-universal`:
   ```nginx
   server {
       listen 80;
       server_name your-domain.com;
       root /var/www/bot-web-universal/website/public;

       add_header X-Frame-Options "SAMEORIGIN";
       add_header X-Content-Type-Options "nosniff";

       index index.php;
       charset utf-8;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location = /favicon.ico { access_log off; log_not_found off; }
       location = /robots.txt  { access_log off; log_not_found off; }

       error_page 404 /index.php;

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```

   Enable site:
   ```bash
   sudo ln -s /etc/nginx/sites-available/bot-web-universal /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl restart nginx
   ```

7. **Setup SSL**
   ```bash
   sudo certbot --nginx -d your-domain.com
   ```

8. **Configure Supervisor for Queue Worker**

   Create `/etc/supervisor/conf.d/laravel-worker.conf`:
   ```ini
   [program:laravel-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/bot-web-universal/website/artisan queue:work redis --sleep=3 --tries=3
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/var/log/laravel-worker.log
   ```

   Update supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start laravel-worker:*
   ```

9. **Setup Cron for Scheduler**
   ```bash
   sudo crontab -e -u www-data
   ```

   Add:
   ```cron
   * * * * * cd /var/www/bot-web-universal/website && php artisan schedule:run >> /dev/null 2>&1
   ```

---

## Project Structure

```
website/
├── app/
│   ├── Filament/
│   │   └── Resources/           # Admin CRUD resources
│   │       ├── DataEntryResource.php
│   │       ├── WebsiteResource.php
│   │       ├── ProxyResource.php
│   │       └── ...
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/             # API for Bot Engine
│   ├── Models/                  # Eloquent models
│   └── Jobs/                    # Queue jobs
├── config/                      # Configuration files
├── database/
│   ├── migrations/              # Database schema
│   └── seeders/                 # Seed data
├── resources/
│   └── views/                   # Blade templates
├── routes/
│   ├── api.php                  # API routes
│   └── web.php                  # Web routes
├── storage/
│   ├── app/
│   │   └── public/              # Public uploads
│   └── logs/                    # Application logs
└── public/                      # Web root
```

---

## Key Models

| Model | Description |
|-------|-------------|
| `Website` | Target website configuration with form steps |
| `FormStep` | Individual step in automation flow |
| `FormField` | Fields to fill in each step |
| `DataEntry` | Data records to be processed |
| `Proxy` | Proxy server configurations |
| `CaptchaService` | CAPTCHA solving service configs |
| `JobLog` | Execution logs for each job |
| `BotSession` | Active browser session tracking |

---

## API Endpoints

The Laravel application exposes REST API for the Bot Engine:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/websites/{slug}/config` | Get website configuration with steps |
| POST | `/api/jobs/{id}/status` | Update job status |
| POST | `/api/jobs/{id}/log` | Create log entry |
| GET | `/api/proxies/random` | Get random active proxy |
| GET | `/api/captcha/solve` | Request CAPTCHA solving |

---

## Usage

### 1. Configure a Website

1. Go to **Bot Configuration** → **Websites** → **Create**
2. Fill in:
   - Name, Slug, Base URL
   - Timeout, retry settings, concurrency
   - Priority and max jobs per minute
   - Custom headers/cookies (optional)
3. Save website

### 2. Define Form Steps

1. Open the website record → **Form Steps** tab
2. Add steps in order:
   - Navigation (go to URL)
   - Wait (for element/timeout)
   - Input (fill form fields)
   - Click (buttons, links)
   - CAPTCHA (solve if present)
   - Screenshot (capture evidence)

### 3. Add Data Entries

**Manual:**
1. Go to **Data Management** → **Data Entries**
2. Select website group
3. Click **New Entry**
4. Fill in form data (key-value pairs)

**CSV Import:**
1. Click **Import CSV**
2. Upload CSV file
3. Map identifier column (e.g., NIK)
4. Submit

### 4. Queue Jobs

1. Select entries from the list
2. Click **Queue Selected** or use bulk action
3. Jobs are sent to Bot Engine via API
4. Monitor progress in **Job Logs**

---

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_*` | Database connection settings | - |
| `REDIS_*` | Redis connection settings | - |
| `BOT_ENGINE_URL` | Bot Engine API endpoint | `http://localhost:3001` |
| `BOT_ENGINE_API_KEY` | Shared secret for API auth | - |
| `TWOCAPTCHA_API_KEY` | 2Captcha API key | - |
| `CAPSOLVER_API_KEY` | CapSolver API key | - |

---

## Common Commands

```bash
# Clear all caches
php artisan optimize:clear

# Cache configs (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Create admin user
php artisan make:filament-user

# Queue worker
php artisan queue:work redis --tries=3

# View logs
tail -f storage/logs/laravel.log
```

---

## Troubleshooting

### Permission Errors
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Database Connection Issues
```bash
# Test connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### Queue Not Processing
```bash
# Check Redis connection
redis-cli ping

# Restart queue worker
sudo supervisorctl restart laravel-worker:*
```

### Clear All Caches
```bash
php artisan optimize:clear
composer dump-autoload
```

---

## Security

- Change default admin password after first login
- Use strong `APP_KEY` and `BOT_ENGINE_API_KEY`
- Enable HTTPS in production
- Restrict API access to Bot Engine IP only
- Regularly update dependencies: `composer update`
- Monitor logs for unauthorized access

---

## Support

For issues or questions:
- Check Laravel logs: `storage/logs/laravel.log`
- Review Filament documentation: https://filamentphp.com/docs
- Contact system administrator
