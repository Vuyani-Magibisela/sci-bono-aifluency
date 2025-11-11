# Manual API Testing Guide

**Version**: 1.0
**Last Updated**: 2025-11-10
**Controllers**: AuthController, UserController

---

## Table of Contents

1. [Environment Setup](#environment-setup)
2. [Authentication Endpoints](#authentication-endpoints)
3. [User Management Endpoints](#user-management-endpoints)
4. [Testing Checklist](#testing-checklist)
5. [Troubleshooting](#troubleshooting)

---

## Environment Setup

### Prerequisites

- Apache running on port 80
- MySQL database `ai_fluency_lms` accessible
- PHP 8.1+ with required extensions
- cURL installed for command-line testing

### Verify Environment

```bash
# Check Apache is running
sudo systemctl status apache2

# Check database connection
cd /var/www/html/sci-bono-aifluency/api
php -r "
require 'config/database.php';
echo \$pdo ? 'Database connected' : 'Failed';
"

# Check token_blacklist table exists
mysql -u vuksDev -p'Vu13#k*s3D3V' ai_fluency_lms -e "DESC token_blacklist;"
```

### Test API Entry Point

```bash
# Test basic routing
php -r "
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['REQUEST_URI'] = '/api/auth/me';
\$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_USER_AGENT'] = 'Test';
require '/var/www/html/sci-bono-aifluency/api/index.php';
"
```

Expected: JSON response (even if error, routing works)

---

## Authentication Endpoints

### 1. User Registration

**Endpoint**: `POST /api/auth/register`
**Authentication**: Not required

#### Test 1.1: Successful Registration

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Student",
    "email": "student@test.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role": "student"
  }'
```

**Expected Response** (201 Created):
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 2,
      "name": "Test Student",
      "email": "student@test.com",
      "role": "student",
      "is_active": true,
      "created_at": "2025-11-10 10:30:00"
    },
    "tokens": {
      "accessToken": "eyJ0eXAi...",
      "refreshToken": "eyJ0eXAi...",
      "expiresIn": 3600
    }
  }
}
```

**Save the accessToken** for subsequent tests!

#### Test 1.2: Duplicate Email

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Another User",
    "email": "student@test.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role": "student"
  }'
```

**Expected Response** (409 Conflict):
```json
{
  "success": false,
  "message": "Email address is already registered",
  "errors": {
    "email": "This email address is already in use"
  }
}
```

#### Test 1.3: Weak Password

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Weak Password User",
    "email": "weak@test.com",
    "password": "simple",
    "password_confirmation": "simple",
    "role": "student"
  }'
```

**Expected Response** (422 Validation Error):
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "password": "Password must contain at least one uppercase letter"
  }
}
```

#### Test 1.4: Password Mismatch

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "mismatch@test.com",
    "password": "SecurePass123!",
    "password_confirmation": "DifferentPass123!",
    "role": "student"
  }'
```

**Expected Response** (422):
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "password_confirmation": "Passwords do not match"
  }
}
```

---

### 2. User Login

**Endpoint**: `POST /api/auth/login`
**Authentication**: Not required

#### Test 2.1: Successful Login

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "student@test.com",
    "password": "SecurePass123!"
  }'
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 2,
      "name": "Test Student",
      "email": "student@test.com",
      "role": "student",
      "is_active": true,
      "last_login": "2025-11-10 10:35:00"
    },
    "statistics": {
      "enrollments": 0,
      "completed_lessons": 0,
      "quiz_attempts": 0,
      "average_quiz_score": 0,
      "certificates": 0
    },
    "tokens": {
      "accessToken": "eyJ0eXAi...",
      "refreshToken": "eyJ0eXAi...",
      "expiresIn": 3600
    }
  }
}
```

#### Test 2.2: Invalid Email

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "nonexistent@test.com",
    "password": "SecurePass123!"
  }'
```

**Expected Response** (401 Unauthorized):
```json
{
  "success": false,
  "message": "Invalid email or password"
}
```

#### Test 2.3: Invalid Password

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "student@test.com",
    "password": "WrongPassword123!"
  }'
```

**Expected Response** (401 Unauthorized):
```json
{
  "success": false,
  "message": "Invalid email or password"
}
```

#### Test 2.4: Login as Admin

```bash
# Use existing admin account
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@sci-bono.org",
    "password": "admin123"
  }'
```

**Save the admin accessToken** for admin operations!

---

### 3. Token Refresh

**Endpoint**: `POST /api/auth/refresh`
**Authentication**: Refresh token required

#### Test 3.1: Successful Token Refresh

```bash
# Use the refreshToken from login
curl -X POST http://localhost/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }'
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "tokens": {
      "accessToken": "eyJ0eXAi... (new token)",
      "refreshToken": "eyJ0eXAi... (new refresh token)",
      "expiresIn": 3600
    }
  }
}
```

#### Test 3.2: Invalid Refresh Token

