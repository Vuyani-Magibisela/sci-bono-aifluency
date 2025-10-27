# Database Schema Design - Sci-Bono AI Fluency LMS

**Document Version:** 1.0
**Last Updated:** 2025-10-27
**Author:** Development Team
**Status:** Design Phase

---

## Table of Contents

1. [Introduction](#introduction)
2. [Database Overview](#database-overview)
3. [Naming Conventions](#naming-conventions)
4. [Core Tables](#core-tables)
5. [Table Definitions](#table-definitions)
6. [Relationships & Foreign Keys](#relationships--foreign-keys)
7. [Indexes & Performance](#indexes--performance)
8. [Data Types & Constraints](#data-types--constraints)
9. [Sample Data](#sample-data)
10. [Migration Scripts](#migration-scripts)
11. [Security Considerations](#security-considerations)
12. [Backup & Maintenance](#backup--maintenance)
13. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This document provides the complete database schema design for the Sci-Bono AI Fluency Learning Management System. It defines all tables, relationships, constraints, and indexes required to support the platform's functionality.

### Scope

**Covered in this document:**
- Complete table definitions with all columns
- Primary and foreign key relationships
- Indexes for query optimization
- Data types and constraints
- Sample data structures
- Migration scripts

**Related documents:**
- [Future Architecture](../01-Architecture/future-architecture.md) - Overall system design
- [Migration Roadmap](../01-Architecture/migration-roadmap.md) - Implementation timeline
- [Current Architecture](../01-Architecture/current-architecture.md) - Existing static structure

### Database Management System

**Selected DBMS:** MySQL 8.0+
**Character Set:** utf8mb4 (full Unicode support including emojis)
**Collation:** utf8mb4_unicode_ci (case-insensitive, accent-sensitive)
**Storage Engine:** InnoDB (supports transactions, foreign keys, row-level locking)

---

## Database Overview

### Entity Relationship Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                       DATABASE STRUCTURE                             │
└─────────────────────────────────────────────────────────────────────┘

USERS DOMAIN                   CONTENT DOMAIN              LEARNING DOMAIN
┌──────────┐                  ┌──────────┐                ┌─────────────┐
│  users   │                  │ courses  │                │ enrollments │
│  (core)  │                  │  (core)  │                │   (bridge)  │
└─────┬────┘                  └────┬─────┘                └──────┬──────┘
      │                            │                              │
      │                       ┌────┴─────┐                       │
      │                       │ modules  │                       │
      │                       │ (1-to-M) │                       │
      │                       └────┬─────┘                       │
      │                            │                              │
      │                       ┌────┴─────┐                       │
      │                       │ chapters │                  ┌────┴─────┐
      │                       │ (1-to-M) │                  │ progress │
      │                       └────┬─────┘                  │ (tracks) │
      │                            │                         └──────────┘
      │                            │
      │                       ┌────┴─────────┐
      │                       │   quizzes    │
      │                       │   (1-to-M)   │
      │                       └────┬─────────┘
      │                            │
      │                       ┌────┴──────────────┐
      │                       │  quiz_questions   │
      │                       │    (1-to-M)       │
      │                       └───────────────────┘
      │
      ├─────────────────────────────────────────────────┐
      │                                                  │
┌─────┴──────────┐                              ┌───────┴────────┐
│ quiz_attempts  │                              │ project_       │
│  (submissions) │                              │  submissions   │
└────────────────┘                              └────────────────┘
      │                                                  │
┌─────┴──────────┐                              ┌───────┴────────┐
│ quiz_attempt_  │                              │  certificates  │
│   answers      │                              │   (awards)     │
└────────────────┘                              └────────────────┘

SUPPORTING TABLES
┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  sessions    │  │ password_    │  │    logs      │  │   settings   │
│  (auth)      │  │  resets      │  │  (audit)     │  │  (config)    │
└──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘
```

### Table Categories

**Core Tables (10):**
1. `users` - User accounts and authentication
2. `courses` - Course definitions
3. `modules` - Course modules (e.g., Module 1: AI Foundations)
4. `chapters` - Individual lessons within modules
5. `quizzes` - Quiz definitions
6. `quiz_questions` - Questions for each quiz
7. `enrollments` - User course enrollments
8. `progress` - Chapter completion tracking
9. `quiz_attempts` - Quiz submission records
10. `quiz_attempt_answers` - Individual answer records

**Extended Tables (6):**
11. `project_submissions` - Project uploads and submissions
12. `certificates` - Awarded certificates
13. `sessions` - User session management
14. `password_resets` - Password reset tokens
15. `logs` - System activity logs
16. `settings` - Application configuration

**Total Tables:** 16

---

## Naming Conventions

### Table Names
- ✅ Lowercase, plural nouns: `users`, `courses`, `enrollments`
- ✅ Underscore for multi-word: `quiz_attempts`, `project_submissions`
- ✅ Descriptive and clear: `password_resets` not `pwd_rst`

### Column Names
- ✅ Lowercase with underscores: `created_at`, `user_id`
- ✅ Consistent naming patterns:
  - Primary keys: `id`
  - Foreign keys: `{table_singular}_id` (e.g., `user_id`, `course_id`)
  - Timestamps: `created_at`, `updated_at`, `completed_at`, `submitted_at`
  - Boolean fields: `is_active`, `completed`, `is_published`

### Indexes
- Primary keys: `PRIMARY`
- Foreign keys: `fk_{table}_{column}`
- Unique indexes: `uk_{table}_{column}` or `UNIQUE_{column}`
- Regular indexes: `idx_{table}_{column}` or `INDEX_{column}`

---

## Core Tables

### Summary Table

| Table Name | Primary Purpose | Est. Rows | Growth Rate |
|------------|-----------------|-----------|-------------|
| `users` | User accounts | 1K-100K | High |
| `courses` | Course catalog | 10-100 | Low |
| `modules` | Course modules | 50-500 | Low |
| `chapters` | Lesson content | 200-2K | Medium |
| `quizzes` | Quiz definitions | 50-500 | Low |
| `quiz_questions` | Quiz question bank | 500-5K | Medium |
| `enrollments` | User enrollments | 10K-1M | High |
| `progress` | Chapter tracking | 100K-10M | Very High |
| `quiz_attempts` | Quiz submissions | 50K-5M | Very High |
| `quiz_attempt_answers` | Answer records | 500K-50M | Very High |
| `project_submissions` | Project uploads | 10K-1M | High |
| `certificates` | Awarded certificates | 5K-500K | High |
| `sessions` | Active sessions | 1K-10K | High (volatile) |
| `password_resets` | Reset tokens | 100-1K | Medium (volatile) |
| `logs` | Activity logs | 100K-10M+ | Very High |
| `settings` | App configuration | 50-200 | Very Low |

---

## Table Definitions

### 1. users

**Purpose:** Stores user account information for students, instructors, and administrators.

```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identity
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    -- Profile
    avatar VARCHAR(500) NULL DEFAULT NULL,
    bio TEXT NULL,
    phone VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL,

    -- Location (optional)
    country VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,

    -- Role & Status
    role ENUM('student', 'instructor', 'admin') NOT NULL DEFAULT 'student',
    status ENUM('active', 'inactive', 'suspended', 'deleted') NOT NULL DEFAULT 'active',

    -- Verification
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    verification_token VARCHAR(100) NULL,

    -- Preferences
    language VARCHAR(10) NOT NULL DEFAULT 'en',
    timezone VARCHAR(50) NOT NULL DEFAULT 'Africa/Johannesburg',
    notifications_enabled BOOLEAN NOT NULL DEFAULT TRUE,

    -- Tracking
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    last_login_ip VARCHAR(45) NULL,
    login_count INT UNSIGNED NOT NULL DEFAULT 0,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Fields Explained:**
- `id`: Unique identifier for each user
- `email`: Login credential, must be unique
- `password_hash`: Bcrypt hashed password (using PHP's `password_hash()`)
- `role`: Determines access level (student/instructor/admin)
- `status`: Account state (active users can log in)
- `email_verified_at`: NULL until email is verified
- `deleted_at`: Soft delete timestamp (NULL if not deleted)

---

### 2. courses

**Purpose:** Defines available courses in the platform.

```sql
CREATE TABLE courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identity
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,

    -- Content
    description TEXT NOT NULL,
    long_description TEXT NULL,
    thumbnail VARCHAR(500) NULL,
    cover_image VARCHAR(500) NULL,

    -- Metadata
    partner VARCHAR(100) NULL COMMENT 'Microsoft, Intel, etc.',
    level ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
    duration_hours INT UNSIGNED NULL COMMENT 'Estimated hours to complete',
    language VARCHAR(10) NOT NULL DEFAULT 'en',

    -- Target Audience
    min_age INT UNSIGNED NULL DEFAULT 10,
    max_age INT UNSIGNED NULL DEFAULT 35,
    grade_level VARCHAR(50) NULL COMMENT 'e.g., Grades 8-12',

    -- Status & Publishing
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL DEFAULT NULL,

    -- Ordering
    display_order INT UNSIGNED NOT NULL DEFAULT 0,

    -- Enrollment Settings
    is_free BOOLEAN NOT NULL DEFAULT TRUE,
    price DECIMAL(10,2) NULL DEFAULT NULL,
    enrollment_limit INT UNSIGNED NULL COMMENT 'Max students, NULL = unlimited',
    requires_approval BOOLEAN NOT NULL DEFAULT FALSE,

    -- Certification
    certificate_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    certificate_template VARCHAR(255) NULL,

    -- Tracking
    total_enrollments INT UNSIGNED NOT NULL DEFAULT 0,
    total_completions INT UNSIGNED NOT NULL DEFAULT 0,
    average_rating DECIMAL(3,2) NULL DEFAULT NULL,

    -- Ownership
    created_by INT UNSIGNED NOT NULL COMMENT 'User ID of creator',

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_courses_created_by (created_by)
        REFERENCES users(id) ON DELETE RESTRICT,

    -- Indexes
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published_at (published_at),
    INDEX idx_display_order (display_order),
    INDEX idx_created_by (created_by)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Example Data Mapping:**
- **Current:** Single AI Fluency course
- **Future:** Multiple courses (AI Fluency, Python Programming, Data Science, etc.)

---

### 3. modules

**Purpose:** Organizes chapters into modules within a course.

```sql
CREATE TABLE modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    course_id INT UNSIGNED NOT NULL,

    -- Identity
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(255) NOT NULL,

    -- Content
    description TEXT NULL,
    icon VARCHAR(100) NULL COMMENT 'Icon identifier or emoji',
    color VARCHAR(20) NULL COMMENT 'Hex color for UI',

    -- Metadata
    duration_hours INT UNSIGNED NULL COMMENT 'Estimated hours',

    -- Ordering
    display_order INT UNSIGNED NOT NULL DEFAULT 0,

    -- Quiz Association
    has_quiz BOOLEAN NOT NULL DEFAULT TRUE,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_modules_course (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,

    -- Indexes
    UNIQUE KEY uk_course_slug (course_id, slug),
    INDEX idx_course_order (course_id, display_order)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Example Data Mapping:**
- Module 1: AI Foundations (`module1.html`)
- Module 2: Generative AI (`module2.html`)
- Module 3: Advanced Search (`module3.html`)
- Module 4: Responsible AI (`module4.html`)
- Module 5: Microsoft Copilot (`module5.html`)
- Module 6: AI Impact (`module6.html`)

---

### 4. chapters

**Purpose:** Individual lessons/chapters within modules.

```sql
CREATE TABLE chapters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    module_id INT UNSIGNED NOT NULL,

    -- Identity
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) NULL,

    -- Content
    content LONGTEXT NOT NULL COMMENT 'HTML content of the chapter',
    summary TEXT NULL COMMENT 'Brief summary for listings',

    -- Metadata
    duration_minutes INT UNSIGNED NULL COMMENT 'Estimated reading time',
    difficulty ENUM('beginner', 'intermediate', 'advanced') NULL,

    -- Media
    featured_image VARCHAR(500) NULL,
    video_url VARCHAR(500) NULL,

    -- Ordering
    display_order INT UNSIGNED NOT NULL DEFAULT 0,

    -- Learning Objectives
    learning_objectives JSON NULL COMMENT 'Array of learning objectives',

    -- Prerequisites
    requires_previous_completion BOOLEAN NOT NULL DEFAULT TRUE,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_chapters_module (module_id)
        REFERENCES modules(id) ON DELETE CASCADE,

    -- Indexes
    UNIQUE KEY uk_module_slug (module_id, slug),
    INDEX idx_module_order (module_id, display_order)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Example Data Mapping:**
- `chapter1.html` → module_id=1, display_order=1
- `chapter1_11.html` → module_id=1, display_order=2
- `chapter2.html` → module_id=1, display_order=3
- etc.

**File Naming to Database Mapping:**
```
chapter1.html       → module 1, order 1  (main chapter)
chapter1_11.html    → module 1, order 2  (sub-lesson)
chapter1_17.html    → module 1, order 3  (sub-lesson)
chapter2.html       → module 1, order 4  (main chapter)
chapter2_12.html    → module 1, order 5  (sub-lesson)
...
chapter7.html       → module 2, order 1  (module 2 starts)
```

---

### 5. quizzes

**Purpose:** Quiz definitions for each module.

```sql
CREATE TABLE quizzes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    module_id INT UNSIGNED NOT NULL,

    -- Identity
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,

    -- Configuration
    passing_score INT UNSIGNED NOT NULL DEFAULT 70 COMMENT 'Percentage required to pass',
    time_limit_minutes INT UNSIGNED NULL COMMENT 'NULL = no time limit',
    max_attempts INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = unlimited',

    -- Behavior
    shuffle_questions BOOLEAN NOT NULL DEFAULT FALSE,
    shuffle_options BOOLEAN NOT NULL DEFAULT FALSE,
    show_correct_answers BOOLEAN NOT NULL DEFAULT TRUE,
    show_feedback BOOLEAN NOT NULL DEFAULT TRUE,

    -- Availability
    available_from TIMESTAMP NULL DEFAULT NULL,
    available_until TIMESTAMP NULL DEFAULT NULL,

    -- Tracking
    total_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    average_score DECIMAL(5,2) NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_quizzes_module (module_id)
        REFERENCES modules(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_module (module_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Example Data Mapping:**
- `module1Quiz.html` → quiz for module 1
- `module2Quiz.html` → quiz for module 2
- etc.

---

### 6. quiz_questions

**Purpose:** Individual questions within quizzes.

```sql
CREATE TABLE quiz_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    quiz_id INT UNSIGNED NOT NULL,

    -- Content
    question TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'essay')
        NOT NULL DEFAULT 'multiple_choice',

    -- Options (for multiple choice)
    options JSON NOT NULL COMMENT 'Array of answer options',
    correct_answer INT UNSIGNED NOT NULL COMMENT 'Index of correct option (0-based)',

    -- Feedback
    explanation TEXT NULL COMMENT 'Shown after answer submission',

    -- Scoring
    points INT UNSIGNED NOT NULL DEFAULT 1,

    -- Ordering
    display_order INT UNSIGNED NOT NULL DEFAULT 0,

    -- Media
    image_url VARCHAR(500) NULL,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_quiz_questions_quiz (quiz_id)
        REFERENCES quizzes(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_quiz_order (quiz_id, display_order)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**JSON Structure for `options`:**
```json
[
    "Option A: First answer",
    "Option B: Second answer",
    "Option C: Third answer",
    "Option D: Fourth answer"
]
```

**Example from Current Quiz Data:**
```json
{
  "question": "What does AI stand for?",
  "options": [
    "Artificial Intelligence",
    "Automated Information",
    "Advanced Interface",
    "Applied Innovation"
  ],
  "correct_answer": 0,
  "explanation": "AI stands for Artificial Intelligence..."
}
```

---

### 7. enrollments

**Purpose:** Tracks which users are enrolled in which courses.

```sql
CREATE TABLE enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,

    -- Status
    status ENUM('active', 'completed', 'dropped', 'expired')
        NOT NULL DEFAULT 'active',

    -- Progress
    progress_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00
        COMMENT '0.00 to 100.00',
    chapters_completed INT UNSIGNED NOT NULL DEFAULT 0,
    chapters_total INT UNSIGNED NOT NULL DEFAULT 0,

    -- Timing
    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    last_accessed_at TIMESTAMP NULL DEFAULT NULL,

    -- Certificate
    certificate_issued BOOLEAN NOT NULL DEFAULT FALSE,
    certificate_issued_at TIMESTAMP NULL DEFAULT NULL,

    -- Approval (if required)
    requires_approval BOOLEAN NOT NULL DEFAULT FALSE,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    approved_by INT UNSIGNED NULL,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_enrollments_user (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_enrollments_course (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY fk_enrollments_approved_by (approved_by)
        REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    UNIQUE KEY uk_user_course (user_id, course_id),
    INDEX idx_user (user_id),
    INDEX idx_course (course_id),
    INDEX idx_status (status),
    INDEX idx_enrolled_at (enrolled_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Business Logic:**
- One user can only be enrolled in a course once (enforced by unique key)
- Progress is calculated and cached for performance
- `completed_at` is set when `progress_percentage` reaches 100%

---

### 8. progress

**Purpose:** Tracks chapter-level completion for each user.

```sql
CREATE TABLE progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    user_id INT UNSIGNED NOT NULL,
    chapter_id INT UNSIGNED NOT NULL,
    enrollment_id INT UNSIGNED NOT NULL,

    -- Status
    completed BOOLEAN NOT NULL DEFAULT FALSE,

    -- Tracking
    time_spent_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    visit_count INT UNSIGNED NOT NULL DEFAULT 0,

    -- Bookmarking
    bookmarked BOOLEAN NOT NULL DEFAULT FALSE,
    notes TEXT NULL,

    -- Timing
    first_accessed_at TIMESTAMP NULL DEFAULT NULL,
    last_accessed_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_progress_user (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_progress_chapter (chapter_id)
        REFERENCES chapters(id) ON DELETE CASCADE,
    FOREIGN KEY fk_progress_enrollment (enrollment_id)
        REFERENCES enrollments(id) ON DELETE CASCADE,

    -- Indexes
    UNIQUE KEY uk_user_chapter (user_id, chapter_id),
    INDEX idx_user (user_id),
    INDEX idx_chapter (chapter_id),
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_completed (completed)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Usage:**
- Record is created when user first accesses a chapter
- `completed` is marked TRUE when user finishes the chapter
- `time_spent_seconds` is accumulated across multiple visits
- Used to calculate enrollment progress percentage

---

### 9. quiz_attempts

**Purpose:** Records user quiz submissions.

```sql
CREATE TABLE quiz_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    user_id INT UNSIGNED NOT NULL,
    quiz_id INT UNSIGNED NOT NULL,
    enrollment_id INT UNSIGNED NOT NULL,

    -- Attempt Details
    attempt_number INT UNSIGNED NOT NULL COMMENT 'Which attempt (1, 2, 3...)',

    -- Scoring
    score DECIMAL(5,2) NOT NULL COMMENT 'Percentage score (0.00-100.00)',
    points_earned INT UNSIGNED NOT NULL DEFAULT 0,
    points_possible INT UNSIGNED NOT NULL DEFAULT 0,
    passed BOOLEAN NOT NULL DEFAULT FALSE,

    -- Answers
    total_questions INT UNSIGNED NOT NULL,
    correct_answers INT UNSIGNED NOT NULL DEFAULT 0,
    incorrect_answers INT UNSIGNED NOT NULL DEFAULT 0,
    unanswered INT UNSIGNED NOT NULL DEFAULT 0,

    -- Timing
    started_at TIMESTAMP NOT NULL,
    submitted_at TIMESTAMP NOT NULL,
    time_taken_seconds INT UNSIGNED NOT NULL,

    -- Status
    status ENUM('in_progress', 'submitted', 'graded', 'abandoned')
        NOT NULL DEFAULT 'in_progress',

    -- Instructor Review (for essay questions)
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    instructor_feedback TEXT NULL,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_quiz_attempts_user (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_quiz_attempts_quiz (quiz_id)
        REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY fk_quiz_attempts_enrollment (enrollment_id)
        REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY fk_quiz_attempts_reviewed_by (reviewed_by)
        REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_user (user_id),
    INDEX idx_quiz (quiz_id),
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_user_quiz (user_id, quiz_id, attempt_number)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Business Logic:**
- Each quiz submission creates a new attempt record
- `attempt_number` increments for each retry
- `passed` is TRUE if `score >= quiz.passing_score`
- Best attempt is used for certificate generation

---

### 10. quiz_attempt_answers

**Purpose:** Stores individual answers for each quiz attempt.

```sql
CREATE TABLE quiz_attempt_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    quiz_attempt_id INT UNSIGNED NOT NULL,
    quiz_question_id INT UNSIGNED NOT NULL,

    -- Answer
    selected_option INT UNSIGNED NULL COMMENT 'Selected option index (0-based)',
    essay_answer TEXT NULL COMMENT 'For essay-type questions',

    -- Evaluation
    is_correct BOOLEAN NULL COMMENT 'NULL if not yet graded',
    points_awarded INT UNSIGNED NOT NULL DEFAULT 0,

    -- Timing
    time_spent_seconds INT UNSIGNED NULL,
    answered_at TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_quiz_attempt_answers_attempt (quiz_attempt_id)
        REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY fk_quiz_attempt_answers_question (quiz_question_id)
        REFERENCES quiz_questions(id) ON DELETE CASCADE,

    -- Indexes
    UNIQUE KEY uk_attempt_question (quiz_attempt_id, quiz_question_id),
    INDEX idx_attempt (quiz_attempt_id),
    INDEX idx_question (quiz_question_id),
    INDEX idx_correct (is_correct)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Usage:**
- One record per question per attempt
- `selected_option` stores the index of chosen answer
- `is_correct` is calculated by comparing with `quiz_questions.correct_answer`
- Allows detailed quiz analytics

---

### 11. project_submissions

**Purpose:** Handles student project submissions.

```sql
CREATE TABLE project_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    user_id INT UNSIGNED NOT NULL,
    chapter_id INT UNSIGNED NULL COMMENT 'Associated chapter if applicable',
    module_id INT UNSIGNED NULL COMMENT 'Associated module if applicable',
    enrollment_id INT UNSIGNED NOT NULL,

    -- Submission Details
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,

    -- Files
    file_path VARCHAR(500) NOT NULL COMMENT 'Path to uploaded file',
    file_name VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED NOT NULL COMMENT 'Bytes',
    file_type VARCHAR(100) NOT NULL COMMENT 'MIME type',

    -- Status
    status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'revision_requested')
        NOT NULL DEFAULT 'draft',

    -- Grading
    score DECIMAL(5,2) NULL COMMENT 'Percentage or points',
    max_score DECIMAL(5,2) NULL,
    passed BOOLEAN NULL,

    -- Feedback
    instructor_feedback TEXT NULL,
    graded_by INT UNSIGNED NULL,
    graded_at TIMESTAMP NULL DEFAULT NULL,

    -- Timing
    submitted_at TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_project_submissions_user (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_project_submissions_chapter (chapter_id)
        REFERENCES chapters(id) ON DELETE SET NULL,
    FOREIGN KEY fk_project_submissions_module (module_id)
        REFERENCES modules(id) ON DELETE SET NULL,
    FOREIGN KEY fk_project_submissions_enrollment (enrollment_id)
        REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY fk_project_submissions_graded_by (graded_by)
        REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_user (user_id),
    INDEX idx_chapter (chapter_id),
    INDEX idx_module (module_id),
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 12. certificates

**Purpose:** Stores awarded certificates.

```sql
CREATE TABLE certificates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    enrollment_id INT UNSIGNED NOT NULL,

    -- Certificate Details
    certificate_number VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unique certificate ID',
    certificate_hash VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash for verification',

    -- Content
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,

    -- File
    file_path VARCHAR(500) NULL COMMENT 'Path to generated PDF',
    file_url VARCHAR(500) NULL COMMENT 'Public URL if hosted elsewhere',

    -- Metadata
    completion_date DATE NOT NULL,
    final_score DECIMAL(5,2) NULL,

    -- Verification
    is_valid BOOLEAN NOT NULL DEFAULT TRUE,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    revoked_by INT UNSIGNED NULL,
    revocation_reason TEXT NULL,

    -- Issued By
    issued_by INT UNSIGNED NULL COMMENT 'User ID of issuer',
    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_certificates_user (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_certificates_course (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY fk_certificates_enrollment (enrollment_id)
        REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY fk_certificates_issued_by (issued_by)
        REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY fk_certificates_revoked_by (revoked_by)
        REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_user (user_id),
    INDEX idx_course (course_id),
    INDEX idx_certificate_number (certificate_number),
    INDEX idx_certificate_hash (certificate_hash),
    INDEX idx_issued_at (issued_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Certificate Number Format:** `SCIBONO-AIFLU-{YEAR}-{SEQUENTIAL}`
Example: `SCIBONO-AIFLU-2025-000123`

---

### 13. sessions

**Purpose:** Manages user login sessions (if using session-based auth).

```sql
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,

    -- Relationships
    user_id INT UNSIGNED NULL,

    -- Session Data
    payload LONGTEXT NOT NULL COMMENT 'Serialized session data',

    -- Metadata
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    -- Timing
    last_activity TIMESTAMP NOT NULL,

    -- Foreign Keys
    FOREIGN KEY fk_sessions_user (user_id)
        REFERENCES users(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_user (user_id),
    INDEX idx_last_activity (last_activity)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Note:** If using JWT authentication, this table may not be needed.

---

### 14. password_resets

**Purpose:** Temporary storage for password reset tokens.

```sql
CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- User
    email VARCHAR(255) NOT NULL,

    -- Token
    token VARCHAR(255) NOT NULL UNIQUE,

    -- Timing
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Usage:**
- Tokens expire after 1 hour (configurable)
- Token is deleted/marked used after successful reset
- Old tokens are regularly purged

---

### 15. logs

**Purpose:** System activity audit trail.

```sql
CREATE TABLE logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Actor
    user_id INT UNSIGNED NULL COMMENT 'NULL for system actions',

    -- Action
    action VARCHAR(255) NOT NULL COMMENT 'e.g., user.login, course.created',
    entity_type VARCHAR(100) NULL COMMENT 'e.g., course, user, enrollment',
    entity_id INT UNSIGNED NULL COMMENT 'ID of affected entity',

    -- Details
    description TEXT NULL,
    metadata JSON NULL COMMENT 'Additional context',

    -- Request Info
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    -- Severity
    level ENUM('debug', 'info', 'warning', 'error', 'critical')
        NOT NULL DEFAULT 'info',

    -- Timestamp
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY fk_logs_user (user_id)
        REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_level (level),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Example Log Entries:**
```
action: 'user.login', user_id: 123, ip_address: '192.168.1.1'
action: 'course.enrolled', user_id: 123, entity_type: 'course', entity_id: 1
action: 'quiz.submitted', user_id: 123, entity_type: 'quiz', entity_id: 5
```

---

### 16. settings

**Purpose:** Application-wide configuration.

```sql
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Key-Value
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT NULL,

    -- Metadata
    description TEXT NULL,
    data_type ENUM('string', 'integer', 'boolean', 'json')
        NOT NULL DEFAULT 'string',

    -- Access Control
    is_public BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Can frontend access?',

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY uk_setting_key (setting_key)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Example Settings:**
```
site_name: "Sci-Bono AI Fluency Platform"
site_email: "info@scibono.co.za"
enable_registrations: "true"
min_password_length: "8"
session_timeout_minutes: "60"
max_quiz_attempts: "3"
certificate_enabled: "true"
```

---

## Relationships & Foreign Keys

### Relationship Summary

```
users (1) ──< enrollments (M)
courses (1) ──< enrollments (M)
enrollments (1) ──< progress (M)
enrollments (1) ──< quiz_attempts (M)

courses (1) ──< modules (M)
modules (1) ──< chapters (M)
modules (1) ──< quizzes (M)
quizzes (1) ──< quiz_questions (M)

users (1) ──< progress (M)
chapters (1) ──< progress (M)

users (1) ──< quiz_attempts (M)
quizzes (1) ──< quiz_attempts (M)
quiz_attempts (1) ──< quiz_attempt_answers (M)
quiz_questions (1) ──< quiz_attempt_answers (M)

users (1) ──< project_submissions (M)
chapters (1) ──< project_submissions (M)
modules (1) ──< project_submissions (M)

users (1) ──< certificates (M)
courses (1) ──< certificates (M)
```

### Cascade Rules

**ON DELETE CASCADE:**
- When a user is deleted, all their enrollments, progress, quiz attempts, submissions, and certificates are deleted
- When a course is deleted, all modules, enrollments, and related data are deleted
- When a module is deleted, all chapters and quizzes are deleted
- When a chapter is deleted, all progress records are deleted
- When a quiz is deleted, all questions and attempts are deleted

**ON DELETE RESTRICT:**
- Cannot delete a user who created a course (must reassign first)

**ON DELETE SET NULL:**
- When an instructor/admin is deleted, their review/approval records show NULL

---

## Indexes & Performance

### Index Strategy

**Primary Keys (Clustered Indexes):**
- All tables have auto-incrementing integer primary keys
- Provides fast lookups and efficient joins

**Foreign Keys:**
- All foreign key columns are indexed automatically
- Ensures fast join performance

**Unique Indexes:**
- `users.email` - Fast login lookups
- `courses.slug` - SEO-friendly URLs
- `enrollments(user_id, course_id)` - Prevent duplicate enrollments
- `progress(user_id, chapter_id)` - One progress record per user per chapter

**Composite Indexes:**
```sql
-- Find all chapters in a module, ordered
INDEX idx_module_order ON chapters(module_id, display_order)

-- Find all quizzes for a specific course
INDEX idx_module_quizzes ON quizzes(module_id)

-- Find user's quiz attempts
INDEX idx_user_quiz ON quiz_attempts(user_id, quiz_id, attempt_number)
```

**Covering Indexes (Future Optimization):**
```sql
-- Dashboard: Show enrolled courses with progress
CREATE INDEX idx_enrollments_progress
ON enrollments(user_id, status, progress_percentage, last_accessed_at);
```

### Query Optimization Tips

**Avoid N+1 Queries:**
```sql
-- BAD: Separate query for each module's chapters
SELECT * FROM modules WHERE course_id = 1;
-- Then loop: SELECT * FROM chapters WHERE module_id = ?

-- GOOD: Join and fetch all at once
SELECT m.*, c.*
FROM modules m
LEFT JOIN chapters c ON m.id = c.module_id
WHERE m.course_id = 1
ORDER BY m.display_order, c.display_order;
```

**Use Pagination:**
```sql
-- For large result sets
SELECT * FROM logs
ORDER BY created_at DESC
LIMIT 50 OFFSET 0;
```

**Cache Aggregate Values:**
```sql
-- Denormalize common calculations
UPDATE courses
SET total_enrollments = (SELECT COUNT(*) FROM enrollments WHERE course_id = courses.id);
```

---

## Data Types & Constraints

### Data Type Guide

**Strings:**
- `VARCHAR(255)` - Standard string (names, emails, short text)
- `VARCHAR(500)` - Longer strings (titles, descriptions)
- `TEXT` - Medium text (descriptions, summaries)
- `LONGTEXT` - Very large text (HTML content, JSON data)

**Numbers:**
- `INT UNSIGNED` - Positive integers (IDs, counts)
- `BIGINT UNSIGNED` - Very large integers (logs, high-volume tables)
- `DECIMAL(10,2)` - Money/prices (10 digits, 2 decimals)
- `DECIMAL(5,2)` - Percentages (0.00 to 100.00)
- `DECIMAL(3,2)` - Ratings (0.00 to 5.00)

**Dates/Times:**
- `DATE` - Date only (date_of_birth, completion_date)
- `TIMESTAMP` - Date and time (created_at, updated_at, logged_at)
  - Automatically handles timezone conversion
  - Range: 1970-2038 (sufficient for this application)

**Booleans:**
- `BOOLEAN` / `TINYINT(1)` - TRUE/FALSE values

**Special Types:**
- `ENUM` - Fixed set of values (status, role, level)
- `JSON` - Structured data (quiz options, metadata)

### Constraint Types

**NOT NULL:**
- Required fields that must always have a value
- Examples: name, email, password_hash

**UNIQUE:**
- Ensures no duplicate values
- Examples: email, slug, certificate_number

**DEFAULT:**
- Provides default value if not specified
- Examples: DEFAULT 'active', DEFAULT 0, DEFAULT FALSE

**CHECK (MySQL 8.0+):**
```sql
CHECK (progress_percentage >= 0 AND progress_percentage <= 100)
CHECK (passing_score >= 0 AND passing_score <= 100)
```

---

## Sample Data

### Example: Complete Course Structure

```sql
-- Insert Course
INSERT INTO courses (id, title, slug, description, partner, status, created_by, published_at) VALUES
(1, 'AI Fluency Course', 'ai-fluency', 'Comprehensive AI education for grades 8-12', 'Microsoft', 'published', 1, NOW());

-- Insert Modules
INSERT INTO modules (id, course_id, title, slug, description, display_order) VALUES
(1, 1, 'AI Foundations', 'ai-foundations', 'Introduction to Artificial Intelligence', 1),
(2, 1, 'Generative AI', 'generative-ai', 'Understanding Generative AI Technologies', 2),
(3, 1, 'Advanced Search', 'advanced-search', 'Advanced Search Techniques with AI', 3),
(4, 1, 'Responsible AI', 'responsible-ai', 'Ethics and Responsibility in AI', 4),
(5, 1, 'Microsoft Copilot', 'microsoft-copilot', 'Using Microsoft Copilot Effectively', 5),
(6, 1, 'AI Impact', 'ai-impact', 'AI Impact on Society and Future', 6);

-- Insert Chapters (Module 1 examples)
INSERT INTO chapters (id, module_id, title, slug, content, display_order) VALUES
(1, 1, 'Introduction to AI', 'introduction-to-ai', '<h1>Welcome to AI Foundations</h1>...', 1),
(2, 1, 'What is Artificial Intelligence?', 'what-is-ai', '<h1>Defining AI</h1>...', 2),
(3, 1, 'History of AI', 'history-of-ai', '<h1>AI Through the Ages</h1>...', 3);

-- Insert Quiz
INSERT INTO quizzes (id, module_id, title, passing_score) VALUES
(1, 1, 'Module 1: AI Foundations Quiz', 70);

-- Insert Quiz Questions
INSERT INTO quiz_questions (quiz_id, question, options, correct_answer, explanation, display_order) VALUES
(1, 'What does AI stand for?',
 '["Artificial Intelligence", "Automated Information", "Advanced Interface", "Applied Innovation"]',
 0,
 'AI stands for Artificial Intelligence, which refers to the simulation of human intelligence in machines.',
 1);
```

### Example: User Enrollment Flow

```sql
-- User enrolls in course
INSERT INTO enrollments (user_id, course_id, enrolled_at) VALUES
(123, 1, NOW());

-- User starts first chapter
INSERT INTO progress (user_id, chapter_id, enrollment_id, first_accessed_at, last_accessed_at) VALUES
(123, 1, 1, NOW(), NOW());

-- User completes first chapter
UPDATE progress
SET completed = TRUE, completed_at = NOW(), time_spent_seconds = 600
WHERE user_id = 123 AND chapter_id = 1;

-- User takes quiz
INSERT INTO quiz_attempts (user_id, quiz_id, enrollment_id, attempt_number, started_at) VALUES
(123, 1, 1, 1, NOW());

-- User submits quiz (auto-calculated)
UPDATE quiz_attempts
SET submitted_at = NOW(),
    score = 85.00,
    passed = TRUE,
    status = 'submitted'
WHERE id = LAST_INSERT_ID();
```

---

## Migration Scripts

### Initial Database Creation

**File:** `migrations/001_create_database.sql`

```sql
-- Create database
CREATE DATABASE IF NOT EXISTS scibono_ai_fluency
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE scibono_ai_fluency;
```

### Create All Tables

**File:** `migrations/002_create_tables.sql`

```sql
-- Execute in this order to respect foreign key dependencies:
-- 1. users
-- 2. courses
-- 3. modules
-- 4. chapters
-- 5. quizzes
-- 6. quiz_questions
-- 7. enrollments
-- 8. progress
-- 9. quiz_attempts
-- 10. quiz_attempt_answers
-- 11. project_submissions
-- 12. certificates
-- 13. sessions
-- 14. password_resets
-- 15. logs
-- 16. settings

-- (Full CREATE TABLE statements from above)
```

### Seed Initial Data

**File:** `migrations/003_seed_data.sql`

```sql
-- Create admin user
INSERT INTO users (name, email, password_hash, role, status, email_verified_at) VALUES
('Admin User', 'admin@scibono.co.za', '$2y$10$...(bcrypt hash)...', 'admin', 'active', NOW());

-- Insert AI Fluency course structure
-- (See Sample Data section above)

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description, data_type) VALUES
('site_name', 'Sci-Bono AI Fluency Platform', 'Site name', 'string'),
('enable_registrations', 'true', 'Allow new user registrations', 'boolean'),
('min_password_length', '8', 'Minimum password length', 'integer'),
('max_quiz_attempts', '0', 'Max quiz attempts (0 = unlimited)', 'integer'),
('certificate_enabled', 'true', 'Enable certificate generation', 'boolean');
```

### Migration Execution Order

```bash
# 1. Create database
mysql -u root -p < migrations/001_create_database.sql

# 2. Create tables
mysql -u root -p scibono_ai_fluency < migrations/002_create_tables.sql

# 3. Seed initial data
mysql -u root -p scibono_ai_fluency < migrations/003_seed_data.sql

# 4. Verify
mysql -u root -p scibono_ai_fluency -e "SHOW TABLES;"
mysql -u root -p scibono_ai_fluency -e "SELECT COUNT(*) FROM users;"
```

---

## Security Considerations

### Password Security

**Hashing:**
```php
// PHP password hashing
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verification
if (password_verify($input_password, $stored_hash)) {
    // Password correct
}
```

**Requirements:**
- Minimum 8 characters (configurable)
- Mix of uppercase, lowercase, numbers, symbols (enforced client-side)
- Never store plain text passwords
- Use bcrypt with cost factor 12

### SQL Injection Prevention

**Always use prepared statements:**
```php
// GOOD: Prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// BAD: String concatenation
$query = "SELECT * FROM users WHERE email = '$email'"; // VULNERABLE!
```

### Authorization Checks

**Row-Level Security:**
```php
// Ensure user can only access their own data
$stmt = $pdo->prepare("
    SELECT * FROM enrollments
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$enrollment_id, $current_user_id]);
```

### Data Validation

**Input Validation:**
- Validate all inputs before database insertion
- Use appropriate data types
- Enforce length limits
- Sanitize HTML content (use libraries like HTML Purifier)

**Email Validation:**
```php
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception("Invalid email address");
}
```

### Soft Deletes

**Users table includes `deleted_at`:**
- Don't permanently delete user data (regulatory compliance)
- Set `deleted_at` timestamp instead
- Exclude soft-deleted records from queries:

```sql
SELECT * FROM users WHERE deleted_at IS NULL;
```

### Audit Trail

**Use logs table:**
- Log all sensitive actions (login, enrollment, certificate issuance)
- Store IP addresses and user agents
- Regularly archive old logs

---

## Backup & Maintenance

### Backup Strategy

**Daily Backups:**
```bash
#!/bin/bash
# Backup script
DATE=$(date +%Y-%m-%d_%H-%M-%S)
mysqldump -u root -p scibono_ai_fluency \
    --single-transaction \
    --quick \
    --lock-tables=false \
    > backups/scibono_ai_fluency_$DATE.sql

# Compress
gzip backups/scibono_ai_fluency_$DATE.sql

# Keep only last 30 days
find backups/ -name "*.sql.gz" -mtime +30 -delete
```

**Tables to Backup Separately:**
- `users` - Critical user data
- `enrollments` - Enrollment history
- `certificates` - Issued certificates
- `quiz_attempts` - Student records

### Maintenance Tasks

**Weekly:**
```sql
-- Optimize tables
OPTIMIZE TABLE progress;
OPTIMIZE TABLE quiz_attempts;
OPTIMIZE TABLE logs;

-- Analyze tables for query optimization
ANALYZE TABLE enrollments;
ANALYZE TABLE progress;
```

**Monthly:**
```sql
-- Clean up expired password reset tokens
DELETE FROM password_resets WHERE expires_at < NOW();

-- Archive old logs (move to archive table)
INSERT INTO logs_archive SELECT * FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Clean up abandoned sessions
DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Monitoring Queries

**Check table sizes:**
```sql
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)",
    table_rows
FROM information_schema.TABLES
WHERE table_schema = 'scibono_ai_fluency'
ORDER BY (data_length + index_length) DESC;
```

**Find slow queries:**
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2; -- Log queries taking > 2 seconds
```

---

## Related Documents

### Architecture Documents
- [Current Architecture](../01-Architecture/current-architecture.md) - Existing static PWA structure
- [Future Architecture](../01-Architecture/future-architecture.md) - Full LMS design
- [Migration Roadmap](../01-Architecture/migration-roadmap.md) - Implementation plan

### Development Guides
- [Development Setup Guide](../04-Development/setup-guide.md) (coming soon)
- [Coding Standards](../04-Development/coding-standards.md) (coming soon)
- [API Documentation](../02-Code-Reference/api-design.md) (coming soon)

### External References
- [MySQL 8.0 Documentation](https://dev.mysql.com/doc/refman/8.0/en/)
- [InnoDB Storage Engine](https://dev.mysql.com/doc/refman/8.0/en/innodb-storage-engine.html)
- [MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)

---

## Document Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2025-10-27 | 1.0 | Dev Team | Initial database schema design |

---

## Appendix

### ERD Diagram Files

**Recommended Tools:**
- MySQL Workbench (export EER diagrams)
- dbdiagram.io (online ERD tool)
- Draw.io (manual diagrams)

**To Generate:**
```bash
# Using MySQL Workbench
mysql-workbench --model scibono_ai_fluency

# Export as PNG/PDF
```

### Database Naming Reference

**Abbreviations Used:**
- `id` = Identifier
- `fk` = Foreign Key
- `uk` = Unique Key
- `idx` = Index
- `PK` = Primary Key
- `FK` = Foreign Key
- `M` = Many (in relationships)

---

**END OF DOCUMENT**

*This schema design provides a comprehensive foundation for the Sci-Bono AI Fluency LMS database. Review and adjust based on specific requirements during implementation.*
