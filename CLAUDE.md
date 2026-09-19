# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Sci-Bono AI Fluency LMS** — hybrid platform combining a static PWA frontend (HTML/CSS/vanilla JS) with a PHP 8.1+/MySQL backend exposing a REST API. Deployed to cPanel shared hosting; also runs on any LAMP stack. No frontend build step.

A separate, more detailed doc-oriented CLAUDE.md exists at `Documentation/CLAUDE.md` — that file drives the documentation-heavy workflow used for content authoring. This root file is the primary architectural reference for code changes.

## Commands

### Backend (from repo root unless noted)
```bash
# Install PHP dependencies
cd api && composer install

# Run full backend test suite (bash-driven, not PHPUnit)
cd api/tests && bash run_all_tests.sh

# Run a single test
php api/tests/test_login.php

# Apply all SQL migrations (interactive, prompts for password)
cd api/migrations && bash run-all-migrations.sh <db_name> <user>

# Apply one migration
mysql -u root -p ai_fluency_lms < api/migrations/030_create_notifications_table.sql
```

### Frontend / local dev
```bash
# PHP built-in server (serves both static HTML and PHP API)
php -S localhost:8000

# Static-only preview (API will 404)
python3 -m http.server 8000
```

The API only works under a server that executes PHP. Apache/Nginx with mod_rewrite is required for the pretty `/api/*` URLs — see `api/.htaccess`.

## Architecture

### Two-tier hybrid, one repo
- **Static frontend**: HTML pages at the repo root (`index.html`, `about.html`, module/chapter pages), plus role-scoped subdirectories: `admin/`, `instructor/`, `student/`, `profile/`, `public/`. Shared assets in `css/`, `js/`, `images/`.
- **Backend**: everything under `api/`. Front controller is `api/index.php`; all requests to `/api/*` are rewritten to it.
- **PWA layer**: `service-worker.js` + `manifest.json` at the root cache the static shell.

### Backend request flow (critical to understand before adding endpoints)
1. Apache rewrites `/api/<anything>` → `api/index.php`.
2. `api/index.php` loads Composer autoload, `config/config.php` (env vars via phpdotenv), then `config/database.php` (creates global `$pdo`), applies CORS + JSON body parsing + optional rate limiting.
3. It then `require`s `api/routes/api.php`. That file is **not** a router library — it's a single large `$routes` array of `[method, pattern, handler, auth, roles]` entries, followed by an inline matcher that regex-converts `:param` placeholders, checks auth/role, instantiates `App\Controllers\<Name>` with `$pdo`, and invokes the method with the extracted `$params` array.
4. **All new endpoints must be added to that `$routes` array** — there is no auto-discovery. Route order matters: more specific patterns must come before generic `:id` patterns (e.g. `/certificates/my-certificates` is listed before `/certificates/:id`).

### Controllers extend `BaseController`
`api/controllers/BaseController.php` centralizes auth/RBAC. Prefer these helpers over duplicating logic:
- `getCurrentUser()` — returns the full user row (not just the JWT payload), so `primary_organization_id` / `primary_school_id` are available. Cached per request. Also enforces the token blacklist.
- `requireRole($rolesOrRole)` — 403s if the user isn't in the allowed list.
- `requireOwnershipOrRole($resourceUserId, $allowedRoles)` — students-only-see-their-own with admin bypass.
- `executeWithErrorHandling($callback, $msg)` — wraps DB ops; logs to `api/logs/`, returns a generic 500.
- Hierarchical RBAC helpers: `getManagedOrganizationIds()`, `getManagedSchoolIds()`, `requireOrganizationManagementPermission()`, `canAssignRole()` — used by org/school-scoped controllers.

### Models extend `BaseModel` (Active Record)
`api/models/BaseModel.php` provides `find`, `findBy`, `all`, `create`, `update`, `delete`, `count`, `exists`, `query`, `execute`, transactions. Subclasses set `$table`, `$primaryKey`, `$fillable`, `$hidden`.

