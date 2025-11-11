# Sci-Bono AI Fluency - Backend API

**Version**: 1.0.0
**Architecture**: Hybrid MVC + REST API
**PHP Version**: 8.0+
**Database**: MySQL 8.0 / MariaDB

---

## Overview

This directory contains the complete backend API for the Sci-Bono AI Fluency LMS platform. The API follows MVC (Model-View-Controller) principles while exposing RESTful endpoints for frontend consumption.

**Architecture Pattern**: Hybrid MVC + REST API
**Documentation**: See `/Documentation/ARCHITECTURE_DECISION.md` for rationale

---

## Directory Structure

```
/api/
├── /controllers/          Request handlers (business logic)
├── /models/              Data models (Active Record pattern)
├── /views/               Server-rendered templates (emails, PDFs, reports)
├── /routes/              API route definitions
├── /middleware/          Request filtering (auth, CORS)
├── /config/              Configuration files
├── /utils/               Utility classes (JWT, validation)
├── /migrations/          Database migration scripts
├── /tests/               Backend test suite
├── /logs/                Application logs
├── /vendor/              Composer dependencies
├── index.php             API front controller
├── .htaccess             Rewrite rules for clean URLs
├── composer.json         PHP dependency management
└── .env                  Environment configuration (NOT in git)
```

---

## Quick Start

### 1. Environment Setup

```bash
# Copy environment template
cp .env.example .env

# Edit with your database credentials
nano .env
```

Required `.env` variables:
```env
DB_HOST=localhost
DB_NAME=sci_bono_lms
DB_USER=your_user
DB_PASS=your_password
JWT_SECRET=your_random_secret_key
```

### 2. Install Dependencies

```bash
cd /var/www/html/sci-bono-aifluency/api
composer install
```

### 3. Run Database Migrations

```bash
cd migrations
bash run-all-migrations.sh
```

### 4. Test the API

```bash
cd tests
bash run_all_tests.sh
```

Expected output: `Success Rate: 100%` (9/9 tests passing)

---

## API Endpoints

Base URL: `/api`

### Authentication (`/api/auth/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/register` | User registration | No |
| POST | `/auth/login` | User login | No |
| POST | `/auth/logout` | User logout (blacklist token) | Yes |
| POST | `/auth/refresh` | Refresh access token | Yes (refresh token) |
| GET | `/auth/me` | Get current user | Yes |

### Users (`/api/users/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/users` | List all users | Yes (admin/instructor) |
| GET | `/users/:id` | Get user by ID | Yes |
| PUT | `/users/:id` | Update user | Yes (self or admin) |
| DELETE | `/users/:id` | Delete user | Yes (admin only) |

### Courses (`/api/courses/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/courses` | List all courses | No |
| GET | `/courses/:id` | Get course details | No |
| POST | `/courses` | Create course | Yes (admin/instructor) |
| PUT | `/courses/:id` | Update course | Yes (admin/instructor) |
| DELETE | `/courses/:id` | Delete course | Yes (admin only) |

### Modules (`/api/modules/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/modules/:id` | Get module details | No |
| GET | `/modules/:id/lessons` | List module lessons | No |
| POST | `/modules` | Create module | Yes (admin/instructor) |
| PUT | `/modules/:id` | Update module | Yes (admin/instructor) |

### Lessons (`/api/lessons/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/lessons/:id` | Get lesson details | No |
| POST | `/lessons/:id/complete` | Mark lesson complete | Yes |
| GET | `/lessons/:id/progress` | Get user progress | Yes |

### Quizzes (`/api/quizzes/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/quizzes/:id` | Get quiz questions | Yes |
| POST | `/quizzes/:id/submit` | Submit quiz attempt | Yes |
| GET | `/quizzes/attempts/:id` | Get attempt details | Yes |

### Projects (`/api/projects/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/projects/:id/submit` | Submit project | Yes (student) |
| GET | `/projects/submissions/:id` | Get submission | Yes |
| PUT | `/projects/submissions/:id/grade` | Grade submission | Yes (instructor) |

