# Architecture Decision: Hybrid MVC + REST API Structure

**Decision Date**: November 11, 2025
**Status**: ✅ APPROVED
**Decision Maker**: Project Team

---

## Decision

We will use a **hybrid architecture** combining MVC principles with a REST API structure, keeping the `/api` directory for backend application logic instead of renaming it to `/app`.

---

## Context

The original MVC Transformation Plan specified a traditional MVC directory structure with `/app` as the root application directory. However, during Phase 1 implementation, we established a `/api` directory that houses all backend logic (controllers, models, routes, middleware, config, utils).

After frontend-backend integration was completed with authentication working at 100% test pass rate, we evaluated whether to:
1. Refactor `/api` → `/app` to match the original plan exactly
2. Keep `/api` and align the architecture documentation

---

## Final Architecture Structure

```
/var/www/html/sci-bono-aifluency/
│
├── /api/                          ← Backend Application (MVC)
│   ├── /controllers/              ← Request handlers
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── ModuleController.php
│   │   └── ...
│   │
│   ├── /models/                   ← Data models (Active Record pattern)
│   │   ├── BaseModel.php
│   │   ├── User.php
│   │   ├── Course.php
│   │   ├── Module.php
│   │   └── ...
│   │
│   ├── /views/                    ← Server-rendered templates (if needed)
│   │   ├── /emails/               ← Email templates
│   │   ├── /pdf/                  ← PDF templates
│   │   └── /reports/              ← Report templates
│   │
│   ├── /routes/                   ← API routing definitions
│   │   └── api.php
│   │
│   ├── /middleware/               ← Request middleware
│   │   ├── AuthMiddleware.php
│   │   └── CorsMiddleware.php
│   │
│   ├── /config/                   ← Configuration files
│   │   ├── database.php
│   │   └── constants.php
│   │
│   ├── /utils/                    ← Utility classes
│   │   ├── JWTHandler.php
│   │   └── Validator.php
│   │
│   ├── /migrations/               ← Database migrations
│   │   ├── 001_create_users.sql
│   │   ├── 002_create_courses.sql
│   │   └── ...
│   │
│   ├── /tests/                    ← Backend API tests
│   │   ├── test_auth.php
│   │   ├── test_users.php
│   │   └── run_all_tests.sh
│   │
│   ├── /logs/                     ← Application logs
│   │   └── app.log
│   │
│   ├── /vendor/                   ← Composer dependencies
│   │
│   ├── index.php                  ← API front controller
│   ├── .htaccess                  ← API routing rules
│   ├── composer.json              ← PHP dependencies
│   └── .env                       ← Environment config
│
├── /css/                          ← Public stylesheets
│   ├── styles.css
│   └── stylesModules.css
│
├── /js/                           ← Public JavaScript
│   ├── storage.js                 ← LocalStorage abstraction
│   ├── api.js                     ← API wrapper
│   ├── auth.js                    ← Authentication module
│   ├── header-template.js         ← Dynamic header
│   └── script.js                  ← Legacy scripts
│
├── /images/                       ← Public images
│   └── favicon.ico
│
├── /assets/                       ← Additional public assets
│
├── /scripts/                      ← Build/deployment scripts
│
├── /Documentation/                ← Project documentation
│   ├── MVC_TRANSFORMATION_PLAN.md
│   ├── ARCHITECTURE_DECISION.md
│   ├── PHASE1_SUMMARY.md
│   ├── PHASE1_TESTING.md
│   └── ...
│
├── *.html                         ← Frontend views (PWA pages)
│   ├── index.html
│   ├── login.html
│   ├── signup.html
│   ├── module1.html
│   ├── chapter1.html
│   └── ...
│
├── service-worker.js              ← PWA service worker
├── manifest.json                  ← PWA manifest
└── .htaccess                      ← Root routing rules
```

---

## Architecture Type: Hybrid MVC + REST API