```bash
curl -X POST http://localhost/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refreshToken": "invalid-token"
  }'
```

**Expected Response** (401):
```json
{
  "success": false,
  "message": "Invalid or expired refresh token"
}
```

---

### 4. Logout

**Endpoint**: `POST /api/auth/logout`
**Authentication**: Required (Bearer token)

#### Test 4.1: Successful Logout

```bash
# Use accessToken from login
curl -X POST http://localhost/api/auth/logout \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "Logged out successfully",
  "data": null
}
```

#### Test 4.2: Logout Without Token

```bash
curl -X POST http://localhost/api/auth/logout \
  -H "Content-Type: application/json"
```

**Expected Response** (401):
```json
{
  "success": false,
  "message": "No token provided"
}
```

#### Test 4.3: Use Blacklisted Token

```bash
# Try to use the token that was just logged out
curl -X GET http://localhost/api/auth/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc... (logged out token)"
```

**Expected Response** (401):
```json
{
  "success": false,
  "message": "Token has been revoked. Please login again."
}
```

---

### 5. Get Current User Profile

**Endpoint**: `GET /api/auth/me`
**Authentication**: Required

#### Test 5.1: Get Profile Success

```bash
# Login again to get fresh token
# Then use the accessToken
curl -X GET http://localhost/api/auth/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "User profile retrieved successfully",
  "data": {
    "user": {
      "id": 2,
      "name": "Test Student",
      "email": "student@test.com",
      "role": "student",
      "is_active": true,
      "profile_picture": null,
      "bio": null,
      "school": null,
      "grade": null,
      "created_at": "2025-11-10 10:30:00",
      "last_login": "2025-11-10 10:35:00"
    },
    "statistics": {
      "enrollments": 0,
      "completed_lessons": 0,
      "quiz_attempts": 0,
      "average_quiz_score": 0,
      "certificates": 0
    }
  }
}
```

#### Test 5.2: No Token Provided

```bash
curl -X GET http://localhost/api/auth/me
```

**Expected Response** (401):
```json
{
  "success": false,
  "message": "Authentication required"
}
```

---

## User Management Endpoints

### 6. List Users

**Endpoint**: `GET /api/users`
**Authentication**: Required (Admin or Instructor)

#### Test 6.1: List Users as Admin

```bash
# Use admin accessToken
curl -X GET "http://localhost/api/users?page=1&pageSize=20" \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "System Administrator",
        "email": "admin@sci-bono.org",
        "role": "admin",
        "is_active": true,
        "created_at": "2025-10-28 23:17:00"
      },
      {
        "id": 2,
        "name": "Test Student",
        "email": "student@test.com",
        "role": "student",
        "is_active": true,
        "created_at": "2025-11-10 10:30:00"
      }
    ],
    "pagination": {
      "total": 2,
      "page": 1,
      "pageSize": 20,
      "totalPages": 1,
      "hasNext": false,
      "hasPrev": false
    }
  }
}
```

#### Test 6.2: List Users as Student (Forbidden)

```bash
# Use student accessToken
curl -X GET "http://localhost/api/users" \
  -H "Authorization: Bearer <STUDENT_ACCESS_TOKEN>"
```

**Expected Response** (403 Forbidden):
```json
{
  "success": false,
  "message": "You do not have permission to perform this action"
}
```

#### Test 6.3: Filter by Role

```bash
curl -X GET "http://localhost/api/users?role=student" \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

#### Test 6.4: Search Users

```bash
curl -X GET "http://localhost/api/users?search=test" \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

---

### 7. View User Profile

**Endpoint**: `GET /api/users/:id`
**Authentication**: Required (Admin, Instructor, or Self)

#### Test 7.1: View Own Profile

```bash
# Use student token to view own profile (ID 2)
curl -X GET http://localhost/api/users/2 \
  -H "Authorization: Bearer <STUDENT_ACCESS_TOKEN>"
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "user": {
      "id": 2,
      "name": "Test Student",
      "email": "student@test.com",
      "role": "student",
      "is_active": true
    },
    "statistics": {
      "enrollments": 0,
      "completed_lessons": 0,
      "quiz_attempts": 0,
      "average_quiz_score": 0,
      "certificates": 0
    }
  }
}
```

#### Test 7.2: View Another User as Admin

```bash
curl -X GET http://localhost/api/users/2 \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

**Expected**: 200 OK

#### Test 7.3: View Another User as Student (Forbidden)

```bash
# Student trying to view admin profile
curl -X GET http://localhost/api/users/1 \
  -H "Authorization: Bearer <STUDENT_ACCESS_TOKEN>"
```

**Expected Response** (403):
```json
{
  "success": false,
  "message": "You do not have permission to view this user"
}
```

#### Test 7.4: View Non-existent User

```bash
curl -X GET http://localhost/api/users/9999 \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

**Expected Response** (404):
```json
{
  "success": false,
  "message": "User not found"
}
```

---