### Enrollments (`/api/enrollments/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/enrollments` | List user enrollments | Yes |
| POST | `/enrollments` | Enroll in course | Yes |
| DELETE | `/enrollments/:id` | Unenroll from course | Yes |

### Certificates (`/api/certificates/*`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/certificates/:id` | Get certificate | Yes |
| POST | `/certificates/generate` | Generate certificate | Yes (auto on completion) |

---

## MVC Architecture

### Controllers (`/controllers/`)

Handle incoming HTTP requests and coordinate responses.

**Pattern**:
```php
class UserController {
    public function index($request) {
        // List all users
        $users = User::all();
        return Response::json(['data' => $users]);
    }

    public function show($request, $id) {
        // Show single user
        $user = User::find($id);
        return Response::json(['data' => $user]);
    }
}
```

**Available Controllers**:
- `AuthController.php` - Authentication
- `UserController.php` - User management
- `CourseController.php` - Course CRUD
- `EnrollmentController.php` - Course enrollments
- `ModuleController.php` - Module management
- `LessonController.php` - Lesson content
- `QuizController.php` - Quiz operations
- `ProjectController.php` - Project submissions
- `CertificateController.php` - Certificate generation

### Models (`/models/`)

Data models using Active Record pattern with `BaseModel`.

**Pattern**:
```php
class User extends BaseModel {
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password', 'role'];

    // Relationships
    public function enrollments() {
        return $this->hasMany(Enrollment::class);
    }

    // Custom methods
    public function isAdmin() {
        return $this->role === 'admin';
    }
}
```

**Available Models**:
- `BaseModel.php` - Base class with CRUD methods
- `User.php` - User accounts
- `Course.php` - Courses
- `Module.php` - Course modules
- `Lesson.php` - Lesson content
- `LessonProgress.php` - Lesson tracking
- `Quiz.php` - Quizzes
- `QuizAttempt.php` - Quiz submissions
- `QuizQuestion.php` - Quiz questions
- `Project.php` - Project assignments
- `ProjectSubmission.php` - Project submissions
- `Enrollment.php` - Course enrollments
- `Certificate.php` - Certificates

### Routes (`/routes/`)

Define URL-to-controller mappings.

**File**: `api.php`

```php
// Authentication routes
$router->post('/auth/register', 'AuthController@register');
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/logout', 'AuthController@logout', ['auth']);
$router->post('/auth/refresh', 'AuthController@refresh', ['auth']);
$router->get('/auth/me', 'AuthController@me', ['auth']);

// User routes
$router->get('/users', 'UserController@index', ['auth', 'role:admin,instructor']);
$router->get('/users/:id', 'UserController@show', ['auth']);
$router->put('/users/:id', 'UserController@update', ['auth']);
```

Middleware can be specified as array: `['auth', 'role:admin']`

### Middleware (`/middleware/`)

Filter requests before reaching controllers.

**AuthMiddleware.php**:
- Validates JWT tokens
- Checks token expiry
- Verifies token not blacklisted
- Sets current user in request

**CorsMiddleware.php**:
- Handles CORS headers
- Allows cross-origin requests from frontend
- Handles preflight OPTIONS requests

### Views (`/views/`)

Server-rendered templates for emails, PDFs, reports.

**Structure**:
```
/views/
├── /emails/
│   ├── welcome.php          Welcome email template
│   ├── password-reset.php   Password reset email
│   └── quiz-graded.php      Quiz graded notification
├── /pdf/
│   ├── certificate.php      Certificate PDF template
│   └── transcript.php       Transcript PDF template
└── /reports/
    ├── student-progress.php Student progress report
    └── instructor-grades.php Instructor grade book
```

---

## Authentication

### JWT Token System

**Access Token**:
- Expiry: 1 hour
- Stored in: LocalStorage (`access_token`)
- Used for: API authentication
- Header: `Authorization: Bearer <token>`

**Refresh Token**:
- Expiry: 7 days
- Stored in: LocalStorage (`refresh_token`)
- Used for: Obtaining new access token
- Endpoint: `POST /api/auth/refresh`