### Backend (MVC)
- **Model**: Active Record pattern in `/api/models/`
- **View**: JSON responses for API, optional templates in `/api/views/`
- **Controller**: Request handlers in `/api/controllers/`
- **Router**: Centralized routing in `/api/routes/api.php`

### Frontend (PWA)
- **HTML Pages**: Static/dynamic HTML in project root
- **JavaScript**: Modular vanilla JS in `/js/`
- **CSS**: Design system in `/css/`
- **Service Worker**: Offline-first PWA capabilities

### Integration Layer
- **API Wrapper** (`/js/api.js`): Frontend communicates with backend via REST
- **Authentication** (`/js/auth.js`): JWT token management
- **Storage** (`/js/storage.js`): LocalStorage abstraction

---

## URL Structure

### API Endpoints (Backend)
```
/api/auth/register          POST    User registration
/api/auth/login             POST    User login
/api/auth/logout            POST    User logout
/api/auth/refresh           POST    Token refresh
/api/auth/me                GET     Current user

/api/users                  GET     List users
/api/users/:id              GET     Show user
/api/users/:id              PUT     Update user
/api/users/:id              DELETE  Delete user

/api/courses                GET     List courses
/api/courses/:id            GET     Show course
/api/modules/:id            GET     Show module
/api/lessons/:id            GET     Show lesson
/api/quizzes/:id            GET     Show quiz
/api/quizzes/:id/submit     POST    Submit quiz attempt
...
```

### Frontend Pages (PWA)
```
/index.html                 Home page
/login.html                 Login page
/signup.html                Registration page
/student-dashboard.html     Student dashboard
/instructor-dashboard.html  Instructor dashboard
/admin-dashboard.html       Admin dashboard
/profile.html               User profile
/module1.html               Module page
/chapter1.html              Chapter content
...
```

---

## Rationale for `/api` vs `/app`

### Why We Chose `/api`:

1. **RESTful Convention**
   - `/api` prefix is standard for REST APIs
   - Clear URL structure: `/api/resource/action`
   - Industry best practice for API-first architectures

2. **Frontend Already Integrated**
   - Phase 1 authentication built with `/api` base URL
   - All 100% backend tests passing with `/api` structure
   - No breaking changes needed

3. **Clear Separation of Concerns**
   - `/api/` = Backend application logic
   - `/css/`, `/js/`, `/images/` = Frontend public assets
   - `*.html` = Frontend views
   - Easier to understand for developers

4. **Future-Proof Architecture**
   - Supports SPA (Single Page Application) migration
   - Enables mobile app integration (same API)
   - Allows API versioning: `/api/v1/`, `/api/v2/`
   - Can add GraphQL endpoint: `/graphql`

5. **Modern Hybrid Pattern**
   - Backend: MVC with JSON responses
   - Frontend: PWA with JavaScript modules
   - Integration: REST API communication
   - Standard pattern for progressive web apps

6. **Less Refactoring**
   - Avoid updating all route definitions
   - Avoid changing frontend API base URL
   - Avoid modifying .htaccess rules
   - Focus on feature development

---

## MVC Principles Maintained

Despite using `/api` instead of `/app`, we still follow MVC principles:

✅ **Model** - Data models in `/api/models/`
✅ **View** - JSON responses (REST API view layer)
✅ **Controller** - Request handlers in `/api/controllers/`
✅ **Routing** - Centralized routes in `/api/routes/`
✅ **Middleware** - Request filtering in `/api/middleware/`
✅ **Configuration** - Environment config in `/api/config/`
✅ **Separation** - Business logic separate from presentation

---

## Trade-offs

### What We Gain:
- ✅ RESTful API structure
- ✅ Clear backend/frontend separation
- ✅ API versioning capability
- ✅ Mobile-ready architecture
- ✅ No breaking changes to existing code

### What We Lose:
- ❌ Not strictly following traditional MVC naming
- ❌ Deviates from original transformation plan
- ❌ May confuse developers expecting `/app` directory

### Mitigation:
- ✅ Update MVC_TRANSFORMATION_PLAN.md to reflect `/api`
- ✅ Document architecture decision (this file)
- ✅ Create clear README for new developers
- ✅ Maintain MVC principles in code organization