Important quirks worth knowing:
- `create()`/`update()` wrap column names in backticks so **MySQL reserved words as column names work** (e.g. `order`, `key`). Follow this convention if adding raw SQL.
- `all()`'s `$orderBy` string is regex-escaped for reserved words (`order|index|key|group`) but otherwise interpolated raw — never pass user input into `$orderBy`.
- Only whitelisted `$fillable` keys are written; extra keys are silently dropped in `create`/`update`.

### Role hierarchy (Phase 12)
Roles from highest to lowest privilege: `superadmin` > `orgadmin` > `schooladmin` > `teacher` > `student`. Legacy code may still reference `admin`/`instructor`; new work should use the hierarchical names. `canAssignRole()` enforces that a user can only assign roles below their own level.

### Auth
- JWTs via `firebase/php-jwt`. Access token expiry `JWT_EXPIRY` (default 1h), refresh `JWT_REFRESH_EXPIRY` (default 30d).
- Logout adds the token to the `token_blacklist` table; `BaseController::getCurrentUser()` checks this on every authenticated request, so logout is effectively immediate.
- Frontend sends `Authorization: Bearer <token>` — see `js/api.js` and `js/auth.js`.

### Configuration
- `api/.env` (never committed) supplies `DB_*`, `JWT_SECRET`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `MAIL_*`. Loaded by `Dotenv\Dotenv::createImmutable` in `api/config/config.php`, which then `define()`s constants.
- `CORS_ALLOWED_ORIGINS` is a hardcoded array in `config.php` — add new frontend origins there, not in `.htaccess`.
- `UPLOAD_PATH` points at repo-root `/uploads/` (outside `api/`). File-serving relies on this.

### Migrations
- Numbered SQL files in `api/migrations/NNN_description.sql`, applied in filename order.
- Some migrations have companion `NNN_rollback.sql` and PHP runner scripts (`run_migration_NNN.php`) for complex data transformations.
- `000_full_schema.sql` is a consolidated snapshot for fresh installs; individual `001+` files are the historical sequence.
- Production deploys import via phpMyAdmin using pre-built files under `database/production/`.

### Frontend conventions
- **No frameworks, no build step.** Vanilla HTML/CSS/ES6+. External libs are CDN-only (GSAP, Font Awesome, jsPDF, html2canvas, Quill). Do not introduce npm/webpack/Vite/React/Tailwind — this is an explicit architectural constraint documented in `Documentation/CLAUDE.md`.
- All HTTP to the API must use native `fetch()` (see `js/api.js`) — no axios.
- Role-scoped dashboards live in `admin/`, `instructor/`, `student/`, `profile/` subdirectories. Public/unauthenticated pages are in `public/` (login, signup, offline, 403).
- Service worker caching: after adding a new static file that must work offline, bump `CACHE_NAME` in `service-worker.js` and add the path to `urlsToCache`. Skipping the cache-name bump means clients keep the stale cache.

## Deployment

Production target is a cPanel subdomain. `.deployignore` lists what to strip before uploading — notably `Documentation/`, `scripts/`, `api/tests/`, `api/scripts/`, `database/production/`, `.md` docs, migration runner PHPs, and log files. The root `.htaccess` force-redirects HTTP → HTTPS (except localhost), sets security headers, and blocks direct access to `.env`, `.git`, `Documentation/`, `scripts/`. See `DEPLOYMENT_GUIDE.md` for the full checklist.

## Gotchas

- **Route ordering in `api/routes/api.php`**: static segments must precede `:id` patterns matching the same prefix, otherwise the wildcard route wins. When adding endpoints, scan the file for the resource group and insert near siblings.
- **`api/.env` is required at runtime** — `Dotenv::createImmutable` throws if missing. Even test scripts load the full config stack.
- **`_POST` is repopulated from JSON body** in `api/index.php` for POST/PUT/DELETE/PATCH with `Content-Type: application/json` — controllers can just read `$_POST['field']` regardless of body format.
- **Two CLAUDE.md files**: this root file for code work; `Documentation/CLAUDE.md` for content authoring and the doc-first workflow. Don't sync them — they serve different audiences.
- **PSR-4 map spans multiple dirs**: `composer.json` autoloads `App\Controllers\` from `controllers/`, `App\Models\` from `models/`, etc. — not the conventional single `src/` root. Rerun `composer dump-autoload` after adding new subdirectories under `api/`.
