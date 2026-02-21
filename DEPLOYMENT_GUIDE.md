# Sci-Bono AI Discovery Hub - Production Deployment Guide

**Target**: `https://sci-bono-ai-discovery-hub.vuyanimagibisela.co.za/`
**Hosting**: cPanel shared hosting (PHP 8.3.28, MySQL)
**Date**: 2026-02-17

---

## Pre-Flight Checklist

### 1. Update Database Password
Edit `api/.env` and replace `[PLACEHOLDER_PASSWORD]` with the actual MySQL password created in cPanel:
```
DB_PASS=your_actual_cpanel_mysql_password
```

### 2. Verify JWT Secret
A new JWT secret has been generated in `api/.env`. If you need a fresh one:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

## Database Setup (via phpMyAdmin)

Import SQL scripts **in order** via phpMyAdmin in cPanel:

1. **`database/production/01_schema.sql`** — Creates all tables, views, triggers
2. **`database/production/02_course_content.sql`** — Course, module, lesson, quiz content + achievement definitions + certificate templates
3. **`database/production/03_reference_data.sql`** — Organizations and schools data
4. **`database/production/04_admin_user.sql`** — Creates superadmin account

**Important**: The schema file contains `CREATE DATABASE` and `USE` statements referencing `ai_fluency_lms`. In phpMyAdmin, either:
- Select the target database `vuyanjcb_scibono_ai_discoveryHub` first, OR
- Edit the SQL to replace `ai_fluency_lms` with `vuyanjcb_scibono_ai_discoveryHub`

---

## File Upload

### Using cPanel File Manager or FTP

1. Upload **all files** from the project root to the subdomain's document root
2. **Exclude** files/directories listed in `.deployignore`:
   - `.git/`, `.claude/`, `.vscode/`, `Documentation/`, `backups/`, `scripts/`
   - `test_api.html`, `test-api.html`, `redesignIndex.html`
   - `CLAUDE.md`, `README.md`, `PHASE*.md`, `*_COMPLETE.md`
   - `database/production/` (SQL files — already imported via phpMyAdmin)
   - `api/migrations/run_migration_*.php`, `api/migrations/create_backup.php`
3. **Do upload** empty `api/logs/` directory (create it if needed)
4. **Do upload** empty `uploads/` directory

### Key Files That Must Be Present
- `.htaccess` (root) — HTTPS redirect, security headers, caching
- `api/.htaccess` — API routing and CORS
- `api/.env` — Database credentials (with real password)
- `admin/.htaccess` — Admin cache settings
- `service-worker.js` — PWA functionality
- `manifest.json` — PWA manifest

---

## Post-Upload Permissions

Set via cPanel File Manager or SSH:

| Path | Permission | Purpose |
|------|-----------|---------|
| `api/.env` | 600 | Protect credentials |
| `uploads/` | 755 | File uploads |
| `api/logs/` | 755 | Application logs |
| All `.php` files | 644 | Standard PHP files |
| All directories | 755 | Standard directories |

---

## Verification Checklist

### 1. HTTPS Redirect
- Visit `http://sci-bono-ai-discovery-hub.vuyanimagibisela.co.za/`
- Should redirect to `https://` version

### 2. Landing Page
- Visit `https://sci-bono-ai-discovery-hub.vuyanimagibisela.co.za/`
- Should load the landing page (index.html)

### 3. API Health
```bash
curl https://sci-bono-ai-discovery-hub.vuyanimagibisela.co.za/api/courses
```
Should return JSON with course data.

### 4. Security Check
```bash
# .env should be blocked (403)
curl -I https://sci-bono-ai-discovery-hub.vuyanimagibisela.co.za/api/.env

# Config should be blocked (403)
curl -I https://sci-bono-ai-discovery-hub.vuyanimagibisela.co.za/api/config/config.php
```

### 5. Admin Login
- Visit `https://sci-bono-ai-discovery-hub.vuyanimagibisela.co.za/public/login.html`
- Login with: `admin@vuyanimagibisela.co.za` / `Vu13#k*s3D`
- Should redirect to admin dashboard

### 6. PWA
- Check browser DevTools > Application > Service Workers — should be registered
- Check manifest loads: DevTools > Application > Manifest

### 7. CORS Headers
- Check browser console for CORS errors when making API calls
- If issues, verify `api/.htaccess` and `api/config/config.php` CORS settings match the domain

### 8. Student Signup Flow
- Test student registration at `/public/signup.html`
- Verify email, password validation
- Check enrollment in a course

---

## Troubleshooting

### API returns 500 errors
1. Check `api/logs/php_errors.log` and `api/logs/error.log`
2. Verify database credentials in `api/.env`
3. Verify PHP version is 8.0+ (`php -v` in cPanel terminal)
4. Check that `composer install` was run (vendor directory exists)

### CORS errors in browser
1. Verify the domain in `api/config/config.php` CORS_ALLOWED_ORIGINS matches exactly
2. Check `api/.htaccess` has the correct Access-Control-Allow-Origin header
3. Ensure no trailing slashes in origin URLs

### .htaccess not working
1. Verify `mod_rewrite` is enabled (standard on cPanel)
2. Check `AllowOverride All` is set (standard on cPanel)
3. Test with a simple redirect rule first

### Service Worker issues
1. Service workers require HTTPS (which we have)
2. Clear browser cache and unregister old service workers
3. Check DevTools > Application > Service Workers for errors

### Login not working
1. Verify users table has the admin record: `SELECT * FROM users WHERE email = 'admin@vuyanimagibisela.co.za';`
2. Check JWT_SECRET in `.env` matches what was set
3. Verify `token_blacklist` table exists

---

## Post-Deployment Tasks

1. **Change admin password** via the profile page after first login
2. **Set up email** in `.env` if SMTP is available on the hosting
3. **Monitor logs** at `api/logs/` for the first few days
4. **Test all student flows**: signup, enrollment, lessons, quizzes, achievements
5. **Update DNS** if the subdomain isn't yet pointing to the hosting

---

## Rollback

If issues arise:
1. The development environment at `http://aifluency.local` remains unchanged
2. All changes are tracked in git — revert with `git checkout -- .`
3. Database can be dropped and recreated from the SQL scripts
