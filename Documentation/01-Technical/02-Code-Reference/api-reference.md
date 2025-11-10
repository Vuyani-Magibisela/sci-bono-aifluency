# API Reference Documentation

**Version**: 1.0.0
**Last Updated**: 2025-11-04
**Base URL**: `/api`
**Environment**: Development

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Response Format](#response-format)
4. [Error Codes](#error-codes)
5. [Rate Limiting](#rate-limiting)
6. [API Endpoints](#api-endpoints)
   - [Authentication](#authentication-endpoints)
   - [Users](#user-endpoints)
   - [Courses](#course-endpoints)
   - [Modules](#module-endpoints)
   - [Lessons](#lesson-endpoints)
   - [Quizzes](#quiz-endpoints)
   - [Progress](#progress-endpoints)
   - [Enrollments](#enrollment-endpoints)
   - [Certificates](#certificate-endpoints)

---

## Overview

The Sci-Bono AI Fluency LMS API is a RESTful API that provides programmatic access to course content, user management, progress tracking, and certification features.

### Key Features

- **JWT Authentication**: Secure token-based authentication
- **Role-Based Access Control**: Student, Instructor, and Admin roles
- **RESTful Design**: Standard HTTP methods (GET, POST, PUT, DELETE)
- **JSON Responses**: All responses in JSON format
- **CORS Support**: Configurable cross-origin resource sharing
- **Rate Limiting**: Protects against abuse

### Base URL

```
Development: http://localhost/api
Production: https://aifluency.sci-bono.org/api
```

### Content Types

All requests and responses use `application/json` content type.

---

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Most endpoints require a valid access token.

### Token Types

1. **Access Token**: Short-lived token (1 hour) for API access
2. **Refresh Token**: Long-lived token (7 days) for obtaining new access tokens

### Authentication Header

Include the access token in the `Authorization` header:

```
Authorization: Bearer <access_token>
```

### Token Lifecycle

1. User logs in → receives access token and refresh token
2. Access token expires after 1 hour
3. Use refresh token to get new access token
4. Refresh token expires after 7 days → user must log in again

---

## Response Format

### Success Response

```json
{
  "success": true,
  "message": "Success message",
  "data": {
    // Response data
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field_name": "Specific error for this field"
  }
}
```

### Paginated Response

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "items": [],
    "pagination": {
      "total": 100,
      "page": 1,
      "pageSize": 20,
      "totalPages": 5,
      "hasNext": true,
      "hasPrev": false
    }
  }
}
```

---

## Error Codes

| HTTP Code | Meaning |
|-----------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Authentication required |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource doesn't exist |
| 422 | Validation Error - Invalid field values |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error |

---

## Rate Limiting

**Default Limits**: 100 requests per minute per IP address

When rate limit is exceeded:
- HTTP Status: `429 Too Many Requests`
- Response: `{"success": false, "message": "Too many requests. Please try again later."}`

---

## API Endpoints

---

## Authentication Endpoints

### 1. Register User

Create a new user account.

**Endpoint**: `POST /auth/register`
**Authentication**: Not required
**Roles**: Public

#### Request Body

```json
{
  "name": "John Doe",
  "email": "john.doe@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role": "student"
}
```

#### Request Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | User's full name (max 255 chars) |
| email | string | Yes | Valid email address (must be unique) |
| password | string | Yes | Strong password (min 8 chars, uppercase, lowercase, number, special char) |
| password_confirmation | string | Yes | Must match password |
| role | string | No | Default: "student". Options: student, instructor |

#### Success Response (201 Created)

```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 2,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "role": "student",
      "is_active": true,
      "created_at": "2025-11-04 16:30:00"
    },
    "tokens": {
      "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
      "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
      "expiresIn": 3600
    }
  }
}
```

#### Error Responses

**Validation Error (422)**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "The email has already been taken",
    "password": "Password must contain at least one uppercase letter"
  }
}
```

#### cURL Example

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role": "student"
  }'
```

---

### 2. Login

Authenticate a user and receive access tokens.

**Endpoint**: `POST /auth/login`
**Authentication**: Not required
**Roles**: Public

#### Request Body

```json
{
  "email": "john.doe@example.com",
  "password": "SecurePass123!"
}
```

#### Request Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | Yes | User's email address |
| password | string | Yes | User's password |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 2,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "role": "student",
      "is_active": true,
      "last_login": "2025-11-04 16:35:00"
    },
    "tokens": {
      "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
      "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
      "expiresIn": 3600
    }
  }
}
```

#### Error Responses

**Invalid Credentials (401)**
```json
{
  "success": false,
  "message": "Invalid email or password"
}
```

**Account Inactive (403)**
```json
{
  "success": false,
  "message": "Account has been deactivated. Please contact support."
}
```

#### cURL Example

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john.doe@example.com",
    "password": "SecurePass123!"
  }'
```

---

### 3. Refresh Token

Obtain a new access token using a refresh token.

**Endpoint**: `POST /auth/refresh`
**Authentication**: Not required (uses refresh token)
**Roles**: Public

#### Request Body

```json
{
  "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "tokens": {
      "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
      "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
      "expiresIn": 3600
    }
  }
}
```

#### Error Responses

**Invalid Token (401)**
```json
{
  "success": false,
  "message": "Invalid or expired refresh token"
}
```

#### cURL Example

```bash
curl -X POST http://localhost/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }'
```

---

### 4. Logout

Invalidate current access token (blacklist).

**Endpoint**: `POST /auth/logout`
**Authentication**: Required
**Roles**: All authenticated users

#### Request Headers

```
Authorization: Bearer <access_token>
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

#### cURL Example

```bash
curl -X POST http://localhost/api/auth/logout \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

### 5. Get Current User

Retrieve authenticated user's profile.

**Endpoint**: `GET /auth/me`
**Authentication**: Required
**Roles**: All authenticated users

#### Request Headers

```
Authorization: Bearer <access_token>
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "user": {
      "id": 2,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "role": "student",
      "is_active": true,
      "profile_picture": null,
      "bio": null,
      "school": "Sci-Bono High School",
      "grade": "10",
      "created_at": "2025-11-04 16:30:00",
      "last_login": "2025-11-04 16:35:00"
    },
    "statistics": {
      "enrollments": 2,
      "completed_lessons": 15,
      "quiz_attempts": 8,
      "average_quiz_score": 85.5,
      "certificates": 1
    }
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/auth/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

## User Endpoints

### 6. List Users

Retrieve a paginated list of users.

**Endpoint**: `GET /users`
**Authentication**: Required
**Roles**: Admin, Instructor

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| page | integer | No | Page number (default: 1) |
| pageSize | integer | No | Items per page (default: 20, max: 100) |
| role | string | No | Filter by role (student, instructor, admin) |
| search | string | No | Search by name or email |

#### Request Example

```
GET /users?page=1&pageSize=20&role=student&search=john
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": {
    "items": [
      {
        "id": 2,
        "name": "John Doe",
        "email": "john.doe@example.com",
        "role": "student",
        "is_active": true,
        "school": "Sci-Bono High School",
        "grade": "10",
        "created_at": "2025-11-04 16:30:00",
        "last_login": "2025-11-04 16:35:00"
      }
    ],
    "pagination": {
      "total": 45,
      "page": 1,
      "pageSize": 20,
      "totalPages": 3,
      "hasNext": true,
      "hasPrev": false
    }
  }
}
```

#### cURL Example

```bash
curl -X GET "http://localhost/api/users?page=1&pageSize=20&role=student" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

### 7. Get User by ID

Retrieve a specific user's profile.

**Endpoint**: `GET /users/:id`
**Authentication**: Required
**Roles**: Admin, Instructor, or the user themselves

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | User ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "user": {
      "id": 2,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "role": "student",
      "is_active": true,
      "profile_picture": "/uploads/profiles/2.jpg",
      "bio": "Passionate about AI and machine learning",
      "school": "Sci-Bono High School",
      "grade": "10",
      "created_at": "2025-11-04 16:30:00",
      "last_login": "2025-11-04 16:35:00"
    },
    "statistics": {
      "enrollments": 2,
      "completed_lessons": 15,
      "quiz_attempts": 8,
      "average_quiz_score": 85.5,
      "certificates": 1
    }
  }
}
```

#### Error Responses

**Not Found (404)**
```json
{
  "success": false,
  "message": "User not found"
}
```

**Forbidden (403)**
```json
{
  "success": false,
  "message": "You do not have permission to view this user"
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/users/2 \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

### 8. Update User

Update a user's profile information.

**Endpoint**: `PUT /users/:id`
**Authentication**: Required
**Roles**: Admin, or the user themselves

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | User ID |

#### Request Body

```json
{
  "name": "John Smith",
  "bio": "Updated bio",
  "school": "New School Name",
  "grade": "11",
  "profile_picture": "/uploads/profiles/new.jpg"
}
```

#### Request Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | No | User's full name |
| bio | string | No | User biography |
| school | string | No | School name |
| grade | string | No | Grade level |
| profile_picture | string | No | Profile picture URL |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "user": {
      "id": 2,
      "name": "John Smith",
      "email": "john.doe@example.com",
      "role": "student",
      "is_active": true,
      "profile_picture": "/uploads/profiles/new.jpg",
      "bio": "Updated bio",
      "school": "New School Name",
      "grade": "11",
      "created_at": "2025-11-04 16:30:00",
      "last_login": "2025-11-04 16:35:00"
    }
  }
}
```

#### cURL Example

```bash
curl -X PUT http://localhost/api/users/2 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -d '{
    "name": "John Smith",
    "bio": "Updated bio",
    "school": "New School Name",
    "grade": "11"
  }'
```

---

### 9. Delete User

Delete a user account (Admin only).

**Endpoint**: `DELETE /users/:id`
**Authentication**: Required
**Roles**: Admin only

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | User ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

#### Error Responses

**Cannot Delete Self (400)**
```json
{
  "success": false,
  "message": "You cannot delete your own account"
}
```

#### cURL Example

```bash
curl -X DELETE http://localhost/api/users/2 \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

## Course Endpoints

### 10. List Courses

Retrieve all available courses.

**Endpoint**: `GET /courses`
**Authentication**: Not required
**Roles**: Public

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| page | integer | No | Page number (default: 1) |
| pageSize | integer | No | Items per page (default: 20) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Courses retrieved successfully",
  "data": {
    "items": [
      {
        "id": 1,
        "title": "AI Fluency Course",
        "slug": "ai-fluency",
        "description": "Comprehensive AI literacy course for grades 8-12",
        "duration_hours": 40,
        "difficulty": "beginner",
        "thumbnail": "/images/course-thumbnail.jpg",
        "is_active": true,
        "modules_count": 6,
        "lessons_count": 44,
        "enrolled_students": 250,
        "created_at": "2025-10-28 23:17:00"
      }
    ],
    "pagination": {
      "total": 1,
      "page": 1,
      "pageSize": 20,
      "totalPages": 1,
      "hasNext": false,
      "hasPrev": false
    }
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/courses
```

---

### 11. Get Course by ID

Retrieve detailed information about a specific course.

**Endpoint**: `GET /courses/:id`
**Authentication**: Not required
**Roles**: Public

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Course ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Course retrieved successfully",
  "data": {
    "course": {
      "id": 1,
      "title": "AI Fluency Course",
      "slug": "ai-fluency",
      "description": "Comprehensive AI literacy course for grades 8-12",
      "duration_hours": 40,
      "difficulty": "beginner",
      "thumbnail": "/images/course-thumbnail.jpg",
      "is_active": true,
      "created_at": "2025-10-28 23:17:00"
    },
    "modules": [
      {
        "id": 1,
        "title": "AI Foundations",
        "order_index": 1,
        "lessons_count": 11
      },
      {
        "id": 2,
        "title": "Generative AI",
        "order_index": 2,
        "lessons_count": 8
      }
    ],
    "statistics": {
      "enrolled_students": 250,
      "completed_students": 78,
      "average_completion_rate": 65.5
    }
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/courses/1
```

---

### 12. Create Course

Create a new course (Admin/Instructor only).

**Endpoint**: `POST /courses`
**Authentication**: Required
**Roles**: Admin, Instructor

#### Request Body

```json
{
  "title": "Advanced AI Ethics",
  "slug": "advanced-ai-ethics",
  "description": "Deep dive into ethical considerations in AI",
  "duration_hours": 30,
  "difficulty": "advanced",
  "thumbnail": "/images/ethics-course.jpg"
}
```

#### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Course created successfully",
  "data": {
    "course": {
      "id": 2,
      "title": "Advanced AI Ethics",
      "slug": "advanced-ai-ethics",
      "description": "Deep dive into ethical considerations in AI",
      "duration_hours": 30,
      "difficulty": "advanced",
      "thumbnail": "/images/ethics-course.jpg",
      "is_active": true,
      "created_at": "2025-11-04 17:00:00"
    }
  }
}
```

#### cURL Example

```bash
curl -X POST http://localhost/api/courses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -d '{
    "title": "Advanced AI Ethics",
    "slug": "advanced-ai-ethics",
    "description": "Deep dive into ethical considerations in AI",
    "duration_hours": 30,
    "difficulty": "advanced"
  }'
```

---

### 13. Update Course

Update an existing course.

**Endpoint**: `PUT /courses/:id`
**Authentication**: Required
**Roles**: Admin, Instructor

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Course ID |

#### Request Body

```json
{
  "title": "Updated Course Title",
  "description": "Updated description",
  "is_active": false
}
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Course updated successfully",
  "data": {
    "course": {
      "id": 1,
      "title": "Updated Course Title",
      "description": "Updated description",
      "is_active": false
    }
  }
}
```

#### cURL Example

```bash
curl -X PUT http://localhost/api/courses/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -d '{
    "title": "Updated Course Title",
    "is_active": false
  }'
```

---

## Module Endpoints

### 14. List Modules by Course

Retrieve all modules for a specific course.

**Endpoint**: `GET /courses/:courseId/modules`
**Authentication**: Not required
**Roles**: Public

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| courseId | integer | Course ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Modules retrieved successfully",
  "data": {
    "items": [
      {
        "id": 1,
        "course_id": 1,
        "title": "AI Foundations",
        "slug": "ai-foundations",
        "description": "Introduction to artificial intelligence concepts",
        "order_index": 1,
        "lessons_count": 11,
        "estimated_duration": "8 hours"
      },
      {
        "id": 2,
        "course_id": 1,
        "title": "Generative AI",
        "slug": "generative-ai",
        "description": "Understanding generative AI models",
        "order_index": 2,
        "lessons_count": 8,
        "estimated_duration": "6 hours"
      }
    ]
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/courses/1/modules
```

---

### 15. Get Module by ID

Retrieve detailed information about a specific module.

**Endpoint**: `GET /modules/:id`
**Authentication**: Not required
**Roles**: Public

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Module ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Module retrieved successfully",
  "data": {
    "module": {
      "id": 1,
      "course_id": 1,
      "title": "AI Foundations",
      "slug": "ai-foundations",
      "description": "Introduction to artificial intelligence concepts",
      "order_index": 1,
      "created_at": "2025-10-28 23:17:00"
    },
    "lessons": [
      {
        "id": 1,
        "title": "What is AI?",
        "slug": "what-is-ai",
        "order_index": 1,
        "estimated_duration": "30 minutes"
      }
    ],
    "quiz": {
      "id": 1,
      "title": "AI Foundations Quiz",
      "questions_count": 10,
      "passing_score": 70
    }
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/modules/1
```

---

## Lesson Endpoints

### 16. List Lessons by Module

Retrieve all lessons for a specific module.

**Endpoint**: `GET /modules/:moduleId/lessons`
**Authentication**: Not required
**Roles**: Public

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| moduleId | integer | Module ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Lessons retrieved successfully",
  "data": {
    "items": [
      {
        "id": 1,
        "module_id": 1,
        "title": "What is AI?",
        "slug": "what-is-ai",
        "order_index": 1,
        "estimated_duration": "30 minutes",
        "content_preview": "Artificial Intelligence (AI) is...",
        "has_video": false,
        "has_interactive": true
      }
    ]
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/modules/1/lessons
```

---

### 17. Get Lesson by ID

Retrieve full lesson content.

**Endpoint**: `GET /lessons/:id`
**Authentication**: Not required
**Roles**: Public

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Lesson ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Lesson retrieved successfully",
  "data": {
    "lesson": {
      "id": 1,
      "module_id": 1,
      "title": "What is AI?",
      "slug": "what-is-ai",
      "content": "<div class='chapter-content'>...</div>",
      "order_index": 1,
      "estimated_duration": "30 minutes",
      "learning_objectives": [
        "Define artificial intelligence",
        "Understand basic AI concepts"
      ],
      "created_at": "2025-10-28 23:17:00"
    },
    "navigation": {
      "previous": null,
      "next": {
        "id": 2,
        "title": "History of AI",
        "slug": "history-of-ai"
      }
    }
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/lessons/1
```

---

## Quiz Endpoints

### 18. Get Quiz by Module

Retrieve quiz for a specific module.

**Endpoint**: `GET /modules/:moduleId/quiz`
**Authentication**: Required
**Roles**: All authenticated users

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| moduleId | integer | Module ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Quiz retrieved successfully",
  "data": {
    "quiz": {
      "id": 1,
      "module_id": 1,
      "title": "AI Foundations Quiz",
      "description": "Test your knowledge of AI fundamentals",
      "time_limit": 30,
      "passing_score": 70,
      "attempts_allowed": 3,
      "questions_count": 10
    },
    "questions": [
      {
        "id": 1,
        "question": "What does AI stand for?",
        "options": [
          "Artificial Intelligence",
          "Automated Integration",
          "Advanced Interface",
          "Applied Information"
        ],
        "order_index": 1
      }
    ],
    "user_attempts": {
      "attempts_taken": 1,
      "attempts_remaining": 2,
      "best_score": 80,
      "last_attempt": "2025-11-03 14:30:00"
    }
  }
}
```

**Note**: Correct answers are NOT included in the response. They are only revealed after submission.

#### cURL Example

```bash
curl -X GET http://localhost/api/modules/1/quiz \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

### 19. Submit Quiz Attempt

Submit quiz answers and receive results.

**Endpoint**: `POST /quizzes/:id/submit`
**Authentication**: Required
**Roles**: All authenticated users

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Quiz ID |

#### Request Body

```json
{
  "answers": {
    "1": 0,
    "2": 2,
    "3": 1,
    "4": 3,
    "5": 0,
    "6": 1,
    "7": 2,
    "8": 0,
    "9": 3,
    "10": 1
  }
}
```

**Format**: `{ "question_id": selected_option_index }`

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Quiz submitted successfully",
  "data": {
    "attempt": {
      "id": 15,
      "quiz_id": 1,
      "user_id": 2,
      "score": 80,
      "passed": true,
      "time_taken": 1240,
      "submitted_at": "2025-11-04 17:30:00"
    },
    "results": {
      "total_questions": 10,
      "correct_answers": 8,
      "incorrect_answers": 2,
      "percentage": 80,
      "passing_score": 70,
      "passed": true
    },
    "question_results": [
      {
        "question_id": 1,
        "question": "What does AI stand for?",
        "user_answer": 0,
        "correct_answer": 0,
        "is_correct": true,
        "explanation": "AI stands for Artificial Intelligence..."
      },
      {
        "question_id": 2,
        "question": "Which year was the term AI coined?",
        "user_answer": 2,
        "correct_answer": 1,
        "is_correct": false,
        "explanation": "The term was coined in 1956..."
      }
    ]
  }
}
```

#### Error Responses

**No Attempts Remaining (400)**
```json
{
  "success": false,
  "message": "You have no remaining attempts for this quiz"
}
```

#### cURL Example

```bash
curl -X POST http://localhost/api/quizzes/1/submit \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -d '{
    "answers": {
      "1": 0,
      "2": 2,
      "3": 1
    }
  }'
```

---

## Progress Endpoints

### 20. Get User Progress

Retrieve authenticated user's overall progress.

**Endpoint**: `GET /progress`
**Authentication**: Required
**Roles**: All authenticated users

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Progress retrieved successfully",
  "data": {
    "overall": {
      "courses_enrolled": 2,
      "lessons_completed": 15,
      "lessons_in_progress": 3,
      "quizzes_passed": 5,
      "certificates_earned": 1,
      "total_time_spent": 28800,
      "average_quiz_score": 85.5
    },
    "courses": [
      {
        "course_id": 1,
        "course_title": "AI Fluency Course",
        "enrollment_date": "2025-10-15 09:00:00",
        "progress_percentage": 34,
        "lessons_completed": 15,
        "lessons_total": 44,
        "last_accessed": "2025-11-04 16:00:00"
      }
    ]
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/progress \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

### 21. Update Lesson Progress

Mark a lesson as complete or update progress.

**Endpoint**: `POST /lessons/:id/progress`
**Authentication**: Required
**Roles**: All authenticated users

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Lesson ID |

#### Request Body

```json
{
  "completed": true,
  "time_spent": 1800
}
```

#### Request Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| completed | boolean | Yes | Mark lesson as completed |
| time_spent | integer | No | Time spent in seconds |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Progress updated successfully",
  "data": {
    "lesson_progress": {
      "lesson_id": 1,
      "user_id": 2,
      "completed": true,
      "time_spent": 1800,
      "completed_at": "2025-11-04 17:45:00"
    },
    "module_progress": {
      "module_id": 1,
      "lessons_completed": 5,
      "lessons_total": 11,
      "progress_percentage": 45
    }
  }
}
```

#### cURL Example

```bash
curl -X POST http://localhost/api/lessons/1/progress \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -d '{
    "completed": true,
    "time_spent": 1800
  }'
```

---

### 22. Get Course Progress

Retrieve detailed progress for a specific course.

**Endpoint**: `GET /courses/:id/progress`
**Authentication**: Required
**Roles**: All authenticated users

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Course ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Course progress retrieved successfully",
  "data": {
    "course": {
      "id": 1,
      "title": "AI Fluency Course",
      "enrollment_date": "2025-10-15 09:00:00"
    },
    "progress": {
      "overall_percentage": 34,
      "lessons_completed": 15,
      "lessons_total": 44,
      "time_spent": 28800
    },
    "modules": [
      {
        "module_id": 1,
        "module_title": "AI Foundations",
        "progress_percentage": 45,
        "lessons_completed": 5,
        "lessons_total": 11,
        "quiz_score": 80,
        "quiz_passed": true
      }
    ]
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/courses/1/progress \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

## Enrollment Endpoints

### 23. Enroll in Course

Enroll authenticated user in a course.

**Endpoint**: `POST /courses/:id/enroll`
**Authentication**: Required
**Roles**: All authenticated users

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Course ID |

#### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Enrolled successfully",
  "data": {
    "enrollment": {
      "id": 25,
      "user_id": 2,
      "course_id": 1,
      "enrollment_date": "2025-11-04 18:00:00",
      "status": "active"
    }
  }
}
```

#### Error Responses

**Already Enrolled (400)**
```json
{
  "success": false,
  "message": "You are already enrolled in this course"
}
```

#### cURL Example

```bash
curl -X POST http://localhost/api/courses/1/enroll \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

### 24. List User Enrollments

Retrieve all enrollments for authenticated user.

**Endpoint**: `GET /enrollments`
**Authentication**: Required
**Roles**: All authenticated users

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| status | string | No | Filter by status (active, completed, dropped) |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Enrollments retrieved successfully",
  "data": {
    "items": [
      {
        "id": 25,
        "course_id": 1,
        "course_title": "AI Fluency Course",
        "course_thumbnail": "/images/course-thumbnail.jpg",
        "enrollment_date": "2025-10-15 09:00:00",
        "status": "active",
        "progress_percentage": 34,
        "last_accessed": "2025-11-04 16:00:00"
      }
    ]
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/enrollments \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

## Certificate Endpoints

### 25. List User Certificates

Retrieve all certificates earned by authenticated user.

**Endpoint**: `GET /certificates`
**Authentication**: Required
**Roles**: All authenticated users

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Certificates retrieved successfully",
  "data": {
    "items": [
      {
        "id": 8,
        "user_id": 2,
        "course_id": 1,
        "course_title": "AI Fluency Course",
        "certificate_number": "SCIBONO-2025-000008",
        "verification_code": "ABC123XYZ789",
        "issued_date": "2025-11-01 15:30:00",
        "download_url": "/api/certificates/8/download"
      }
    ]
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/certificates \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

### 26. Get Certificate by ID

Retrieve a specific certificate.

**Endpoint**: `GET /certificates/:id`
**Authentication**: Required
**Roles**: Certificate owner, or Admin/Instructor

#### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Certificate ID |

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Certificate retrieved successfully",
  "data": {
    "certificate": {
      "id": 8,
      "user_id": 2,
      "user_name": "John Doe",
      "course_id": 1,
      "course_title": "AI Fluency Course",
      "certificate_number": "SCIBONO-2025-000008",
      "verification_code": "ABC123XYZ789",
      "issued_date": "2025-11-01 15:30:00",
      "completion_date": "2025-11-01 14:00:00",
      "final_score": 88.5,
      "download_url": "/api/certificates/8/download",
      "verification_url": "/verify/ABC123XYZ789"
    }
  }
}
```

#### cURL Example

```bash
curl -X GET http://localhost/api/certificates/8 \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

---

## Additional Notes

### CORS Configuration

The API supports CORS for specific origins configured in `/api/config/config.php`:

```php
define('CORS_ALLOWED_ORIGINS', [
    'https://aifluency.sci-bono.org',
    'https://www.sci-bono.org'
]);
```

In development mode (`APP_DEBUG=true`), localhost origins are automatically allowed.

### Pagination

All list endpoints support pagination with these default values:
- Default page: 1
- Default pageSize: 20
- Maximum pageSize: 100

### Date Formats

All dates are returned in `Y-m-d H:i:s` format (e.g., `2025-11-04 18:00:00`).

### File Uploads

File upload endpoints (profile pictures, course thumbnails) accept:
- Maximum file size: 5MB
- Allowed types: JPEG, PNG, GIF
- Files stored in `/uploads/` directory

### Security Headers

All responses include these security headers:
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection: 1; mode=block`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`

---

## Implementation Status

**Status**: ✅ Infrastructure Complete (Routing, Models, Utilities)
**Controllers**: ⏳ Pending Implementation
**Testing**: ⏳ Pending
**Deployment**: ⏳ Pending

All endpoints are defined in `/api/routes/api.php` and the routing system is fully functional. Controllers need to be implemented to handle the business logic for each endpoint.

---

**Document Version**: 1.0.0
**Last Updated**: 2025-11-04
**Maintained By**: Development Team
**Related Documentation**:
- [PHP API Code Reference](/Documentation/01-Technical/02-Code-Reference/php-api-code-reference.md)
- [Database Schema](/Documentation/01-Technical/03-Database/schema-design.md)
- [Setup Guide](/Documentation/01-Technical/04-Development/setup-guide.md)