### 8. Update User Profile

**Endpoint**: `PUT /api/users/:id`
**Authentication**: Required (Admin or Self)

#### Test 8.1: Update Own Profile

```bash
curl -X PUT http://localhost/api/users/2 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <STUDENT_ACCESS_TOKEN>" \
  -d '{
    "name": "Updated Student Name",
    "bio": "I am learning AI and loving it!",
    "school": "Sci-Bono High School",
    "grade": "10"
  }'
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "User profile updated successfully",
  "data": {
    "user": {
      "id": 2,
      "name": "Updated Student Name",
      "email": "student@test.com",
      "role": "student",
      "bio": "I am learning AI and loving it!",
      "school": "Sci-Bono High School",
      "grade": "10",
      "is_active": true
    }
  }
}
```

#### Test 8.2: Update Another User as Admin

```bash
curl -X PUT http://localhost/api/users/2 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>" \
  -d '{
    "name": "Admin Updated Name"
  }'
```

**Expected**: 200 OK

#### Test 8.3: Update Another User as Student (Forbidden)

```bash
curl -X PUT http://localhost/api/users/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <STUDENT_ACCESS_TOKEN>" \
  -d '{
    "name": "Trying to hack admin"
  }'
```

**Expected Response** (403):
```json
{
  "success": false,
  "message": "You do not have permission to access this resource"
}
```

---

### 9. Delete User

**Endpoint**: `DELETE /api/users/:id`
**Authentication**: Required (Admin only)

#### Test 9.1: Delete User as Admin

```bash
# First, create a user to delete
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "User To Delete",
    "email": "delete@test.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
  }'

# Note the user ID, then delete
curl -X DELETE http://localhost/api/users/3 \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "message": "User deleted successfully",
  "data": {
    "deleted_user_id": 3,
    "deleted_user_email": "delete@test.com"
  }
}
```

#### Test 9.2: Delete User as Student (Forbidden)

```bash
curl -X DELETE http://localhost/api/users/1 \
  -H "Authorization: Bearer <STUDENT_ACCESS_TOKEN>"
```

**Expected Response** (403):
```json
{
  "success": false,
  "message": "You do not have permission to perform this action"
}
```

#### Test 9.3: Admin Cannot Delete Self

```bash
# Admin trying to delete own account
curl -X DELETE http://localhost/api/users/1 \
  -H "Authorization: Bearer <ADMIN_ACCESS_TOKEN>"
```

**Expected Response** (400):
```json
{
  "success": false,
  "message": "You cannot delete your own account"
}
```

---

## Testing Checklist

### Authentication Flow ✓

- [ ] User can register with valid data
- [ ] Registration validates email uniqueness
- [ ] Registration validates password strength
- [ ] Registration validates password confirmation
- [ ] User can login with correct credentials
- [ ] Login fails with wrong email
- [ ] Login fails with wrong password
- [ ] Login fails for inactive accounts
- [ ] Access token can be refreshed with refresh token
- [ ] Refresh fails with invalid token
- [ ] User can logout and token is blacklisted
- [ ] Blacklisted token cannot be used
- [ ] User can retrieve own profile with valid token
- [ ] Profile retrieval fails without token

### User Management Flow ✓

- [ ] Admin can list all users with pagination
- [ ] Instructor can list users
- [ ] Student cannot list users
- [ ] Users can be filtered by role
- [ ] Users can be searched by name/email
- [ ] User can view own profile
- [ ] Admin can view any user profile
- [ ] Student cannot view other profiles
- [ ] Non-existent users return 404
- [ ] User can update own profile
- [ ] Admin can update any profile
- [ ] Student cannot update other profiles
- [ ] Admin can delete users
- [ ] Student cannot delete users
- [ ] Admin cannot delete self

---

## Troubleshooting

### Issue: "Controller not found"

**Cause**: Routing not finding controllers
**Solution**: Verify files exist in `/api/controllers/` and have correct namespace

### Issue: "Database connection failed"

**Cause**: PDO not initialized
**Solution**: Check `/api/config/database.php` and `.env` file

### Issue: "Invalid or expired token"

**Cause**: Token expired or JWT_SECRET changed
**Solution**: Login again to get fresh token

### Issue: "Token has been revoked"

**Cause**: Token was blacklisted via logout
**Solution**: Login again

### Issue: "Validation failed" errors

**Cause**: Input doesn't meet validation rules
**Solution**: Check error messages and fix input

---

## Next Steps

Once manual testing is complete:

1. **Create Unit Tests**: Automated PHPUnit tests for all controller methods
2. **Create Postman Collection**: Import these tests into Postman for easier testing
3. **Implement Remaining Controllers**: CourseController, ModuleController, etc.
4. **Frontend Integration**: Connect login.html and signup.html to API

---

**Testing Complete**: Mark as ✓ when all tests pass
**Issues Found**: Document in GitHub Issues
**Performance**: Note any slow responses (>200ms)
