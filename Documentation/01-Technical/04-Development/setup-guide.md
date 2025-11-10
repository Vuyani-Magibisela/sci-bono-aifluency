# Development Setup Guide - Sci-Bono AI Fluency Platform

**Document Version:** 1.0
**Last Updated:** 2025-10-27
**Author:** Development Team
**Status:** Complete

---

## Table of Contents

1. [Introduction](#introduction)
2. [System Requirements](#system-requirements)
3. [Current Setup (Static PWA)](#current-setup-static-pwa)
4. [Future Setup (Full LMS)](#future-setup-full-lms)
5. [Installation Instructions](#installation-instructions)
6. [Development Tools](#development-tools)
7. [Testing Environment](#testing-environment)
8. [Troubleshooting](#troubleshooting)
9. [Best Practices](#best-practices)
10. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This guide provides step-by-step instructions for setting up a development environment for the Sci-Bono AI Fluency platform. It covers both the current static PWA implementation and the future full LMS with backend.

### Audience

- **New Developers** - Setting up for the first time
- **Frontend Developers** - Working on HTML/CSS/JavaScript
- **Backend Developers** - Future PHP/MySQL development
- **Content Creators** - Adding courses and chapters
- **QA Testers** - Testing environment setup

### What You'll Learn

- How to set up a local web server
- How to test the PWA offline functionality
- How to debug with browser DevTools
- How to prepare for backend development
- How to contribute to the codebase

---

## System Requirements

### Minimum Requirements

**For Current Static PWA Development:**

| Component | Requirement |
|-----------|-------------|
| **Operating System** | Windows 10+, macOS 10.15+, Ubuntu 20.04+ |
| **RAM** | 4 GB minimum, 8 GB recommended |
| **Disk Space** | 500 MB for project + tools |
| **Web Server** | Apache 2.4+, Nginx 1.18+, or Node.js built-in server |
| **Browser** | Chrome 90+, Firefox 88+, or Edge 90+ |
| **Text Editor** | VS Code, Sublime Text, or any code editor |

**For Future LMS Development:**

| Component | Requirement |
|-----------|-------------|
| **PHP** | 8.1 or higher |
| **MySQL** | 8.0 or higher |
| **Composer** | 2.0+ (PHP dependency manager) |
| **Node.js** | 18+ (for build tools, optional) |
| **Git** | Latest version |

---

### Recommended Specifications

**Developer Workstation:**
- **RAM:** 16 GB
- **CPU:** Quad-core 2.5 GHz+
- **SSD:** For faster file operations
- **Display:** 1920x1080 minimum (dual monitors recommended)

**Internet Connection:**
- Required for CDN resources (Font Awesome, jsPDF, html2canvas)
- Required for testing PWA installation
- Required for Git operations

---

## Current Setup (Static PWA)

### Overview

The current platform is a **static Progressive Web App** with:
- No backend server required
- No database
- Pure HTML/CSS/JavaScript
- Can run on any static web server

### Quick Start (5 Minutes)

**Option 1: Python Simple Server**

```bash
# Navigate to project directory
cd /var/www/html/sci-bono-aifluency

# Start server (Python 3)
python3 -m http.server 8000

# Open browser
# Navigate to: http://localhost:8000
```

**Option 2: PHP Built-in Server**

```bash
# Navigate to project directory
cd /var/www/html/sci-bono-aifluency

# Start server
php -S localhost:8000

# Open browser
# Navigate to: http://localhost:8000
```

**Option 3: Node.js http-server**

```bash
# Install http-server globally (one time)
npm install -g http-server

# Navigate to project directory
cd /var/www/html/sci-bono-aifluency

# Start server
http-server -p 8000

# Open browser
# Navigate to: http://localhost:8000
```

**Option 4: VS Code Live Server Extension**

```
1. Install "Live Server" extension in VS Code
2. Open project folder in VS Code
3. Right-click index.html
4. Select "Open with Live Server"
5. Browser opens automatically
```

---

### Detailed Setup: Apache (Recommended for Production-Like Testing)

#### Windows Setup (XAMPP)

**1. Download XAMPP**
```
URL: https://www.apachefriends.org/download.html
Version: 8.1.x or higher (includes Apache + PHP + MySQL)
```

**2. Install XAMPP**
```
- Run installer
- Choose components: Apache, PHP, MySQL (optional for now)
- Install to: C:\xampp (default)
- Allow firewall access when prompted
```

**3. Configure Apache**

Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName scibono-ai-fluency.local
    DocumentRoot "C:/path/to/sci-bono-aifluency"

    <Directory "C:/path/to/sci-bono-aifluency">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/scibono-error.log"
    CustomLog "logs/scibono-access.log" common
</VirtualHost>
```

**4. Edit Hosts File**

Edit `C:\Windows\System32\drivers\etc\hosts` (as Administrator):

```
127.0.0.1    scibono-ai-fluency.local
```

**5. Start Apache**
```
- Open XAMPP Control Panel
- Click "Start" next to Apache
- Visit: http://scibono-ai-fluency.local
```

---

#### macOS Setup (Homebrew + Apache)

**1. Install Homebrew** (if not installed)
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

**2. Install Apache**
```bash
brew install httpd
```

**3. Configure Apache**

Edit `/opt/homebrew/etc/httpd/httpd.conf`:

```apache
# Enable modules
LoadModule rewrite_module lib/httpd/modules/mod_rewrite.so

# Change DocumentRoot
DocumentRoot "/Users/yourname/Sites/sci-bono-aifluency"
<Directory "/Users/yourname/Sites/sci-bono-aifluency">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Change Listen port if needed
Listen 8080
```

**4. Start Apache**
```bash
brew services start httpd

# Or start manually
sudo apachectl start
```

**5. Access Site**
```
http://localhost:8080
```

---

#### Linux Setup (Ubuntu/Debian)

**1. Install Apache**
```bash
sudo apt update
sudo apt install apache2
```

**2. Create Project Directory**
```bash
sudo mkdir -p /var/www/html/sci-bono-aifluency
sudo chown -R $USER:$USER /var/www/html/sci-bono-aifluency
```

**3. Configure Virtual Host**

Create `/etc/apache2/sites-available/scibono.conf`:

```apache
<VirtualHost *:80>
    ServerName scibono-ai-fluency.local
    ServerAdmin admin@scibono.local
    DocumentRoot /var/www/html/sci-bono-aifluency

    <Directory /var/www/html/sci-bono-aifluency>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/scibono-error.log
    CustomLog ${APACHE_LOG_DIR}/scibono-access.log combined
</VirtualHost>
```

**4. Enable Site**
```bash
sudo a2ensite scibono.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**5. Edit Hosts File**
```bash
sudo nano /etc/hosts

# Add line:
127.0.0.1    scibono-ai-fluency.local
```

**6. Access Site**
```
http://scibono-ai-fluency.local
```

---

### Project Structure

```
sci-bono-aifluency/
├── index.html                  # Landing page
├── service-worker.js           # PWA Service Worker
├── manifest.json               # PWA Manifest
├── offline.html                # Offline fallback
│
├── css/
│   ├── styles.css              # Main styles
│   └── stylesModules.css       # Module-specific styles
│
├── js/
│   └── script.js               # Main JavaScript
│
├── images/
│   ├── android-chrome-192x192.png
│   ├── android-chrome-512x512.png
│   ├── apple-touch-icon.png
│   └── favicon.ico
│
├── chapter*.html               # 50+ chapter files
├── module*.html                # 6 module overview pages
├── module*Quiz.html            # 6 quiz pages
│
├── Documentation/              # Project documentation
│   ├── 01-Technical/
│   ├── 02-User-Guides/
│   └── README.md
│
├── CLAUDE.md                   # AI assistant guidelines
└── CHANGELOG.md                # Version history
```

---

### Verifying Setup

**1. Check Landing Page**
```
Navigate to: http://localhost:8000/
Expected: AI Fluency landing page loads
```

**2. Check CSS Loading**
```
Open DevTools (F12) → Network tab
Reload page
Expected: styles.css loads with status 200
```

**3. Check JavaScript**
```
Open DevTools → Console
Expected: No errors (except possible SW warnings on first load)
```

**4. Check Service Worker**
```
Open DevTools → Application → Service Workers
Expected: "Status: activated and running"
```

**5. Test Navigation**
```
Click "Start Course" button
Expected: Redirects to aifluencystart.html
```

**6. Test Offline Mode**
```
DevTools → Network → Check "Offline"
Reload page
Expected: Page loads from cache
```

---

## Full LMS Setup (✅ Phase 1 Complete)

### Overview

**Status**: API infrastructure implemented and operational (as of 2025-11-04)

The LMS backend requires:
- **Backend:** PHP 8.1+ with Apache/Nginx ✅
- **Database:** MySQL 8.0+ ✅
- **API:** RESTful API for frontend-backend communication ✅
- **Authentication:** JWT-based auth system ✅

### Full Stack Setup

#### 1. Install LAMP/LEMP Stack ✅

**Current Environment:**
- **OS:** Ubuntu 22.04 (WSL2)
- **Web Server:** Apache 2.4.52
- **PHP:** 8.1.2
- **MySQL:** 8.0.43

**Ubuntu - LAMP Stack Installation**

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install MySQL
sudo apt install mysql-server -y
sudo mysql_secure_installation

# Install PHP and extensions
sudo apt install php8.1 php8.1-cli php8.1-fpm php8.1-mysql \
                 php8.1-curl php8.1-mbstring php8.1-xml \
                 php8.1-zip php8.1-gd php8.1-json -y

# Enable PHP module
sudo a2enmod php8.1

# Enable required Apache modules
sudo a2enmod rewrite
sudo a2enmod headers

# Restart Apache
sudo systemctl restart apache2
```

**Verify Installation:**
```bash
# Check Apache
apache2 -v
# Expected: Apache/2.4.52 (Ubuntu)

# Check PHP
php -v
# Expected: PHP 8.1.2

# Check MySQL
mysql --version
# Expected: mysql Ver 8.0.43
```

**macOS - MAMP or Homebrew**

```bash
# Using Homebrew
brew install php@8.1 mysql

# Start services
brew services start php@8.1
brew services start mysql
```

**Windows - XAMPP**

```
1. Download XAMPP with PHP 8.1+
2. Install to C:\xampp
3. Start Apache and MySQL from Control Panel
```

---

#### 2. Create Database ✅

**Actual Implementation:**

**Database Created:** `ai_fluency_lms`
**Database User:** `ai_fluency_user`
**Character Set:** utf8mb4_unicode_ci

**Connect to MySQL:**
```bash
# Using root or existing admin user
mysql -u vuksDev -p
```

**Create Database and User:**
```sql
-- Create database
CREATE DATABASE ai_fluency_lms
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Create user with secure password
CREATE USER 'ai_fluency_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';

-- Grant privileges
GRANT ALL PRIVILEGES ON ai_fluency_lms.* TO 'ai_fluency_user'@'localhost';
FLUSH PRIVILEGES;

-- Switch to database
USE ai_fluency_lms;
```

**Run Migrations:** ✅
```bash
# All 5 migration scripts executed successfully
cd /var/www/html/sci-bono-aifluency/api

# 1. Create users table
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/001_create_users_table.sql

# 2. Create courses, modules, lessons tables
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/002_create_courses_modules_lessons.sql

# 3. Create quizzes and questions tables
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/003_create_quizzes_questions.sql

# 4. Create enrollments and progress tables
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/004_create_enrollments_progress.sql

# 5. Create certificates and submissions tables
mysql -u ai_fluency_user -p ai_fluency_lms < migrations/005_create_certificates_submissions.sql
```

**Content Migration:** ✅
```bash
# Extract content from HTML files
cd /var/www/html/sci-bono-aifluency/scripts/migration

# 1. Extract lessons (44 lessons extracted)
php extract-chapters.php

# 2. Extract quizzes (1 quiz, 10 questions extracted)
php extract-quizzes.php

# 3. Validate extracted content
php validate-content.php

# 4. Import to database
php import-to-db.php
```

**Verify Database Content:**
```sql
-- Check tables created
SHOW TABLES;

-- Check content imported
SELECT COUNT(*) FROM courses;   -- Expected: 1
SELECT COUNT(*) FROM modules;   -- Expected: 6
SELECT COUNT(*) FROM lessons;   -- Expected: 44
SELECT COUNT(*) FROM quizzes;   -- Expected: 1
SELECT COUNT(*) FROM quiz_questions;  -- Expected: 10
SELECT COUNT(*) FROM users;     -- Expected: 1 (admin)
```

---

#### 3. Install Composer ✅

**Linux/macOS:**
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
php -r "unlink('composer-setup.php');"
```

**Windows:**
```
Download: https://getcomposer.org/Composer-Setup.exe
Run installer
Restart terminal
```

**Verify Installation:**
```bash
composer --version
# Expected: Composer version 2.x.x
```

**Current Installation:** ✅ Composer 2.x installed and operational

---

#### 4. Install PHP Dependencies ✅

**Actual `composer.json` Implementation:**
```json
{
    "name": "scibono/ai-fluency-lms",
    "description": "Sci-Bono AI Fluency Learning Management System",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "firebase/php-jwt": "^6.10",
        "vlucas/phpdotenv": "^5.6"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "App\\Controllers\\": "controllers/",
            "App\\Models\\": "models/",
            "App\\Middleware\\": "middleware/",
            "App\\Utils\\": "utils/"
        }
    }
}
```

**Dependencies Installed:** ✅
- **firebase/php-jwt** (v6.10): JWT token generation and verification
- **vlucas/phpdotenv** (v5.6): Environment variable management
- **phpunit/phpunit** (v10.5): Unit testing framework (dev)

**Install Dependencies:**
```bash
cd /var/www/html/sci-bono-aifluency/api
composer install
```

**Verify Installation:**
```bash
# Check installed packages
composer show

# Test autoloader
php -r "
require 'vendor/autoload.php';
echo class_exists('App\\Utils\\Response') ? 'Autoload working' : 'Failed';
"
```

**Future Dependencies** (to be added as needed):
- phpmailer/phpmailer: Email notifications
- intervention/image: Image processing
- mpdf/mpdf: PDF certificate generation

---

#### 5. Configure Environment ✅

**Create `/api/.env` file:** ✅

**Actual Implementation:**
```bash
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ai_fluency_lms
DB_USER=ai_fluency_user
DB_PASSWORD=<SECURE_PASSWORD_GENERATED>

# Application Settings
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

# JWT Configuration
JWT_SECRET=<SECURE_64_CHAR_SECRET_GENERATED>
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

**Generate Secure Secrets:**
```bash
# Generate JWT secret (64 characters)
openssl rand -base64 48

# Generate secure database password
openssl rand -base64 16 | tr -d "=+/" | cut -c1-20
```

**Security Configuration:** ✅
- `.env` file created with secure credentials
- File permissions set to 600 (read/write owner only)
- `.env` added to `.gitignore`
- Secrets generated using OpenSSL

**Verify Configuration:**
```bash
# Test database connection
cd /var/www/html/sci-bono-aifluency/api
php -r "
require 'config/database.php';
echo \$pdo ? 'Database connected successfully' : 'Connection failed';
"

# Test environment loading
php -r "
require 'vendor/autoload.php';
require 'config/config.php';
echo 'APP_ENV: ' . APP_ENV . PHP_EOL;
echo 'DB_NAME: ' . DB_NAME . PHP_EOL;
"
```

**Important Security Notes:**
- ✅ `.env` excluded from git commits
- ✅ Never commit secrets to version control
- ✅ Use different secrets for production
- ✅ Rotate JWT secret periodically
- ✅ Use strong passwords (min 20 characters with special chars)

---

#### 6. Configure Apache for API ✅

**Enable Required Modules:** ✅
```bash
# Enable mod_rewrite for URL rewriting
sudo a2enmod rewrite

# Enable mod_headers for CORS and security headers
sudo a2enmod headers

# Restart Apache
sudo systemctl restart apache2
```

**Verify Modules:**
```bash
# Check if mod_rewrite is enabled
apache2ctl -M | grep rewrite
# Expected: rewrite_module (shared)

# Check if mod_headers is enabled
apache2ctl -M | grep headers
# Expected: headers_module (shared)
```

**API `.htaccess` Configuration:** ✅

Created at `/api/.htaccess`:
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

    # CORS headers
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

**Test Apache Configuration:**
```bash
# Test configuration syntax
sudo apache2ctl configtest
# Expected: Syntax OK

# Test URL rewriting
php -r "
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REQUEST_URI'] = '/api/courses';
require '/var/www/html/sci-bono-aifluency/api/index.php';
"
# Should return JSON response (endpoint not found until controllers are implemented)
```

---

#### 7. Create API Structure ✅

**Actual Implementation - Directory Structure:**

```
sci-bono-aifluency/
├── api/
│   ├── index.php               ✅ API entry point (CORS, rate limiting, error handling)
│   ├── .htaccess               ✅ URL rewriting, security headers
│   ├── .env                    ✅ Environment variables (secure credentials)
│   ├── composer.json           ✅ Dependency management
│   │
│   ├── config/
│   │   ├── config.php          ✅ Central configuration (PHPDotEnv)
│   │   └── database.php        ✅ PDO connection with custom parser
│   │
│   ├── routes/
│   │   └── api.php             ✅ Route definitions (26 endpoints)
│   │
│   ├── controllers/            ⏳ Pending (Phase 2)
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── CourseController.php
│   │   ├── ModuleController.php
│   │   ├── LessonController.php
│   │   ├── QuizController.php
│   │   ├── ProgressController.php
│   │   ├── EnrollmentController.php
│   │   └── CertificateController.php
│   │
│   ├── models/                 ✅ Active Record pattern
│   │   ├── BaseModel.php       ✅ Base CRUD operations
│   │   ├── User.php            ✅ User authentication & management
│   │   ├── Course.php          ⏳ Pending
│   │   ├── Module.php          ⏳ Pending
│   │   ├── Lesson.php          ⏳ Pending
│   │   ├── Quiz.php            ⏳ Pending
│   │   ├── Enrollment.php      ⏳ Pending
│   │   └── Certificate.php     ⏳ Pending
│   │
│   ├── utils/                  ✅ Helper classes
│   │   ├── Response.php        ✅ JSON response formatting
│   │   ├── Validator.php       ✅ Input validation
│   │   └── JWTHandler.php      ✅ JWT token management
│   │
│   ├── middleware/             ⏳ Pending (Phase 2)
│   │   ├── AuthMiddleware.php
│   │   ├── RoleMiddleware.php
│   │   └── CorsMiddleware.php
│   │
│   ├── migrations/             ✅ Database migrations
│   │   ├── 001_create_users_table.sql              ✅
│   │   ├── 002_create_courses_modules_lessons.sql  ✅
│   │   ├── 003_create_quizzes_questions.sql        ✅
│   │   ├── 004_create_enrollments_progress.sql     ✅
│   │   └── 005_create_certificates_submissions.sql ✅
│   │
│   ├── logs/                   ✅ Application logs
│   ├── tests/                  ✅ Unit tests directory
│   └── vendor/                 ✅ Composer dependencies
│
├── scripts/
│   └── migration/              ✅ Content migration scripts
│       ├── extract-chapters.php      ✅ Extract 44 lessons
│       ├── extract-quizzes.php       ✅ Extract quizzes
│       ├── validate-content.php      ✅ Validate data
│       ├── import-to-db.php          ✅ Import to database
│       └── output/                   ✅ JSON output files
│
└── .gitignore                  ✅ Security exclusions
```

**Implementation Summary:**

**✅ Complete (Phase 1):**
- API entry point with routing system
- Configuration management (config, database)
- Utility classes (Response, Validator, JWTHandler)
- Base model layer (BaseModel, User)
- Route definitions (26 endpoints across 9 categories)
- Security configuration (.htaccess, .gitignore)
- Database setup (12 tables created)
- Content migration (44 lessons, 6 modules, 1 quiz)

**⏳ Pending (Phase 2+):**
- 9 Controller implementations
- 7 Additional model classes
- 3 Middleware implementations
- Unit tests
- Integration tests

**Verification:**

```bash
# Verify directory structure
cd /var/www/html/sci-bono-aifluency/api
tree -L 2 -I 'vendor'

# Verify all files exist
ls -la config/
ls -la routes/
ls -la models/
ls -la utils/
ls -la migrations/

# Test API infrastructure
php -r "
require 'vendor/autoload.php';
require 'config/config.php';
require 'config/database.php';
\$user = new App\Models\User(\$pdo);
echo 'Users in database: ' . \$user->count() . PHP_EOL;
"
```

---

## Development Tools

### Code Editor: VS Code (Recommended)

**Download:** https://code.visualstudio.com/

**Essential Extensions:**

```
1. Live Server (ritwickdey.liveserver)
   - Live reload for HTML/CSS/JS changes

2. PHP Intelephense (bmewburn.vscode-intelephense-client)
   - PHP language support, autocomplete

3. ESLint (dbaeumer.vscode-eslint)
   - JavaScript linting

4. Prettier (esbenp.prettier-vscode)
   - Code formatting

5. GitLens (eamodio.gitlens)
   - Enhanced Git integration

6. MySQL (cweijan.vscode-mysql-client2)
   - Database management

7. Thunder Client (rangav.vscode-thunder-client)
   - API testing

8. HTML CSS Support (ecmel.vscode-html-css)
   - CSS class IntelliSense
```

**Install Extensions:**
```bash
# Via command line
code --install-extension ritwickdey.liveserver
code --install-extension bmewburn.vscode-intelephense-client
code --install-extension dbaeumer.vscode-eslint
code --install-extension esbenp.prettier-vscode
```

**VS Code Settings (`.vscode/settings.json`):**
```json
{
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "files.associations": {
    "*.html": "html"
  },
  "liveServer.settings.port": 8000,
  "liveServer.settings.root": "/",
  "[php]": {
    "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
  }
}
```

---

### Browser DevTools

**Chrome DevTools (Recommended for PWA Testing)**

**Essential Panels:**

1. **Elements** - Inspect HTML/CSS
   - Shortcuts: `Ctrl+Shift+C` (Inspect element)
   - Edit styles live
   - View computed styles

2. **Console** - JavaScript debugging
   - View errors and logs
   - Test JavaScript code
   - Monitor Service Worker logs

3. **Network** - Monitor requests
   - View all HTTP requests
   - Check resource loading times
   - Simulate offline mode

4. **Application** - PWA features
   - Service Workers
   - Cache Storage
   - Local Storage
   - Manifest

5. **Lighthouse** - Performance audits
   - PWA score
   - Performance metrics
   - Accessibility checks
   - SEO analysis

**Keyboard Shortcuts:**
```
F12 or Ctrl+Shift+I     - Open DevTools
Ctrl+Shift+C            - Inspect element
Ctrl+Shift+J            - Open Console
Ctrl+Shift+M            - Toggle device toolbar (mobile view)
Ctrl+Shift+P            - Command palette
```

---

### Git Configuration

**Install Git:**
- Windows: https://git-scm.com/download/win
- macOS: `brew install git`
- Linux: `sudo apt install git`

**Configure Git:**
```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
git config --global core.editor "code --wait"  # Use VS Code as editor
```

**Clone Repository:**
```bash
# HTTPS
git clone https://github.com/yourusername/sci-bono-aifluency.git

# SSH (if configured)
git clone git@github.com:yourusername/sci-bono-aifluency.git

# Navigate to project
cd sci-bono-aifluency
```

**Branch Strategy:**
```bash
# Main branch (production)
git checkout main

# Create feature branch
git checkout -b feature/new-chapter

# Make changes, commit
git add .
git commit -m "Add chapter 15: Neural Networks"

# Push to remote
git push origin feature/new-chapter
```

---

### Database Tools

**MySQL Workbench** (GUI Client)
- Download: https://dev.mysql.com/downloads/workbench/
- Visual database design
- Query builder
- Data import/export

**phpMyAdmin** (Web-based)
- Comes with XAMPP/MAMP
- Access: http://localhost/phpmyadmin
- Database management in browser

**DBeaver** (Multi-database)
- Download: https://dbeaver.io/
- Supports MySQL, PostgreSQL, SQLite, etc.
- Free and open source

**Command Line:**
```bash
# Connect to MySQL
mysql -u root -p

# Show databases
SHOW DATABASES;

# Use database
USE scibono_ai_fluency;

# Show tables
SHOW TABLES;

# Describe table structure
DESCRIBE users;

# Run query
SELECT * FROM users LIMIT 10;
```

---

## Testing Environment

### Local Testing

**1. Development Server**
```bash
# Start local server
python3 -m http.server 8000

# Access site
http://localhost:8000
```

**2. Test Checklist**

**Frontend Testing:**
- [ ] Landing page loads correctly
- [ ] All CSS styles apply
- [ ] JavaScript functions work
- [ ] Navigation buttons functional
- [ ] Mobile menu works
- [ ] Chapter tabs switch correctly
- [ ] Quiz submissions work
- [ ] PDF download works (if enabled)

**PWA Testing:**
- [ ] Service Worker registers
- [ ] Resources cache on first visit
- [ ] Site works offline
- [ ] Offline page shows for uncached pages
- [ ] Install prompt appears
- [ ] App installs successfully
- [ ] Installed app opens in standalone mode

**Responsive Testing:**
- [ ] Mobile (320px - 767px)
- [ ] Tablet (768px - 1023px)
- [ ] Desktop (1024px+)
- [ ] Test in Chrome mobile view (Ctrl+Shift+M)

---

### Browser Testing Matrix

| Browser | Version | Priority | Notes |
|---------|---------|----------|-------|
| Chrome | Latest | **High** | Primary development browser |
| Firefox | Latest | **High** | Test PWA compatibility |
| Safari (macOS) | Latest | **Medium** | macOS users |
| Safari (iOS) | Latest | **High** | iPhone/iPad testing |
| Edge | Latest | **Medium** | Windows default browser |
| Samsung Internet | Latest | **Low** | Android Samsung devices |

**Testing Tools:**
- **BrowserStack** - Test on real devices (paid)
- **LambdaTest** - Cross-browser testing (paid/free tier)
- **Chrome Device Mode** - Simulate mobile devices (free)

---

### Automated Testing (Future)

**Unit Testing (PHP - PHPUnit):**
```bash
# Install PHPUnit
composer require --dev phpunit/phpunit

# Run tests
./vendor/bin/phpunit tests/
```

**Example Test:**
```php
<?php
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation()
    {
        $user = new User('John Doe', 'john@example.com');
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
    }
}
```

**JavaScript Testing (Jest):**
```bash
# Install Jest
npm install --save-dev jest

# Run tests
npm test
```

**Example Test:**
```javascript
// quiz.test.js
test('calculateScore returns correct score', () => {
  const answers = [0, 1, 2];
  const quizData = [
    { correctAnswer: 0 },
    { correctAnswer: 1 },
    { correctAnswer: 3 }
  ];
  const score = calculateScore(answers, quizData);
  expect(score).toBe(2); // 2 out of 3 correct
});
```

---

### Performance Testing

**Lighthouse Audit:**
```
1. Open Chrome DevTools (F12)
2. Go to "Lighthouse" tab
3. Select categories:
   - Performance
   - Accessibility
   - Best Practices
   - SEO
   - PWA
4. Click "Analyze page load"
5. Review scores and recommendations
```

**Target Scores:**
- Performance: 90+
- Accessibility: 90+
- Best Practices: 90+
- SEO: 90+
- PWA: 100

**PageSpeed Insights:**
```
URL: https://pagespeed.web.dev/
Enter: Your deployed site URL
Review: Mobile and Desktop scores
```

---

## Troubleshooting

### Common Issues

#### Issue 1: Service Worker Not Registering

**Symptom:**
```
Console error: "Failed to register service worker: SecurityError"
```

**Cause:** Not using HTTPS (or localhost)

**Solution:**
```
Option 1: Use localhost
  http://localhost:8000 ✅

Option 2: Use 127.0.0.1
  http://127.0.0.1:8000 ✅

Option 3: Enable HTTPS locally
  Use mkcert to generate local SSL certificate
```

---

#### Issue 2: CSS Not Loading

**Symptom:** Page loads but no styling

**Cause:** Incorrect CSS path

**Solution:**
```html
<!-- Check HTML -->
<link rel="stylesheet" href="/css/styles.css">

<!-- Verify file exists -->
ls css/styles.css

<!-- Check browser Network tab -->
DevTools → Network → styles.css should be 200 OK
```

---

#### Issue 3: JavaScript Errors

**Symptom:**
```
Console error: "Uncaught ReferenceError: $ is not defined"
```

**Cause:** jQuery not loaded (but we don't use jQuery)

**Solution:**
- Verify all `<script>` tags load correctly
- Check CDN availability (Font Awesome, jsPDF, html2canvas)
- Clear browser cache

---

#### Issue 4: Port Already in Use

**Symptom:**
```
Error: Address already in use (port 8000)
```

**Solution:**
```bash
# Find process using port
# Linux/macOS:
lsof -i :8000

# Windows:
netstat -ano | findstr :8000

# Kill process (Linux/macOS)
kill -9 <PID>

# Kill process (Windows)
taskkill /PID <PID> /F

# Or use different port
python3 -m http.server 8001
```

---

#### Issue 5: MySQL Connection Failed

**Symptom:**
```
Error: SQLSTATE[HY000] [2002] Connection refused
```

**Solution:**
```bash
# Check if MySQL is running
sudo systemctl status mysql

# Start MySQL
sudo systemctl start mysql

# Check credentials in .env
DB_HOST=localhost  # Not 127.0.0.1
DB_PORT=3306
DB_USER=scibono_dev
DB_PASS=dev_password_123
```

---

#### Issue 6: Permission Denied

**Symptom:**
```
403 Forbidden
You don't have permission to access / on this server
```

**Solution:**
```bash
# Fix file permissions (Linux)
sudo chown -R www-data:www-data /var/www/html/sci-bono-aifluency
sudo chmod -R 755 /var/www/html/sci-bono-aifluency

# Or give user ownership
sudo chown -R $USER:$USER /var/www/html/sci-bono-aifluency
```

---

#### Issue 7: Offline Mode Not Working

**Symptom:** Page doesn't load when offline

**Checklist:**
```
✅ Service Worker registered?
   DevTools → Application → Service Workers

✅ Resources cached?
   DevTools → Application → Cache Storage

✅ offline.html in cache?
   Check urlsToCache in service-worker.js

✅ Fetch handler catches errors?
   Check catch block in fetch event
```

---

## Best Practices

### Development Workflow

**1. Before Making Changes**
```bash
# Pull latest changes
git pull origin main

# Create feature branch
git checkout -b feature/your-feature

# Start local server
python3 -m http.server 8000
```

**2. While Developing**
```
- Keep browser DevTools open
- Check Console for errors
- Test in multiple browsers
- Test responsive breakpoints
- Commit frequently with clear messages
```

**3. Before Committing**
```
- Test all functionality
- Check for console errors
- Validate HTML (https://validator.w3.org/)
- Run Lighthouse audit
- Update CHANGELOG.md
```

**4. Commit and Push**
```bash
git add .
git commit -m "Add feature: Description of changes"
git push origin feature/your-feature
```

---

### Code Quality

**HTML:**
```html
✅ Use semantic HTML5 tags
✅ Include alt text for images
✅ Use proper heading hierarchy (h1 → h2 → h3)
✅ Validate with W3C Validator
❌ Don't use deprecated tags
```

**CSS:**
```css
✅ Use CSS variables for theming
✅ Mobile-first approach
✅ Consistent naming (BEM-like)
✅ Comment complex selectors
❌ Don't use !important (unless necessary)
```

**JavaScript:**
```javascript
✅ Use const/let (not var)
✅ Use arrow functions
✅ Add error handling (try-catch)
✅ Comment complex logic
❌ Don't pollute global scope
```

---

### Performance Optimization

**1. Images**
```
- Use WebP format where possible
- Compress images (TinyPNG, ImageOptim)
- Use appropriate dimensions
- Lazy load images below fold
```

**2. CSS**
```
- Minimize CSS files
- Remove unused styles
- Use CSS variables
- Avoid deep nesting (max 3 levels)
```

**3. JavaScript**
```
- Minimize JS files
- Load non-critical JS asynchronously
- Use event delegation
- Debounce scroll/resize events
```

**4. Caching**
```
- Pre-cache critical resources only
- Use runtime caching for others
- Increment cache version on updates
- Clean up old caches
```

---

### Security Practices

**1. Never Commit Secrets**
```bash
# Add to .gitignore
.env
config/database.php
*.key
*.pem
```

**2. Validate All Inputs**
```php
// Sanitize user input
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

// Validate
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email');
}
```

**3. Use Prepared Statements**
```php
// GOOD: Prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// BAD: Direct concatenation (SQL injection risk)
$query = "SELECT * FROM users WHERE email = '$email'";
```

**4. Hash Passwords**
```php
// Hash password
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verify password
if (password_verify($input, $hash)) {
    // Password correct
}
```

---

### Documentation

**1. Code Comments**
```javascript
/**
 * Calculates quiz score based on user answers
 * @param {Array} userAnswers - Array of user's selected options
 * @param {Array} quizData - Array of quiz questions with correct answers
 * @returns {number} Total score (number of correct answers)
 */
function calculateScore(userAnswers, quizData) {
    // Implementation
}
```

**2. README Updates**
```markdown
- Keep README.md up to date
- Document new features
- Update installation instructions
- Add troubleshooting for new issues
```

**3. Changelog**
```markdown
## [1.1.0] - 2025-10-27
### Added
- New chapter 15: Neural Networks
- Quiz for module 7

### Changed
- Updated service worker cache version to v2

### Fixed
- Mobile menu not closing on backdrop click
```

---

## Related Documents

### Technical Documentation
- [Current Architecture](../01-Architecture/current-architecture.md) - System overview
- [Future Architecture](../01-Architecture/future-architecture.md) - Planned LMS features
- [Database Schema](../03-Database/schema-design.md) - Database structure
- [JavaScript API](../02-Code-Reference/javascript-api.md) - Frontend code reference
- [Service Worker Guide](../02-Code-Reference/service-worker.md) - PWA implementation

### Development Guides
- [Coding Standards](coding-standards.md) (coming soon) - Code style guide
- [Testing Procedures](testing-procedures.md) (coming soon) - QA guide
- [Deployment Guide](../../04-Deployment/deployment-checklist.md) (coming soon)

### External Resources
- [MDN Web Docs](https://developer.mozilla.org/) - Web development reference
- [PHP Documentation](https://www.php.net/docs.php) - PHP manual
- [MySQL Documentation](https://dev.mysql.com/doc/) - MySQL reference
- [VS Code Docs](https://code.visualstudio.com/docs) - Editor documentation

---

## Document Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2025-10-27 | 1.0 | Dev Team | Initial development setup guide |

---

**END OF DOCUMENT**

*This setup guide provides everything needed to start developing for the Sci-Bono AI Fluency platform. Follow these instructions to set up your local environment and begin contributing.*