### Token Refresh Flow

1. Frontend detects access token expiring (< 5 minutes remaining)
2. Calls `POST /api/auth/refresh` with refresh token
3. Backend validates refresh token
4. Backend generates new access token
5. Frontend updates LocalStorage
6. Frontend retries original request with new token

### Token Blacklist

On logout:
- Access token added to `token_blacklist` table
- Refresh token added to `token_blacklist` table
- Tokens become invalid immediately
- Expiry cleanup runs periodically

---

## Database

### Connection

**File**: `/config/database.php`

Uses PDO with MySQL:
```php
$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

### Migrations

**Location**: `/migrations/`

Run all migrations:
```bash
cd migrations
bash run-all-migrations.sh
```

Run single migration:
```bash
mysql -u root -p sci_bono_lms < 001_create_users.sql
```

**Available Migrations**:
1. `001_create_users.sql` - Users table
2. `002_create_courses.sql` - Courses and modules
3. `003_create_lessons.sql` - Lessons and progress
4. `004_create_quizzes.sql` - Quizzes and questions
5. `005_create_projects.sql` - Projects and submissions
6. `006_create_token_blacklist.sql` - Token blacklist

### Schema Overview

**Core Tables**:
- `users` - User accounts (auth, profile)
- `courses` - Course catalog
- `modules` - Course modules (6 modules)
- `lessons` - Lesson content (chapters)
- `lesson_progress` - User lesson tracking
- `enrollments` - User-course relationships
- `quizzes` - Quiz definitions
- `quiz_questions` - Quiz questions
- `quiz_attempts` - User quiz submissions
- `projects` - Project assignments
- `project_submissions` - User project submissions
- `certificates` - Generated certificates
- `token_blacklist` - Invalidated tokens

**See**: `/Documentation/01-Technical/03-Database/schema-design.md` for full schema

---

## Testing

### Run All Tests

```bash
cd tests
bash run_all_tests.sh
```

### Run Individual Test

```bash
php tests/test_auth.php
php tests/test_users.php
```

### Test Coverage

Current: **9/9 tests passing (100%)**

**Test Files**:
- `test_auth.php` - Registration, login, logout, refresh
- `test_users.php` - User CRUD operations
- `test_routes.php` - Routing system
- `test_endpoints_simple.php` - Simple endpoint tests
- `test_all.php` - Comprehensive test suite
- `run_all_tests.sh` - Test runner script

### Adding New Tests

```php
<?php
require_once 'vendor/autoload.php';

// Test registration
$response = makeRequest('/api/auth/register', 'POST', [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'password123',
    'role' => 'student'
]);

assert($response['success'] === true, 'Registration should succeed');
```

---

## Utilities

### JWTHandler (`/utils/JWTHandler.php`)

**Methods**:
- `generate($payload)` - Generate JWT token
- `validate($token)` - Validate and decode token
- `isExpired($token)` - Check if token expired
- `blacklist($token)` - Add token to blacklist

**Usage**:
```php
$jwt = new JWTHandler();
$token = $jwt->generate(['user_id' => 1, 'exp' => time() + 3600]);
$payload = $jwt->validate($token);
```

### Validator (`/utils/Validator.php`)

**Methods**:
- `email($email)` - Validate email format
- `password($password)` - Validate password strength
- `required($fields, $data)` - Check required fields
- `sanitize($data)` - Sanitize input data

**Usage**:
```php
$validator = new Validator();
$validator->required(['email', 'password'], $_POST);
$validator->email($_POST['email']);
$validator->password($_POST['password']);
```

---

## Error Handling

### Response Format

**Success**:
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful"
}
```

