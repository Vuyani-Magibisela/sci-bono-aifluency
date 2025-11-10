# Testing Procedures - Sci-Bono AI Fluency LMS

**Document Version:** 1.0
**Last Updated:** 2025-10-28
**Author:** Development Team
**Status:** Active Testing Guidelines

---

## Table of Contents

1. [Introduction](#introduction)
2. [Testing Philosophy](#testing-philosophy)
3. [Test Environment Setup](#test-environment-setup)
4. [Static PWA Baseline Testing](#static-pwa-baseline-testing)
5. [API Endpoint Testing](#api-endpoint-testing)
6. [Database Testing](#database-testing)
7. [Authentication Testing](#authentication-testing)
8. [Frontend Integration Testing](#frontend-integration-testing)
9. [Content Migration Testing](#content-migration-testing)
10. [Performance Testing](#performance-testing)
11. [Security Testing](#security-testing)
12. [Browser Compatibility Testing](#browser-compatibility-testing)
13. [Mobile Responsive Testing](#mobile-responsive-testing)
14. [PWA Functionality Testing](#pwa-functionality-testing)
15. [Regression Testing](#regression-testing)
16. [User Acceptance Testing (UAT)](#user-acceptance-testing-uat)
17. [Automated Testing Strategy](#automated-testing-strategy)
18. [Bug Reporting & Tracking](#bug-reporting--tracking)
19. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This document establishes comprehensive testing procedures for the Sci-Bono AI Fluency platform during its migration from a static Progressive Web App to a full-featured Learning Management System. It ensures quality, reliability, and user experience standards are maintained throughout development.

### Scope

**Testing Coverage:**
- Static PWA baseline functionality (pre-migration benchmark)
- Backend API endpoints (authentication, user management, course delivery)
- Database operations (CRUD, integrity, performance)
- Frontend-backend integration
- Content migration accuracy
- Security and authentication flows
- Cross-browser compatibility
- Mobile responsiveness
- Performance benchmarks
- User workflows end-to-end

**Out of Scope:**
- Third-party service testing (Google Analytics, Font Awesome CDN)
- Infrastructure testing (Apache/MySQL configuration)
- Load testing with 10,000+ concurrent users (Phase 2 activity)

### Testing Goals

1. **Zero Regression**: Existing static PWA features continue working
2. **Functional Correctness**: All new features work as specified
3. **Data Integrity**: No data loss or corruption during migration
4. **Security Assurance**: Authentication and authorization robust
5. **Performance Standards**: Page loads < 2s, API responses < 200ms
6. **User Experience**: Intuitive workflows, clear error messages
7. **Cross-Platform Compatibility**: Works on all major browsers/devices

---

## Testing Philosophy

### Principles

1. **Test Early, Test Often**
   - Write tests before or alongside code
   - Run tests after every significant change
   - Automate repetitive tests

2. **Test Pyramid Approach**
   - Many unit tests (fast, isolated)
   - Moderate integration tests (combined components)
   - Few end-to-end tests (full user workflows)

3. **Realistic Test Data**
   - Use production-like data volumes
   - Include edge cases (empty, very long, special characters)
   - Test with actual course content

4. **Document Test Results**
   - Record all test executions
   - Track bugs found and fixed
   - Maintain test evidence (screenshots, logs)

5. **Continuous Quality**
   - Testing is everyone's responsibility
   - Fix bugs immediately, don't accumulate debt
   - Re-test after every bug fix

---

## Test Environment Setup

### Required Environments

#### 1. Local Development Environment

**Purpose:** Developer testing during feature implementation

**Setup:**
```bash
# LAMP Stack
- PHP 8.1+ (localhost)
- MySQL 8.0+ (localhost:3306)
- Apache 2.4+ or PHP built-in server

# Project location
/var/www/html/sci-bono-aifluency/

# Database
Database: ai_fluency_lms_dev
User: dev_user (all privileges)
```

**Test Data:**
- Sample user accounts (student, instructor, admin)
- Subset of content (5-10 lessons)
- Sample quiz with 5-10 questions

#### 2. Staging Environment

**Purpose:** Pre-production testing with full dataset

**Setup:**
```bash
# Production-like LAMP stack
- Matches production PHP/MySQL versions
- Full course content migrated
- Realistic user data (anonymized if needed)

# Domain
https://staging.aifluency.sci-bono.org (or subdomain)

# Database
Database: ai_fluency_lms_staging
Periodic refresh from production data
```

#### 3. Production Environment

**Purpose:** Live system serving actual users

**Testing Approach:**
- Smoke testing after deployments
- Monitoring and alerting
- User feedback tracking
- No destructive testing

### Test Data Management

**Test User Accounts:**

| Role | Username | Email | Password | Use Case |
|------|----------|-------|----------|----------|
| Student | test_student | student@test.local | Test123! | Basic learning workflows |
| Instructor | test_instructor | instructor@test.local | Test123! | Course management, grading |
| Admin | test_admin | admin@test.local | Test123! | Full system access |

**Test Content:**
- Maintain separate test database with sample content
- Reset test database before each major test run
- Use SQL scripts for consistent test data setup

---

## Static PWA Baseline Testing

### Purpose
Establish baseline functionality of current static PWA before migration begins. This serves as reference for regression testing.

### PWA Core Features Test

**Test ID:** PWA-001
**Feature:** Service Worker Registration
**Test Steps:**
1. Open browser DevTools > Application > Service Workers
2. Navigate to `https://[domain]/index.html`
3. Observe Service Worker registration

**Expected Result:**
- Service Worker registers successfully
- Status shows "activated and running"
- Scope: `/`

**Test ID:** PWA-002
**Feature:** Offline Functionality
**Test Steps:**
1. Visit site and navigate through 3-5 pages
2. Open DevTools > Network > Toggle "Offline" mode
3. Navigate to cached pages
4. Navigate to uncached page

**Expected Result:**
- Cached pages load from Service Worker cache
- Uncached pages show `offline.html` fallback
- No console errors related to caching

**Test ID:** PWA-003
**Feature:** PWA Installation
**Test Steps:**
1. Visit site in Chrome/Edge (desktop)
2. Wait for install prompt or check address bar for install icon
3. Click "Install" button or use browser install option
4. Launch installed PWA

**Expected Result:**
- Install button appears in header (desktop)
- Browser shows install prompt
- App installs as standalone application
- Launches in app window (no browser chrome)

### Navigation Test

**Test ID:** NAV-001
**Feature:** Landing Page to Module Navigation
**Test Steps:**
1. Load `index.html`
2. Click on "Module 1: AI Foundations" card
3. Verify redirect to `module1.html`

**Expected Result:**
- Navigation occurs without page reload delay
- Module 1 overview page displays correctly
- All chapter cards visible

**Test ID:** NAV-002
**Feature:** Chapter Navigation (Previous/Next)
**Test Steps:**
1. Navigate to `chapter1.html`
2. Click "Next" button
3. Verify redirect to next chapter
4. Click "Previous" button

**Expected Result:**
- Next chapter loads correctly
- Previous chapter returns to chapter1
- Navigation buttons enabled/disabled appropriately

**Test ID:** NAV-003
**Feature:** Internal Chapter Tabs
**Test Steps:**
1. Open `chapter1.html`
2. Click section tab (e.g., "Timeline")
3. Verify scroll to section

**Expected Result:**
- Page scrolls to corresponding section
- Tab becomes active (highlighted)
- URL updates with hash (#timeline)

### Quiz Functionality Test

**Test ID:** QUIZ-001
**Feature:** Quiz Rendering
**Test Steps:**
1. Navigate to `module1Quiz.html`
2. Observe quiz content loads

**Expected Result:**
- All questions render with 4 options each
- Radio buttons functional
- "Submit" button visible

**Test ID:** QUIZ-002
**Feature:** Quiz Submission & Scoring
**Test Steps:**
1. Load `module1Quiz.html`
2. Answer all questions (mix of correct/incorrect)
3. Click "Submit Quiz"
4. Observe results display

**Expected Result:**
- Score calculated correctly (% of correct answers)
- Feedback message appropriate to score
- Correct answers highlighted in green
- Incorrect answers highlighted in red
- Explanations displayed for each question

**Test ID:** QUIZ-003
**Feature:** Quiz Restart
**Test Steps:**
1. Complete quiz and view results
2. Click "Restart Quiz"

**Expected Result:**
- Quiz resets to initial state
- All radio buttons unchecked
- Results hidden
- Question counter resets

### Content Display Test

**Test ID:** CONTENT-001
**Feature:** Chapter Content Rendering
**Test Steps:**
1. Open 5 random chapter pages
2. Scroll through entire content
3. Observe all elements

**Expected Result:**
- Headings, paragraphs render correctly
- SVG graphics display properly (not broken images)
- Lists formatted correctly
- Timeline elements (if present) display in order
- No layout overflow or broken styling

**Test ID:** CONTENT-002
**Feature:** Responsive Layout (Mobile)
**Test Steps:**
1. Open DevTools, set to mobile viewport (375x667)
2. Navigate through landing page, module page, chapter page
3. Test navigation menu, buttons

**Expected Result:**
- Layout adapts to mobile width (no horizontal scroll)
- Text readable without zooming
- Buttons/links tappable (sufficient size)
- Images scale appropriately
- Navigation menu accessible

### Performance Baseline

**Test ID:** PERF-001
**Feature:** Page Load Time
**Test Steps:**
1. Clear browser cache
2. Open DevTools > Network tab
3. Load `index.html`
4. Record `DOMContentLoaded` and `Load` times

**Expected Result:**
- DOMContentLoaded < 1s
- Full page load < 2s
- No excessive resource sizes (images < 200KB each)

**Test ID:** PERF-002
**Feature:** Navigation Speed
**Test Steps:**
1. Navigate from module1.html → chapter1.html → chapter2.html
2. Measure time between click and page interactive

**Expected Result:**
- Page transitions feel instant (< 500ms)
- No visible loading delays
- Smooth scroll animations

### Baseline Test Report

**Document all baseline results in:**
`/Documentation/05-Maintenance/baseline-test-results.md`

**Include:**
- Browser versions tested (Chrome 120, Firefox 121, Safari 17, Edge 120)
- Screenshots of passing tests
- Performance metrics (Lighthouse scores)
- Any issues found in static PWA (to be fixed)

---

## API Endpoint Testing

### Authentication Endpoints

**Test ID:** API-AUTH-001
**Endpoint:** `POST /api/auth/register`
**Purpose:** User registration

**Test Cases:**

| Case | Input | Expected Status | Expected Response |
|------|-------|-----------------|-------------------|
| Valid registration | `{"email":"new@test.com","password":"Pass123!","name":"Test User","role":"student"}` | 201 Created | `{"success":true,"message":"User registered","user_id":X}` |
| Duplicate email | Same email as existing user | 409 Conflict | `{"success":false,"error":"Email already registered"}` |
| Weak password | `{"password":"123"}` | 400 Bad Request | `{"success":false,"error":"Password must be at least 8 characters"}` |
| Missing field | `{"email":"test@test.com"}` (no password) | 400 Bad Request | `{"success":false,"error":"Missing required field: password"}` |
| Invalid email | `{"email":"notanemail"}` | 400 Bad Request | `{"success":false,"error":"Invalid email format"}` |

**Test ID:** API-AUTH-002
**Endpoint:** `POST /api/auth/login`
**Purpose:** User authentication

**Test Cases:**

| Case | Input | Expected Status | Expected Response |
|------|-------|-----------------|-------------------|
| Valid login | `{"email":"student@test.com","password":"Test123!"}` | 200 OK | `{"success":true,"token":"jwt.token.here","user":{...}}` |
| Wrong password | Correct email, wrong password | 401 Unauthorized | `{"success":false,"error":"Invalid credentials"}` |
| Non-existent email | Email not in database | 401 Unauthorized | `{"success":false,"error":"Invalid credentials"}` |
| Missing credentials | `{"email":"test@test.com"}` (no password) | 400 Bad Request | `{"success":false,"error":"Missing required field: password"}` |

**Test ID:** API-AUTH-003
**Endpoint:** `POST /api/auth/refresh`
**Purpose:** JWT token refresh

**Test Cases:**

| Case | Input | Expected Status | Expected Response |
|------|-------|-----------------|-------------------|
| Valid refresh token | `{"refresh_token":"valid.refresh.token"}` | 200 OK | `{"success":true,"token":"new.jwt.token"}` |
| Expired refresh token | Expired token | 401 Unauthorized | `{"success":false,"error":"Refresh token expired"}` |
| Invalid token | Malformed token | 401 Unauthorized | `{"success":false,"error":"Invalid token"}` |

**Test ID:** API-AUTH-004
**Endpoint:** `POST /api/auth/logout`
**Purpose:** Invalidate user session

**Test Cases:**

| Case | Input | Expected Status | Expected Response |
|------|-------|-----------------|-------------------|
| Valid logout | Valid JWT in Authorization header | 200 OK | `{"success":true,"message":"Logged out successfully"}` |
| No token | No Authorization header | 401 Unauthorized | `{"success":false,"error":"No token provided"}` |

### User Management Endpoints

**Test ID:** API-USER-001
**Endpoint:** `GET /api/users/me`
**Purpose:** Get current user profile

**Test Cases:**

| Case | Input | Expected Status | Expected Response |
|------|-------|-----------------|-------------------|
| Authenticated request | Valid JWT token | 200 OK | `{"success":true,"user":{"id":1,"name":"Test User","email":"test@test.com","role":"student"}}` |
| No token | No Authorization header | 401 Unauthorized | `{"success":false,"error":"Authentication required"}` |
| Invalid token | Malformed JWT | 401 Unauthorized | `{"success":false,"error":"Invalid token"}` |

**Test ID:** API-USER-002
**Endpoint:** `PUT /api/users/me`
**Purpose:** Update user profile

**Test Cases:**

| Case | Input | Expected Status | Expected Response |
|------|-------|-----------------|-------------------|
| Valid update | `{"name":"Updated Name"}` | 200 OK | `{"success":true,"user":{...updated data}}` |
| Change email | `{"email":"new@test.com"}` | 200 OK | Updated user object with new email |
| Duplicate email | Email already in use | 409 Conflict | `{"success":false,"error":"Email already taken"}` |
| Invalid field | `{"role":"admin"}` (students can't change role) | 403 Forbidden | `{"success":false,"error":"Cannot modify role"}` |

### Course Content Endpoints (Future Phase)

**Test ID:** API-COURSE-001
**Endpoint:** `GET /api/courses`
**Purpose:** List all courses

**Expected:** Array of course objects with metadata

**Test ID:** API-COURSE-002
**Endpoint:** `GET /api/modules/:moduleId/lessons`
**Purpose:** Get lessons in a module

**Expected:** Array of lesson objects with content

### API Testing Tools

**Recommended Tools:**
1. **Postman** - API request collections
2. **cURL** - Command-line testing
3. **PHPUnit** - Automated API tests
4. **Insomnia** - Alternative to Postman

**Sample cURL Test:**
```bash
# Test user registration
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"Test123!","name":"Test User","role":"student"}'

# Expected: 201 Created with user object

# Test login
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"Test123!"}'

# Expected: 200 OK with JWT token

# Test protected endpoint
curl -X GET http://localhost/api/users/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..."

# Expected: 200 OK with user profile
```

---

## Database Testing

### Schema Integrity Tests

**Test ID:** DB-001
**Test:** Table Creation
**Procedure:**
```sql
-- Verify all required tables exist
SHOW TABLES;

-- Expected tables:
-- users, courses, modules, lessons, quizzes, quiz_questions,
-- enrollments, progress, submissions, certificates
```

**Test ID:** DB-002
**Test:** Foreign Key Constraints
**Procedure:**
```sql
-- Attempt to insert lesson with invalid module_id
INSERT INTO lessons (module_id, title, slug, order_index)
VALUES (999, 'Invalid Lesson', 'invalid', 1);

-- Expected: Foreign key constraint error
```

**Test ID:** DB-003
**Test:** NOT NULL Constraints
**Procedure:**
```sql
-- Attempt to insert user without required fields
INSERT INTO users (email) VALUES ('test@test.com');

-- Expected: Error - password field cannot be null
```

### Data Integrity Tests

**Test ID:** DB-004
**Test:** Unique Constraints
**Procedure:**
```sql
-- Insert user
INSERT INTO users (email, password_hash, name, role)
VALUES ('test@test.com', 'hash', 'Test User', 'student');

-- Attempt duplicate email
INSERT INTO users (email, password_hash, name, role)
VALUES ('test@test.com', 'hash2', 'Test User 2', 'student');

-- Expected: Duplicate entry error for unique key 'email'
```

**Test ID:** DB-005
**Test:** CASCADE Delete
**Procedure:**
```sql
-- Create module with lessons
INSERT INTO modules (id, course_id, title, order_index) VALUES (99, 1, 'Test Module', 99);
INSERT INTO lessons (module_id, title, slug, order_index) VALUES (99, 'Test Lesson', 'test-lesson', 1);

-- Delete module
DELETE FROM modules WHERE id = 99;

-- Check lessons deleted
SELECT COUNT(*) FROM lessons WHERE module_id = 99;

-- Expected: 0 (lesson automatically deleted)
```

### Query Performance Tests

**Test ID:** DB-PERF-001
**Test:** Indexed Column Query Speed
**Procedure:**
```sql
-- Query should use index on email column
EXPLAIN SELECT * FROM users WHERE email = 'test@test.com';

-- Expected: "type: const" or "type: ref" (using index)
-- Execution time: < 1ms for database with 1000 users
```

**Test ID:** DB-PERF-002
**Test:** JOIN Query Performance
**Procedure:**
```sql
-- Complex query: course -> modules -> lessons
SELECT c.title AS course, m.title AS module, l.title AS lesson
FROM courses c
JOIN modules m ON m.course_id = c.id
JOIN lessons l ON l.module_id = m.id
WHERE c.id = 1
ORDER BY m.order_index, l.order_index;

-- Expected: Execution time < 50ms with full dataset (44 lessons)
```

### Backup and Restore Test

**Test ID:** DB-BACKUP-001
**Test:** Database Backup
**Procedure:**
```bash
# Create backup
mysqldump -u user -p ai_fluency_lms > backup_test.sql

# Verify file created and not empty
ls -lh backup_test.sql

# Expected: File size > 100KB (with content)
```

**Test ID:** DB-RESTORE-001
**Test:** Database Restore
**Procedure:**
```bash
# Drop test database
mysql -u user -p -e "DROP DATABASE IF EXISTS ai_fluency_lms_test;"

# Create fresh database
mysql -u user -p -e "CREATE DATABASE ai_fluency_lms_test;"

# Restore from backup
mysql -u user -p ai_fluency_lms_test < backup_test.sql

# Verify data restored
mysql -u user -p ai_fluency_lms_test -e "SELECT COUNT(*) FROM lessons;"

# Expected: Same count as original database
```

---

## Authentication Testing

### Password Security

**Test ID:** AUTH-001
**Test:** Password Hashing
**Procedure:**
1. Register new user with password "Test123!"
2. Query database: `SELECT password_hash FROM users WHERE email = 'test@test.com';`
3. Verify password_hash is bcrypt hash (starts with `$2y$`)
4. Verify hash length = 60 characters

**Expected:** Password never stored in plaintext

**Test ID:** AUTH-002
**Test:** Password Verification
**Procedure:**
1. Login with correct password → Success
2. Login with incorrect password → Failure
3. Login with password differing only in case → Failure (case-sensitive)

**Expected:** Only exact password match succeeds

### JWT Token Security

**Test ID:** AUTH-003
**Test:** Token Generation
**Procedure:**
1. Login successfully
2. Inspect returned JWT token
3. Decode token at jwt.io
4. Verify payload contains: user_id, email, role, exp (expiry)

**Expected:**
- Token is valid JWT format (3 parts separated by dots)
- Expiry set to 1 hour from issue
- No sensitive data (passwords) in token

**Test ID:** AUTH-004
**Test:** Token Expiry
**Procedure:**
1. Login and receive token
2. Use token in API request → Success (200 OK)
3. Wait for token to expire (or manually set short expiry for test)
4. Use expired token → Failure (401 Unauthorized)

**Expected:** Expired tokens rejected

**Test ID:** AUTH-005
**Test:** Token Tampering
**Procedure:**
1. Login and receive valid token
2. Modify token payload (change user_id)
3. Use tampered token in API request

**Expected:** 401 Unauthorized (signature validation fails)

### Session Management

**Test ID:** AUTH-006
**Test:** Logout Clears Session
**Procedure:**
1. Login and receive token
2. Call `/api/auth/logout`
3. Attempt to use same token after logout

**Expected:** Token blacklisted/invalidated (401 Unauthorized)

**Test ID:** AUTH-007
**Test:** Concurrent Sessions
**Procedure:**
1. Login from Browser A → Receive token A
2. Login from Browser B (same user) → Receive token B
3. Use token A in Browser A → Still valid
4. Use token B in Browser B → Still valid

**Expected:** Both tokens valid (allow multiple devices)

### Role-Based Access Control (RBAC)

**Test ID:** AUTH-008
**Test:** Student Access Restrictions
**Procedure:**
1. Login as student
2. Attempt to access admin endpoint: `GET /api/admin/users`

**Expected:** 403 Forbidden (insufficient permissions)

**Test ID:** AUTH-009
**Test:** Admin Full Access
**Procedure:**
1. Login as admin
2. Access admin endpoint: `GET /api/admin/users` → Success
3. Access student endpoint: `GET /api/users/me` → Success

**Expected:** Admin can access all endpoints

**Test ID:** AUTH-010
**Test:** Instructor Partial Access
**Procedure:**
1. Login as instructor
2. Access own courses: `GET /api/instructor/courses` → Success
3. Access all users: `GET /api/admin/users` → Forbidden

**Expected:** Instructor has elevated but not full admin access

---

## Frontend Integration Testing

### Login Flow

**Test ID:** FE-LOGIN-001
**Test:** Successful Login
**Test Steps:**
1. Navigate to `login.html`
2. Enter valid credentials
3. Click "Login"
4. Observe redirect

**Expected Result:**
- API call to `/api/auth/login` succeeds
- JWT token stored in localStorage
- Redirect to `student-dashboard.html`
- Dashboard displays user name

**Test ID:** FE-LOGIN-002
**Test:** Login Failure Handling
**Test Steps:**
1. Navigate to `login.html`
2. Enter invalid credentials
3. Click "Login"

**Expected Result:**
- Error message displayed: "Invalid email or password"
- User remains on login page
- Form not cleared (email retained)
- No token stored

**Test ID:** FE-LOGIN-003
**Test:** Form Validation
**Test Steps:**
1. Navigate to `login.html`
2. Leave email empty, enter password
3. Click "Login"

**Expected Result:**
- Browser validation prevents submission
- Error message: "Please fill out this field"
- No API call made

### Registration Flow

**Test ID:** FE-REG-001
**Test:** Successful Registration
**Test Steps:**
1. Navigate to `signup.html`
2. Fill all fields with valid data
3. Click "Sign Up"

**Expected Result:**
- API call to `/api/auth/register` succeeds
- Success message displayed
- Redirect to login page (or auto-login to dashboard)

**Test ID:** FE-REG-002
**Test:** Duplicate Email Handling
**Test Steps:**
1. Navigate to `signup.html`
2. Enter email that already exists
3. Submit form

**Expected Result:**
- Error message: "Email already registered"
- User remains on signup page
- No new user created in database

### Dashboard Data Loading

**Test ID:** FE-DASH-001
**Test:** Student Dashboard Loads User Data
**Test Steps:**
1. Login as student
2. Observe dashboard loads

**Expected Result:**
- API call to `/api/users/me` made with JWT token
- User name displayed in sidebar: "Welcome, John Doe"
- User email displayed
- Profile avatar/initials shown

**Test ID:** FE-DASH-002
**Test:** Unauthenticated Access Blocked
**Test Steps:**
1. Clear localStorage (remove JWT token)
2. Navigate directly to `student-dashboard.html`

**Expected Result:**
- JavaScript checks for token
- No token found → redirect to `login.html`
- User cannot access dashboard without authentication

### Error Handling

**Test ID:** FE-ERROR-001
**Test:** Network Error Handling
**Test Steps:**
1. Stop backend server
2. Attempt to login

**Expected Result:**
- Error message: "Unable to connect to server. Please try again."
- User-friendly message (not technical error)
- Retry option available

**Test ID:** FE-ERROR-002
**Test:** 500 Server Error Handling
**Test Steps:**
1. Trigger 500 error from API (e.g., database connection failure)
2. Observe frontend response

**Expected Result:**
- Error message: "An unexpected error occurred. Please try again later."
- Error logged to console for debugging
- User not shown technical error details

---

## Content Migration Testing

### Pre-Migration Validation

**Test ID:** MIG-001
**Test:** Content Extraction Completeness
**Procedure:**
1. Run `extract-chapters.php`
2. Compare `lessons.json` count vs HTML file count

**Expected:** 44 lesson records extracted (matching 44 chapter HTML files)

**Test ID:** MIG-002
**Test:** Quiz Data Extraction Accuracy
**Procedure:**
1. Run `extract-quizzes.php`
2. Manually count questions in `module1Quiz.html`
3. Count questions in `quiz_questions.json` for quiz_id=1

**Expected:** Question counts match exactly

### Post-Migration Validation

**Test ID:** MIG-003
**Test:** Content Rendering Comparison
**Procedure:**
1. Open `chapter1.html` in browser → Take screenshot A
2. Query database: `SELECT content FROM lessons WHERE slug = 'chapter1';`
3. Render content HTML in test page → Take screenshot B
4. Compare screenshots visually

**Expected:** Identical appearance (no data loss, SVG intact)

**Test ID:** MIG-004
**Test:** Navigation Relationship Accuracy
**Procedure:**
1. Query lessons ordered by module_id, order_index
2. Verify sequence matches original HTML navigation flow
3. Compute previous/next based on order_index
4. Compare with original HTML previous/next links

**Expected:** Navigation relationships preserved correctly

**Test ID:** MIG-005
**Test:** Quiz Answer Correctness
**Procedure:**
1. For 10 random quiz questions from database
2. Find same question in original HTML file
3. Compare `correctAnswer` value in JS vs `correct_option` in database

**Expected:** All answer indices match (0-based)

---

## Performance Testing

### Page Load Performance

**Test ID:** PERF-003
**Test:** API-Driven Page Load Time
**Procedure:**
1. Clear browser cache
2. Login to dashboard
3. Measure time from navigation to dashboard fully interactive

**Acceptance Criteria:**
- Time to First Byte (TTFB) < 200ms
- First Contentful Paint (FCP) < 1s
- Time to Interactive (TTI) < 2s

**Test ID:** PERF-004
**Test:** Lesson Content Load Time
**Procedure:**
1. Navigate to lesson page (with content from database)
2. Measure API response time + rendering time

**Acceptance Criteria:**
- API `/api/lessons/:id` responds < 200ms
- Full page render < 2s
- Large HTML content (125KB) loads without lag

### API Response Time

**Test ID:** PERF-005
**Test:** Authentication API Performance
**Procedure:**
```bash
# Benchmark login endpoint
for i in {1..100}; do
  curl -X POST http://localhost/api/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com","password":"Test123!"}' \
    -w "%{time_total}\n" -o /dev/null -s
done | awk '{sum+=$1} END {print "Average: " sum/NR "s"}'
```

**Acceptance Criteria:**
- Average response time < 150ms
- 95th percentile < 300ms

**Test ID:** PERF-006
**Test:** Database Query Performance
**Procedure:**
```sql
-- Enable query profiling
SET profiling = 1;

-- Run test query
SELECT l.*, m.title AS module_title
FROM lessons l
JOIN modules m ON l.module_id = m.id
WHERE l.slug = 'chapter1';

-- Show profile
SHOW PROFILES;
```

**Acceptance Criteria:**
- Query execution < 10ms
- JOIN uses indexes (verify with EXPLAIN)

### Concurrent User Simulation

**Test ID:** PERF-007
**Test:** 100 Concurrent Requests
**Tools:** Apache Bench or wrk

**Procedure:**
```bash
# Test login endpoint with 100 concurrent requests
ab -n 1000 -c 100 -p login_data.json -T application/json \
  http://localhost/api/auth/login
```

**Acceptance Criteria:**
- 99% requests complete successfully (200 OK)
- Mean response time < 500ms
- No server errors (500s)

---

## Security Testing

### SQL Injection Prevention

**Test ID:** SEC-001
**Test:** Login SQL Injection
**Procedure:**
1. Attempt login with email: `admin' OR '1'='1`
2. Attempt login with password: `' OR '1'='1' --`

**Expected:** Login fails (no SQL injection vulnerability)

**Verification:** Prepared statements used, input sanitized

### XSS Prevention

**Test ID:** SEC-002
**Test:** Stored XSS in User Profile
**Procedure:**
1. Update user name to: `<script>alert('XSS')</script>`
2. View profile page

**Expected:** Script not executed, displayed as plain text

### CSRF Protection

**Test ID:** SEC-003
**Test:** Cross-Site Request Forgery
**Procedure:**
1. Create malicious HTML page with form auto-submitting to `/api/users/me`
2. Visit malicious page while logged in

**Expected:** Request rejected (CSRF token missing or invalid)

### Password Policy Enforcement

**Test ID:** SEC-004
**Test:** Weak Password Rejection
**Procedure:**
Attempt to register with passwords:
- `123` (too short)
- `password` (too common)
- `abcdefgh` (no numbers/symbols)

**Expected:** All rejected with error message

---

## Browser Compatibility Testing

### Supported Browsers

Test on:
- **Chrome** 120+ (Desktop & Mobile)
- **Firefox** 121+
- **Safari** 17+ (macOS & iOS)
- **Edge** 120+

### Compatibility Test Matrix

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| PWA Install | ✓ | ✓ | ⚠️ (manual) | ✓ |
| Service Worker | ✓ | ✓ | ✓ | ✓ |
| JWT Auth | ✓ | ✓ | ✓ | ✓ |
| SVG Graphics | ✓ | ✓ | ✓ | ✓ |
| CSS Grid Layout | ✓ | ✓ | ✓ | ✓ |
| LocalStorage | ✓ | ✓ | ✓ | ✓ |

**Test Procedure:**
1. Open site in each browser
2. Complete full user workflow (register → login → view lesson → take quiz)
3. Document any browser-specific issues

---

## Mobile Responsive Testing

### Device Matrix

Test on:
- **iPhone 12/13** (375x812)
- **iPhone Pro Max** (428x926)
- **Samsung Galaxy S21** (360x800)
- **iPad** (768x1024)
- **iPad Pro** (1024x1366)

### Mobile Test Cases

**Test ID:** MOBILE-001
**Test:** Touch Navigation
**Procedure:**
1. Open site on mobile device
2. Tap navigation buttons
3. Swipe through content

**Expected:** All interactive elements respond to touch, no hover-only features

**Test ID:** MOBILE-002
**Test:** Form Input on Mobile
**Procedure:**
1. Open login form on mobile
2. Tap email input → Keyboard appears
3. Enter credentials
4. Submit form

**Expected:** Virtual keyboard doesn't obscure submit button, form usable

**Test ID:** MOBILE-003
**Test:** Viewport Meta Tag
**Procedure:**
1. Load page on mobile
2. Check if page fits width (no horizontal scroll)

**Expected:** `<meta name="viewport" content="width=device-width, initial-scale=1.0">` applied

---

## PWA Functionality Testing

### Offline Mode

**Test ID:** PWA-004
**Test:** Offline Lesson Access
**Procedure:**
1. Visit 5 lessons while online
2. Enable airplane mode
3. Navigate to visited lessons

**Expected:** Cached lessons load, uncached show offline page

### App Installation

**Test ID:** PWA-005
**Test:** Add to Home Screen (iOS)
**Procedure:**
1. Open site in Safari on iPhone
2. Tap Share button
3. Tap "Add to Home Screen"
4. Launch app from home screen

**Expected:** App launches in standalone mode, manifest icons used

---

## Regression Testing

### After Each Migration Phase

**Regression Test Suite:**
1. Re-run Static PWA Baseline Tests
2. Verify no features broken
3. Test backward compatibility
4. Check performance hasn't degraded

**Documentation:**
- Record regression test results after each phase
- Track any regressions found and fixed
- Maintain regression test checklist

---

## User Acceptance Testing (UAT)

### Test Participants
- 2-3 students (target user group)
- 1 instructor
- 1 admin

### UAT Scenarios

**Scenario 1: New Student Onboarding**
1. Discover AI Fluency course online
2. Register for account
3. Browse course modules
4. Start first lesson
5. Complete lesson
6. Take module quiz
7. View results

**Success Criteria:**
- User completes flow without confusion
- No technical errors encountered
- Positive feedback on UX

**Scenario 2: Instructor Grading** (Future Phase)
1. Login as instructor
2. View student submissions
3. Grade assignment
4. Provide feedback

---

## Automated Testing Strategy

### Unit Tests (PHPUnit)

**Example:**
```php
// tests/Unit/AuthTest.php
class AuthTest extends TestCase {
    public function testUserRegistration() {
        $response = $this->post('/api/auth/register', [
            'email' => 'test@test.com',
            'password' => 'Test123!',
            'name' => 'Test User',
            'role' => 'student'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'test@test.com']);
    }
}
```

### Integration Tests

**Example:**
```php
// tests/Integration/LoginFlowTest.php
public function testCompleteLoginFlow() {
    // 1. Register user
    $this->post('/api/auth/register', [...]);

    // 2. Login
    $response = $this->post('/api/auth/login', [...]);
    $token = $response->json('token');

    // 3. Access protected endpoint
    $response = $this->get('/api/users/me', [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(200);
}
```

### Continuous Integration (CI)

**GitHub Actions Workflow:**
```yaml
# .github/workflows/test.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: vendor/bin/phpunit
```

---

## Bug Reporting & Tracking

### Bug Report Template

```markdown
## Bug Report

**ID:** BUG-XXX
**Title:** [Short description]
**Severity:** Critical / High / Medium / Low
**Priority:** P0 (Blocker) / P1 / P2 / P3

### Environment
- Browser: Chrome 120
- OS: Windows 11
- Server: Development

### Steps to Reproduce
1. Navigate to...
2. Click...
3. Observe...

### Expected Behavior
[What should happen]

### Actual Behavior
[What actually happens]

### Screenshots
[Attach screenshots]

### Error Messages
[Console errors, API responses]

### Impact
[How many users affected, what features broken]
```

### Bug Tracking

**Use:** GitHub Issues or Jira

**Bug Workflow:**
1. New → Triaged
2. Triaged → In Progress
3. In Progress → Fixed
4. Fixed → Ready for Test
5. Ready for Test → Verified
6. Verified → Closed

---

## Related Documents

- **[Content Migration Guide](../../03-Content-Management/content-migration-guide.md)** - Content migration procedures
- **[Deployment Checklist](../../04-Deployment/deployment-checklist.md)** - Deployment testing requirements
- **[Migration Roadmap](../01-Architecture/migration-roadmap.md)** - Migration phases and timelines

---

**Document End**

**Version History:**
- v1.0 (2025-10-28): Comprehensive testing procedures document created

**Maintained By:** QA Team & Development Team
**Review Schedule:** Update after each migration phase
**Next Steps:** Establish baseline test results before migration begins
