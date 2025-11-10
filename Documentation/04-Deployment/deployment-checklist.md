# Deployment Checklist - Sci-Bono AI Fluency LMS

**Document Version:** 1.0
**Last Updated:** 2025-10-28
**Author:** Development Team & DevOps
**Status:** Active Deployment Guide

---

## Table of Contents

1. [Introduction](#introduction)
2. [Current Deployment: Static PWA](#current-deployment-static-pwa)
3. [Future Deployment: Full LAMP Stack](#future-deployment-full-lamp-stack)
4. [Pre-Deployment Checklist](#pre-deployment-checklist)
5. [Environment Configuration](#environment-configuration)
6. [Database Deployment](#database-deployment)
7. [Backend API Deployment](#backend-api-deployment)
8. [Frontend Deployment](#frontend-deployment)
9. [SSL/HTTPS Setup](#ssl-https-setup)
10. [Service Worker Versioning](#service-worker-versioning)
11. [Post-Deployment Verification](#post-deployment-verification)
12. [Rollback Procedures](#rollback-procedures)
13. [Monitoring & Health Checks](#monitoring--health-checks)
14. [Deployment Automation](#deployment-automation)
15. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This document provides comprehensive deployment checklists for both the current static PWA and the future full-stack LMS. It ensures consistent, error-free deployments across development, staging, and production environments.

### Deployment Types

1. **Current State:** Static HTML/CSS/JS with PWA features (no backend)
2. **Future State:** LAMP stack (PHP backend, MySQL database, static frontend)
3. **Migration State:** Parallel deployment (static + API coexist)

### Deployment Environments

- **Development:** Local developer machines (`localhost`)
- **Staging:** Pre-production testing environment (`staging.domain.com`)
- **Production:** Live user-facing system (`aifluency.sci-bono.org`)

---

## Current Deployment: Static PWA

### Static Site Requirements

**Server Requirements:**
- Web server (Apache, Nginx, or any static file server)
- HTTPS enabled (required for Service Worker)
- HTTP/2 recommended for performance

**No Requirements:**
- ❌ PHP (not needed)
- ❌ Database (not needed)
- ❌ Server-side processing

### Static Deployment Checklist

- [ ] **1. Prepare Files**
  - [ ] All HTML files present (69 files)
  - [ ] CSS files present (`css/styles.css`, `css/stylesModules.css`)
  - [ ] JavaScript files present (`js/script.js`)
  - [ ] Service Worker present (`service-worker.js`)
  - [ ] Manifest file present (`manifest.json`)
  - [ ] Images directory complete (`/images/`)

- [ ] **2. Configure Web Server**
  - [ ] Document root set to `/var/www/html/sci-bono-aifluency/`
  - [ ] Directory index: `index.html`
  - [ ] HTTPS enabled (SSL certificate installed)
  - [ ] Service Worker MIME type: `application/javascript`
  - [ ] Manifest MIME type: `application/manifest+json`

- [ ] **3. Update Service Worker Cache Version**
  ```javascript
  // In service-worker.js
  const CACHE_NAME = 'ai-fluency-cache-v2'; // Increment version
  ```
  - [ ] Cache version incremented
  - [ ] New files added to `urlsToCache` array
  - [ ] Old cache entries will be deleted on activation

- [ ] **4. Test Locally**
  - [ ] Serve site locally: `python3 -m http.server 8000` or `php -S localhost:8000`
  - [ ] Test in browser: `http://localhost:8000`
  - [ ] Verify all pages load
  - [ ] Check Service Worker registers (DevTools > Application)
  - [ ] Test offline functionality

- [ ] **5. Upload to Server**
  - [ ] Use FTP, SCP, rsync, or Git deployment
  - [ ] Preserve file permissions (644 for files, 755 for directories)
  - [ ] Verify all files transferred successfully

- [ ] **6. Post-Deployment Verification**
  - [ ] Visit production URL
  - [ ] Test 5 random pages
  - [ ] Check Service Worker status
  - [ ] Test PWA installation
  - [ ] Verify Google Analytics tracking

- [ ] **7. Clear CDN Cache** (if using CDN)
  - [ ] Cloudflare: Purge everything
  - [ ] AWS CloudFront: Create invalidation

### Apache Configuration (`.htaccess`)

```apache
# Enable HTTPS redirect
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Set correct MIME types
AddType application/manifest+json .json
AddType application/javascript .js

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/css "access plus 1 month"
</IfModule>
```

---

## Future Deployment: Full LAMP Stack

### Server Requirements

**Operating System:**
- Linux (Ubuntu 22.04 LTS or CentOS 8 recommended)
- Windows Server (alternative)

**Software Stack:**
- **Apache** 2.4+ or **Nginx** 1.18+
- **PHP** 8.1+ with extensions:
  - `pdo_mysql`
  - `mbstring`
  - `json`
  - `openssl`
- **MySQL** 8.0+ or **MariaDB** 10.6+
- **Composer** 2.x (PHP dependency manager)

**System Resources:**
- **Minimum:** 2 CPU cores, 4GB RAM, 20GB storage
- **Recommended:** 4 CPU cores, 8GB RAM, 50GB SSD

---

## Pre-Deployment Checklist

### Code Preparation

- [ ] **1. Version Control**
  - [ ] All changes committed to Git
  - [ ] Feature branch merged to `main`
  - [ ] Tagged release: `git tag -a v1.0.0 -m "Release v1.0.0"`
  - [ ] Pushed to remote: `git push origin main --tags`

- [ ] **2. Environment Variables**
  - [ ] `.env.example` file present in repository
  - [ ] `.env` file created for production (NOT committed)
  - [ ] All sensitive values configured (DB passwords, JWT secrets)

- [ ] **3. Dependencies**
  - [ ] PHP dependencies installed: `composer install --no-dev --optimize-autoloader`
  - [ ] Node modules (if any): `npm install --production`

- [ ] **4. Configuration Files**
  - [ ] `api/config/database.php` configured for production
  - [ ] `api/config/config.php` settings reviewed
  - [ ] Error reporting disabled in production (`display_errors = Off`)

- [ ] **5. Security Review**
  - [ ] All API endpoints require authentication (except login/register)
  - [ ] SQL injection protection (prepared statements used)
  - [ ] XSS protection (input sanitization, output escaping)
  - [ ] CSRF tokens implemented
  - [ ] Rate limiting configured
  - [ ] No debug code or console.log in production

- [ ] **6. Testing Complete**
  - [ ] All unit tests passing
  - [ ] Integration tests passing
  - [ ] Manual testing completed
  - [ ] User acceptance testing (UAT) approved
  - [ ] Performance benchmarks met

### Database Preparation

- [ ] **1. Backup Existing Database** (if any)
  ```bash
  mysqldump -u root -p ai_fluency_lms > backup_pre_deploy_$(date +%Y%m%d_%H%M%S).sql
  ```

- [ ] **2. Migration Scripts Ready**
  - [ ] All SQL migration files in `/api/migrations/`
  - [ ] Tested on staging environment
  - [ ] Rollback scripts prepared

- [ ] **3. Database User Permissions**
  - [ ] Production DB user created
  - [ ] Permissions granted (SELECT, INSERT, UPDATE, DELETE)
  - [ ] Remote access configured if needed

### Server Preparation

- [ ] **1. Server Access**
  - [ ] SSH access configured
  - [ ] SSH keys added (no password authentication)
  - [ ] Sudo privileges for deployment user

- [ ] **2. Firewall Configuration**
  - [ ] Port 80 (HTTP) open
  - [ ] Port 443 (HTTPS) open
  - [ ] Port 3306 (MySQL) restricted to localhost

- [ ] **3. SSL Certificate**
  - [ ] SSL certificate obtained (Let's Encrypt or commercial)
  - [ ] Certificate installed on server
  - [ ] Auto-renewal configured (Let's Encrypt)

---

## Environment Configuration

### `.env` File Template

```bash
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ai_fluency_lms
DB_USER=ai_fluency_user
DB_PASSWORD=strong_random_password_here

# JWT Configuration
JWT_SECRET=generate_random_64_character_string_here
JWT_EXPIRY=3600  # 1 hour in seconds
JWT_REFRESH_EXPIRY=2592000  # 30 days

# Application Configuration
APP_ENV=production  # development|staging|production
APP_DEBUG=false
APP_URL=https://aifluency.sci-bono.org

# Email Configuration (future)
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@sci-bono.org
MAIL_PASSWORD=email_password_here

# Analytics
GOOGLE_ANALYTICS_ID=G-VNN90D4GDE
GOOGLE_ADS_ID=ca-pub-6423925713865339
```

### Generate Secure Secrets

```bash
# Generate JWT secret (64 characters)
openssl rand -base64 48

# Generate database password (32 characters)
openssl rand -base64 24
```

### PHP Configuration (`php.ini`)

```ini
; Production settings
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Performance
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000

; Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; Upload limits
upload_max_filesize = 10M
post_max_size = 10M

; Session
session.cookie_httponly = On
session.cookie_secure = On
session.use_strict_mode = On
```

---

## Database Deployment

### Step 1: Create Database

```bash
# SSH into server
ssh user@server.com

# Access MySQL
mysql -u vuksDev -p Vu13#k*s3D3V
```

```sql
-- Create database
CREATE DATABASE ai_fluency_lms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'ai_fluency_user'@'localhost' IDENTIFIED BY 'strong_password_here';

-- Grant permissions
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER
ON ai_fluency_lms.* TO 'ai_fluency_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Exit MySQL
EXIT;
```

### Step 2: Run Migration Scripts

```bash
# Navigate to project directory
cd /var/www/html/sci-bono-aifluency/api

# Run migrations (in order)
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/001_create_users_table.sql
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/002_create_courses_modules_lessons.sql
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/003_create_quizzes_questions.sql
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/004_create_enrollments_progress.sql
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/005_create_certificates_submissions.sql
```

### Step 3: Verify Database Structure

```sql
-- Check all tables created
mysql -u ai_fluency_user -p ai_fluency_lms -e "SHOW TABLES;"

-- Verify table structures
mysql -u ai_fluency_user -p ai_fluency_lms -e "DESCRIBE users;"
mysql -u ai_fluency_user -p ai_fluency_lms -e "DESCRIBE courses;"
mysql -u ai_fluency_user -p ai_fluency_lms -e "DESCRIBE modules;"
mysql -u ai_fluency_user -p ai_fluency_lms -e "DESCRIBE lessons;"
```

### Step 4: Import Content Data

```bash
# Import course content (from migration scripts)
php /var/www/html/sci-bono-aifluency/scripts/migration/import-to-db.php

# Verify data imported
mysql -u ai_fluency_user -p ai_fluency_lms -e "SELECT COUNT(*) FROM lessons;"
```

**Expected Output:** 44 lessons

### Step 5: Create Admin User

```php
<?php
// create-admin.php (run once, then delete)
require_once 'api/config/database.php';

$email = 'admin@sci-bono.org';
$password = 'ChangeThisPassword123!';
$name = 'Admin User';

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$pdo->prepare("INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, 'admin')")
    ->execute([$email, $passwordHash, $name]);

echo "Admin user created: $email\n";
?>
```

```bash
php create-admin.php
rm create-admin.php  # Delete after use for security
```

---

## Backend API Deployment

**Status**: ✅ Development environment deployed and operational (2025-11-04)

### Development Environment Setup ✅

**Current Environment:**
- **Server**: Ubuntu 22.04 (WSL2)
- **Web Server**: Apache 2.4.52
- **PHP**: 8.1.2
- **MySQL**: 8.0.43
- **Database**: ai_fluency_lms
- **Project Path**: `/var/www/html/sci-bono-aifluency`

**Deployed Components:**
- ✅ API infrastructure (index.php, routing, configuration)
- ✅ Database (12 tables created, content migrated)
- ✅ Composer dependencies (JWT, PHPDotEnv)
- ✅ Security configuration (.htaccess, .env)
- ✅ Migration scripts executed
- ✅ 44 lessons imported
- ✅ 6 modules imported
- ✅ 1 quiz with 10 questions imported

### Step 1: Deploy API Files

```bash
# Using Git (recommended)
cd /var/www/html/sci-bono-aifluency
git pull origin main

# Or using SCP
scp -r api/ user@server:/var/www/html/sci-bono-aifluency/

# Or manual copy (development)
# Files already in place at /var/www/html/sci-bono-aifluency/api
```

**Important**: Never commit `.env` file! Ensure it's in `.gitignore`

### Step 2: Set File Permissions ✅

```bash
# API directory
chmod 755 /var/www/html/sci-bono-aifluency/api

# Config directory (restrict access)
chmod 750 /var/www/html/sci-bono-aifluency/api/config
chmod 640 /var/www/html/sci-bono-aifluency/api/config/*.php

# .env file (CRITICAL - most sensitive file)
chmod 600 /var/www/html/sci-bono-aifluency/api/.env

# Make writable: logs directory
mkdir -p /var/www/html/sci-bono-aifluency/api/logs
chmod 755 /var/www/html/sci-bono-aifluency/api/logs

# Vendor directory (read-only)
chmod -R 755 /var/www/html/sci-bono-aifluency/api/vendor

# Set owner to web server user
chown -R www-data:www-data /var/www/html/sci-bono-aifluency/api

# Verify permissions
ls -la /var/www/html/sci-bono-aifluency/api/.env
# Expected: -rw------- (600)
```

**Current Status**: ✅ File permissions configured for development environment

### Step 3: Install PHP Dependencies ✅

**Development:**
```bash
cd /var/www/html/sci-bono-aifluency/api
composer install
```

**Production:**
```bash
cd /var/www/html/sci-bono-aifluency/api
composer install --no-dev --optimize-autoloader --no-interaction
```

**Installed Dependencies:**
- firebase/php-jwt (v6.10) - JWT authentication
- vlucas/phpdotenv (v5.6) - Environment configuration
- phpunit/phpunit (v10.5) - Testing framework (dev only)

**Verify Installation:**
```bash
# Check composer.lock exists
ls -la composer.lock

# Verify vendor directory
ls -la vendor/

# Test autoloader
php -r "
require 'vendor/autoload.php';
echo class_exists('Firebase\JWT\JWT') ? 'JWT library loaded' : 'Failed';
"
```

**Current Status**: ✅ Composer dependencies installed and verified

### Step 4: Configure Apache for API ✅

**Apache Modules Required:**
```bash
# Enable mod_rewrite (URL rewriting)
sudo a2enmod rewrite

# Enable mod_headers (CORS, security headers)
sudo a2enmod headers

# Restart Apache
sudo systemctl restart apache2

# Verify modules
apache2ctl -M | grep -E 'rewrite|headers'
```

**Current Status**: ✅ Both modules enabled

**Actual `.htaccess` Implementation** (`/api/.htaccess`):

```apache
# Enable Rewrite Engine
RewriteEngine On

# Set base directory
RewriteBase /api/

# Block access to sensitive files and directories
RedirectMatch 403 ^/api/\.env$
RedirectMatch 403 ^/api/config/
RedirectMatch 403 ^/api/vendor/
RedirectMatch 403 ^/api/composer\.(json|lock)$
RedirectMatch 403 ^/api/\.git

# Allow access to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Route all requests to index.php
RewriteRule ^(.*)$ index.php [QSA,L]

# Set security headers
<IfModule mod_headers.c>
    # Prevent MIME type sniffing
    Header set X-Content-Type-Options "nosniff"

    # Enable XSS protection
    Header set X-XSS-Protection "1; mode=block"

    # Prevent clickjacking
    Header set X-Frame-Options "SAMEORIGIN"

    # Referrer policy
    Header set Referrer-Policy "strict-origin-when-cross-origin"

    # Content Security Policy
    Header set Content-Security-Policy "default-src 'self'"

    # CORS headers (configured in index.php based on allowed origins)
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    Header always set Access-Control-Max-Age "3600"
</IfModule>

# Disable directory listing
Options -Indexes

# Set default charset
AddDefaultCharset UTF-8

# Handle OPTIONS requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ index.php [QSA,L]
```

**CORS Configuration:**

CORS origins are configured in `/api/config/config.php`:
```php
define('CORS_ALLOWED_ORIGINS', [
    'https://aifluency.sci-bono.org',
    'https://www.sci-bono.org'
]);
```

In development mode (`APP_DEBUG=true`), localhost origins are automatically allowed.

**Verify Configuration:**
```bash
# Test Apache config syntax
sudo apache2ctl configtest
# Expected: Syntax OK

# Test .htaccess is working
php -r "
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REQUEST_URI'] = '/api/test';
\$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require '/var/www/html/sci-bono-aifluency/api/index.php';
"
# Should return JSON error (endpoint not found) - proves routing works
```

**Current Status**: ✅ Apache configured with URL rewriting and security headers

### Step 5: Configure Environment Variables ✅

**Create `/api/.env` file** with secure credentials:

```bash
# NEVER commit this file to git!
# Generate secure secrets using: openssl rand -base64 48

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ai_fluency_lms
DB_USER=ai_fluency_user
DB_PASSWORD=<SECURE_PASSWORD>

# Application Settings
APP_ENV=production  # or development
APP_DEBUG=false     # MUST be false in production
APP_URL=https://aifluency.sci-bono.org

# JWT Configuration
JWT_SECRET=<SECURE_64_CHAR_SECRET>
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=604800
JWT_ALGORITHM=HS256

# API Settings
API_VERSION=1.0
API_PREFIX=/api

# Security Settings
PASSWORD_MIN_LENGTH=8
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=60

# File Upload Settings
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=jpg,jpeg,png,gif,pdf
```

**Security Checklist:**
- ✅ `.env` file permissions set to 600 (read/write owner only)
- ✅ `.env` excluded in `.gitignore`
- ✅ JWT secret minimum 64 characters
- ✅ Database password minimum 20 characters with special chars
- ✅ `APP_DEBUG=false` in production
- ✅ HTTPS enforced in production URL

**Current Status**: ✅ Environment variables configured securely

### Step 6: Test API Infrastructure ✅

**Development Tests:**

```bash
# Test database connection
cd /var/www/html/sci-bono-aifluency/api
php -r "
require 'config/database.php';
echo \$pdo ? 'Database connected' : 'Connection failed';
"
# Expected: Database connected

# Test routing system
php -r "
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REQUEST_URI'] = '/api/courses';
\$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_USER_AGENT'] = 'Test';
require 'index.php';
"
# Expected: {"success":false,"message":"Controller CourseController not found"}
# This is correct - routing works, controllers not yet implemented

# Test User model
php -r "
require 'vendor/autoload.php';
require 'config/config.php';
require 'config/database.php';
\$user = new App\Models\User(\$pdo);
echo 'Users in database: ' . \$user->count();
"
# Expected: Users in database: 1
```

**Production Tests** (once controllers are implemented):

```bash
# Test registration endpoint
curl -X POST https://aifluency.sci-bono.org/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role": "student"
  }'
# Expected: 201 Created with user object and JWT tokens

# Test login endpoint
curl -X POST https://aifluency.sci-bono.org/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'
# Expected: 200 OK with JWT tokens

# Test authenticated endpoint
curl -X GET https://aifluency.sci-bono.org/api/auth/me \
  -H "Authorization: Bearer <ACCESS_TOKEN>"
# Expected: 200 OK with user profile

# Test courses endpoint (public)
curl -X GET https://aifluency.sci-bono.org/api/courses
# Expected: 200 OK with course list
```

**Current Status**:
- ✅ Infrastructure tests passing
- ⏳ Endpoint tests pending (controllers not yet implemented)

---

## Frontend Deployment

### Step 1: Update API Endpoint URLs

```javascript
// In js/auth.js (or config)
const API_BASE_URL = 'https://aifluency.sci-bono.org/api';

// Change from:
// const API_BASE_URL = 'http://localhost/api';
```

### Step 2: Update Service Worker Cache

```javascript
// In service-worker.js
const CACHE_NAME = 'ai-fluency-cache-v3';  // Increment version

const urlsToCache = [
    '/',
    '/index.html',
    '/login.html',
    '/signup.html',
    '/student-dashboard.html',
    '/css/styles.css',
    '/js/script.js',
    '/js/auth.js',  // Add new JS files
    // ... rest of files
];
```

### Step 3: Build Production Assets (if using build tools)

```bash
# If using webpack/vite/etc (future)
npm run build

# Otherwise, files are ready as-is
```

### Step 4: Deploy Frontend Files

```bash
# Using rsync (preserves timestamps)
rsync -avz --exclude='api/' --exclude='.git/' \
  /local/path/sci-bono-aifluency/ \
  user@server:/var/www/html/sci-bono-aifluency/

# Or using Git
ssh user@server
cd /var/www/html/sci-bono-aifluency
git pull origin main
```

### Step 5: Verify Frontend Loads

```bash
# Test homepage
curl -I https://aifluency.sci-bono.org/

# Expected: 200 OK

# Test login page
curl -I https://aifluency.sci-bono.org/login.html

# Expected: 200 OK
```

---

## SSL/HTTPS Setup

### Using Let's Encrypt (Free SSL)

```bash
# Install Certbot
sudo apt-get update
sudo apt-get install certbot python3-certbot-apache

# Obtain certificate
sudo certbot --apache -d aifluency.sci-bono.org

# Follow prompts:
# - Enter email address
# - Agree to terms
# - Choose redirect HTTP to HTTPS: Yes

# Verify auto-renewal
sudo certbot renew --dry-run
```

### Verify SSL Configuration

- [ ] Visit `https://aifluency.sci-bono.org`
- [ ] Check for padlock icon in browser
- [ ] No mixed content warnings
- [ ] SSL Labs test: https://www.ssllabs.com/ssltest/

**Target Grade:** A or A+

---

## Service Worker Versioning

### Strategy: Cache-First with Version Control

**When to Update Cache Version:**
- Any change to HTML files
- Any change to CSS/JS files
- New files added to cache
- Content updated

**Deployment Process:**
1. Update `CACHE_NAME` in `service-worker.js`:
   ```javascript
   const CACHE_NAME = 'ai-fluency-cache-vX';  // Increment X
   ```

2. Deploy new `service-worker.js` to server

3. Service Worker update lifecycle:
   - New SW installs in background
   - Waits for old SW to finish
   - Activates and deletes old cache
   - New version served to users on next page load

**User Experience:**
- First visit after update: Old cached version
- Second visit: New version (SW updated in background)
- Optional: Show "Update available" prompt for immediate refresh

---

## Post-Deployment Verification

### Smoke Tests (Run After Every Deployment)

- [ ] **1. Homepage Loads**
  - [ ] Visit `https://aifluency.sci-bono.org`
  - [ ] Page loads without errors
  - [ ] No console errors in browser

- [ ] **2. API Health Check**
  - [ ] `curl https://aifluency.sci-bono.org/api/health`
  - [ ] Returns `{"status":"ok"}`

- [ ] **3. User Registration**
  - [ ] Register new test user
  - [ ] Receive success response
  - [ ] User added to database

- [ ] **4. User Login**
  - [ ] Login with test user
  - [ ] Receive JWT token
  - [ ] Redirect to dashboard

- [ ] **5. Dashboard Loads**
  - [ ] Dashboard displays user data
  - [ ] No API errors
  - [ ] Navigation functional

- [ ] **6. Content Loads**
  - [ ] Navigate to lesson page
  - [ ] Content renders from database
  - [ ] SVG graphics display

- [ ] **7. Quiz Functionality**
  - [ ] Load quiz page
  - [ ] Submit quiz answers
  - [ ] Receive score

- [ ] **8. Logout**
  - [ ] Logout successfully
  - [ ] Token invalidated
  - [ ] Redirect to login

### Full Regression Test

- [ ] Run complete test suite from `testing-procedures.md`
- [ ] Verify no regressions from previous version
- [ ] Check performance metrics (page load < 2s)

### Monitoring Dashboard

- [ ] Google Analytics tracking active
- [ ] Error logging configured
- [ ] Server resource usage normal (CPU < 50%, RAM < 70%)

---

## Rollback Procedures

### Scenario 1: Frontend Issues

**Rollback Steps:**
```bash
# Revert to previous Git commit
git log  # Find previous working commit hash
git checkout <previous-commit-hash>

# Redeploy
rsync -avz /local/path/ user@server:/var/www/html/sci-bono-aifluency/

# Or on server
cd /var/www/html/sci-bono-aifluency
git reset --hard <previous-commit-hash>
```

### Scenario 2: API Issues

**Rollback Steps:**
```bash
# Stop web server (prevent requests to broken API)
sudo systemctl stop apache2

# Restore previous API code
cd /var/www/html/sci-bono-aifluency/api
git reset --hard <previous-commit-hash>
composer install

# Restart web server
sudo systemctl start apache2

# Test API health
curl https://aifluency.sci-bono.org/api/health
```

### Scenario 3: Database Issues

**Rollback Steps:**
```bash
# Restore database from pre-deployment backup
mysql -u ai_fluency_user -p ai_fluency_lms < backup_pre_deploy_YYYYMMDD_HHMMSS.sql

# Verify restoration
mysql -u ai_fluency_user -p ai_fluency_lms -e "SELECT COUNT(*) FROM users;"
```

### Scenario 4: Complete Rollback

**Emergency Revert:**
1. Restore database backup
2. Revert Git repository to previous version
3. Restart all services
4. Test full user workflow
5. Investigate issue offline, prepare hotfix

---

## Monitoring & Health Checks

### Automated Monitoring

**Uptime Monitoring:**
- Tool: UptimeRobot, Pingdom, or custom script
- Check URL: `https://aifluency.sci-bono.org/api/health`
- Frequency: Every 5 minutes
- Alert: Email/SMS if down for > 2 checks

**Server Monitoring:**
```bash
# Install monitoring agent (example: Netdata)
bash <(curl -Ss https://my-netdata.io/kickstart.sh)

# Monitor:
# - CPU usage
# - RAM usage
# - Disk space
# - MySQL connections
# - PHP-FPM processes
```

### Manual Health Checks (Daily)

```bash
# Check disk space
df -h

# Check Apache status
sudo systemctl status apache2

# Check MySQL status
sudo systemctl status mysql

# Check recent errors
sudo tail -50 /var/log/apache2/error.log
sudo tail -50 /var/log/php/error.log

# Check database connections
mysql -u root -p -e "SHOW PROCESSLIST;"
```

### Performance Monitoring

- **Google Analytics:** User engagement, page load times
- **Lighthouse CI:** Automated performance audits
- **Query Monitoring:** Slow query log in MySQL

```sql
-- Enable slow query log in MySQL
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;  -- Queries > 1 second logged
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';
```

---

## Deployment Automation

### Deployment Script (`deploy.sh`)

```bash
#!/bin/bash

# Deployment script for Sci-Bono AI Fluency LMS
# Usage: ./deploy.sh [environment]

ENVIRONMENT=${1:-production}

echo "Deploying to $ENVIRONMENT environment..."

# 1. Pull latest code
git pull origin main

# 2. Install dependencies
cd api
composer install --no-dev --optimize-autoloader
cd ..

# 3. Update Service Worker cache version (manual step reminder)
echo "REMINDER: Update CACHE_NAME in service-worker.js if needed"

# 4. Run database migrations (if any pending)
# php api/migrations/migrate.php

# 5. Clear caches
php api/clear-cache.php  # If implemented

# 6. Set permissions
chmod -R 755 api
chmod 750 api/config
chmod 640 api/config/*.php

# 7. Restart services
sudo systemctl reload apache2

# 8. Run smoke tests
echo "Running smoke tests..."
curl -f https://aifluency.sci-bono.org/api/health || { echo "Health check failed!"; exit 1; }

echo "Deployment complete!"
```

### CI/CD with GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader
        working-directory: api

      - name: Run tests
        run: vendor/bin/phpunit
        working-directory: api

      - name: Deploy to server
        uses: easingthemes/ssh-deploy@main
        with:
          SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
          REMOTE_HOST: ${{ secrets.REMOTE_HOST }}
          REMOTE_USER: ${{ secrets.REMOTE_USER }}
          TARGET: /var/www/html/sci-bono-aifluency/

      - name: Run post-deployment script
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.REMOTE_HOST }}
          username: ${{ secrets.REMOTE_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/html/sci-bono-aifluency
            ./deploy.sh production
```

---

## Related Documents

- **[Migration Roadmap](../01-Technical/01-Architecture/migration-roadmap.md)** - Migration phases and timeline
- **[Testing Procedures](../01-Technical/04-Development/testing-procedures.md)** - Pre-deployment testing
- **[Current Architecture](../01-Technical/01-Architecture/current-architecture.md)** - System architecture overview
- **[Setup Guide](../01-Technical/04-Development/setup-guide.md)** - Development environment setup

---

**Document End**

**Version History:**
- v1.0 (2025-10-28): Comprehensive deployment checklist created

**Maintained By:** DevOps Team & Development Team
**Review Schedule:** Update after each deployment
**Next Steps:** Execute first production deployment using this checklist