---

## When to Use `/api/views/`

The `/api/views/` directory should be created for server-rendered content:

### Use Cases:
1. **Email Templates** - HTML emails (password reset, welcome, notifications)
2. **PDF Templates** - Certificate generation, reports, transcripts
3. **Report Generation** - Server-rendered analytics reports
4. **Admin Exports** - CSV/Excel generation templates
5. **Server-Side Rendering** - If SSR is needed for specific pages

### Not Used For:
- Frontend HTML pages (these stay in project root)
- Client-side templates (these go in `/js/`)
- Static content (these go in `/css/`, `/js/`, `/images/`)

---

## Comparison to Original MVC Plan

### Original Plan:
```
/app/controllers/
/app/models/
/app/views/
/public/index.php
```

### Our Implementation:
```
/api/controllers/     (was /app/controllers/)
/api/models/          (was /app/models/)
/api/views/           (was /app/views/)
/api/index.php        (was /public/index.php)
/*.html               (frontend views)
```

### Functional Equivalence:
- **Controllers**: Same functionality, different path
- **Models**: Same functionality, different path
- **Views**: JSON API responses + optional templates
- **Front Controller**: `/api/index.php` handles all API routes
- **Public Assets**: `/css/`, `/js/`, `/images/` are publicly accessible

---

## API Versioning Strategy (Future)

Our structure supports API versioning:

```
/api/
  /v1/
    /controllers/
    /routes/
    index.php
  /v2/
    /controllers/
    /routes/
    index.php
  index.php (routes to latest version)
```

Currently we're on implicit v1, but structure allows easy v2 addition.

---

## Database Layer

Database access remains in `/api/`:

```
/api/config/database.php       ← PDO connection
/api/models/BaseModel.php      ← Base model with CRUD
/api/models/User.php           ← User model extends BaseModel
/api/migrations/               ← Database migrations
```

---

## Testing Strategy

```
/api/tests/                    ← Backend API tests
  test_auth.php                ← Auth endpoint tests
  test_users.php               ← User endpoint tests
  test_modules.php             ← Module endpoint tests
  run_all_tests.sh             ← Test runner

/PHASE1_TESTING.md             ← Frontend integration tests
/PHASE1_SUMMARY.md             ← Implementation documentation
```

---

## Security Considerations

The `/api` structure maintains security:

- ✅ `.htaccess` protects sensitive files (`.env`, `/config/`)
- ✅ All requests routed through `index.php` front controller
- ✅ Middleware filters requests before controllers
- ✅ Models use prepared statements (SQL injection prevention)
- ✅ CORS middleware controls cross-origin requests
- ✅ JWT authentication on protected routes

---

## Developer Onboarding

### For Backend Developers:
- API code lives in `/api/`
- Follow MVC pattern: Models → Controllers → Routes
- Add endpoints in `/api/routes/api.php`
- Use `BaseModel` for database operations
- Run tests: `bash /api/tests/run_all_tests.sh`

### For Frontend Developers:
- Static assets in `/css/`, `/js/`, `/images/`
- HTML pages in project root
- Use `/js/api.js` to call backend
- Authentication via `/js/auth.js`
- Service worker handles offline caching

---

## Conclusion

The `/api` directory structure represents a **modern hybrid architecture** that combines MVC backend principles with RESTful API design and PWA frontend capabilities. This decision prioritizes:

1. **Pragmatism** - Keep what works (100% test pass rate)
2. **Standards** - Follow REST API conventions
3. **Future-Proofing** - Enable mobile and SPA support
4. **Clarity** - Clear separation of backend and frontend
5. **Maintainability** - Easy to understand and extend

While it deviates from the traditional `/app` naming in the original MVC plan, it maintains all MVC principles and provides a more flexible, modern architecture suitable for progressive web applications.

---

**Approved By**: Project Team
**Date**: November 11, 2025
**Status**: ✅ ACTIVE ARCHITECTURE