**Error**:
```json
{
  "success": false,
  "error": "Error message",
  "code": 400
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

---

## Security

### Best Practices

✅ **Implemented**:
- Password hashing (bcrypt)
- Prepared statements (SQL injection prevention)
- JWT authentication
- Token blacklisting
- Input validation
- Output sanitization
- CORS middleware
- HTTPS ready

⚠️ **Additional Recommendations**:
- Rate limiting on auth endpoints
- CSRF tokens for forms (frontend)
- Content Security Policy headers
- Security headers (X-Frame-Options, etc.)
- Input sanitization for XSS

### Environment Security

- `.env` file excluded from git (`.gitignore`)
- Database credentials in `.env` only
- JWT secret rotated periodically
- Logs excluded from public access
- Vendor directory excluded from git

---

## Logging

### Application Logs

**Location**: `/logs/app.log`

**Format**:
```
[2025-11-11 12:34:56] INFO: User logged in (ID: 5)
[2025-11-11 12:35:10] ERROR: Failed login attempt (email@example.com)
[2025-11-11 12:36:22] WARNING: Token refresh failed (token expired)
```

**Log Levels**:
- `INFO` - General information
- `WARNING` - Warning messages
- `ERROR` - Error messages
- `DEBUG` - Debug information

**View Logs**:
```bash
tail -f logs/app.log
```

---

## Performance

### Optimization Tips

1. **Database Indexing**:
   - Add indexes on frequently queried columns
   - Use `EXPLAIN` to analyze query performance

2. **Caching** (Future):
   - Consider Redis/Memcached for session data
   - Cache frequently accessed data
   - Implement query result caching

3. **API Response Time**:
   - Current average: < 200ms
   - Target: < 100ms for simple queries

4. **Database Connection**:
   - Uses persistent connections
   - Connection pooling recommended for production

---

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Change `JWT_SECRET` to strong random string
- [ ] Enable HTTPS
- [ ] Configure proper file permissions (755 dirs, 644 files)
- [ ] Disable PHP error display (`display_errors=Off`)
- [ ] Enable error logging (`log_errors=On`)
- [ ] Set up automated backups
- [ ] Configure log rotation
- [ ] Test all endpoints
- [ ] Run security audit

### Apache Configuration

**Enable mod_rewrite**:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Virtual Host**:
```apache
<VirtualHost *:80>
    ServerName api.scibono.com
    DocumentRoot /var/www/html/sci-bono-aifluency

    <Directory /var/www/html/sci-bono-aifluency>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## Troubleshooting

### Common Issues

**Issue**: 404 errors on API endpoints
**Solution**: Enable `mod_rewrite`, check `.htaccess` file

**Issue**: Database connection failed
**Solution**: Verify `.env` credentials, check MySQL service running

**Issue**: JWT token invalid
**Solution**: Check token expiry, verify JWT secret matches

**Issue**: CORS errors
**Solution**: Check `CorsMiddleware`, add frontend origin to whitelist

**Issue**: Tests failing
**Solution**: Run migrations, check database credentials, verify test data

---

## Contributing

### Code Standards

- Follow PSR-12 coding standards
- Use meaningful variable/function names
- Add PHPDoc comments for classes/methods
- Write tests for new features
- Update documentation

### Git Workflow

```bash
git checkout -b feature/new-endpoint
# Make changes
git add .
git commit -m "feat: Add new endpoint for X"
git push origin feature/new-endpoint
# Create pull request
```

---

## Documentation

### Full Documentation

- **Architecture**: `/Documentation/ARCHITECTURE_DECISION.md`
- **MVC Plan**: `/Documentation/MVC_TRANSFORMATION_PLAN.md`
- **Database**: `/Documentation/01-Technical/03-Database/schema-design.md`
- **Phase 1**: `/PHASE1_SUMMARY.md`
- **Testing**: `/PHASE1_TESTING.md`

### Quick Links

- [Backend Testing Summary](/api/tests/test_results_summary.txt)
- [API Routes](/api/routes/api.php)
- [Database Config](/api/config/database.php)
- [JWT Handler](/api/utils/JWTHandler.php)

---

## Support

For issues or questions:
1. Check documentation in `/Documentation/`
2. Review test files in `/tests/`
3. Check logs in `/logs/app.log`
4. Create GitHub issue

---

**Last Updated**: November 11, 2025
**Version**: 1.0.0
**Status**: ✅ Production Ready
