# Changelog

All notable changes to the Sci-Bono AI Fluency LMS project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### ⚡ Performance
- Coming soon: Advanced caching strategies
- Coming soon: Database query optimization

---

## [0.9.0] - Phase 8 Complete: Profile Building & Viewing (2026-01-20)

### 🗄️ Database Schema Enhancements
- **Migration 020**: Added comprehensive profile system to users table
- Added 13 new columns to users table:
  - Profile content: `bio` (TEXT, 5000 char limit), `headline` (VARCHAR 255), `location` (VARCHAR 255)
  - Social links: `website_url`, `github_url`, `linkedin_url`, `twitter_url` (VARCHAR 255)
  - Privacy controls: `is_public_profile`, `show_email`, `show_achievements`, `show_certificates` (BOOLEAN/TINYINT)
  - Metadata: `profile_views_count` (INT, default 0), `last_profile_updated` (TIMESTAMP)
- Created `profile_views` table for analytics:
  - Tracks `viewer_user_id`, `viewed_user_id`, `viewed_at`, `ip_address`, `user_agent`
  - Foreign key constraints with CASCADE delete for data integrity
  - Indexes on `viewed_user_id`, `viewer_user_id`, `viewed_at` for query performance

### 🔧 Enhanced - User Model (`api/models/User.php`)
- Added 13 fillable fields for profile data
- Implemented 7 new methods:
  1. `updateProfileFields()` - Update profile content with timestamp tracking
  2. `getPublicProfileData()` - Privacy-aware profile retrieval (respects is_public_profile)
  3. `updatePrivacySettings()` - Granular privacy controls management
  4. `getProfileCompletionPercentage()` - Calculate 0-100% completion score (10 fields tracked)
  5. `trackProfileView()` - Record view with self-view prevention
  6. `searchPublicProfiles()` - Search by name/headline/location with pagination support
  7. Helper methods for data validation and sanitization

### 🚀 New API Endpoints - UserController
- Added 5 new RESTful endpoints:
  1. `PUT /api/users/:id/profile` - Update profile fields (authenticated, owner-only)
  2. `GET /api/users/:id/profile/public` - Get public profile (respects privacy settings)
  3. `PUT /api/users/:id/profile/privacy` - Update privacy settings (authenticated, owner-only)
  4. `GET /api/users/:id/profile/completion` - Get completion percentage (authenticated)
  5. `GET /api/users/profiles/search` - Search public profiles (optional auth, supports pagination)
- All endpoints follow REST conventions with proper HTTP methods and status codes
- Privacy enforcement at server-side (not just client-side)

### ✅ Testing & Validation
- Created comprehensive test suite (`test_profile_system.php`) with 29 tests:
  - Schema verification (13 columns + profile_views table)
  - Model method testing (all 7 methods)
  - Privacy enforcement tests
  - Search functionality with pagination
  - Profile completion calculation
  - View tracking with self-view prevention
- **Result:** 100% pass rate (29/29 tests) ✅
- Fixed 4 issues during testing:
  1. MySQL TINYINT boolean handling (converted to integers 0/1)
  2. Profile views table creation (manually created via PHP)
  3. Search query parameter binding (switched to positional parameters)
  4. Privacy check integer comparison (strict == 1 comparison)

### 🎨 Frontend - Profile Editing Interface
- **profile-edit.html** (246 lines): Full profile editing interface
  - Avatar upload area with preview
  - Basic information section (bio 5000 chars, headline 255 chars, location)
  - Social links section (4 platforms with URL validation)
  - Privacy toggles with animated switches
  - Profile completion progress bar
  - Real-time character counters
  - Mobile-responsive layout
- **js/profile-edit.js** (380 lines): Profile editing functionality
  - `loadUserProfile()`, `loadProfileCompletion()`, `handleProfileSave()`
  - `validateUrls()`, `setupCharacterCounters()`, `uploadAvatar()`, `removeAvatar()`
  - `showNotification()` - GSAP-animated success/error messages
  - Dual API calls (profile + privacy) with error handling
  - URL validation regex for all social links
  - Character limit enforcement with visual feedback

### 🌐 Frontend - Profile Display & Directory
- **profile.html** (updated): Added "Profile Details" section
  - Displays headline, bio, location, social links (conditional rendering)
  - Changed "Edit Profile" button to link to `/profile-edit.html`
  - Added "View Public Profile" button linking to `/profile-view.html?id={userId}`
  - Empty state message for incomplete profiles
  - JavaScript functions: `loadProfileDetails()`, `setupPublicProfileButton()`
- **profile-view.html** (140 lines): Privacy-aware public profile viewing
  - Profile header with avatar, name, headline, views badge
  - About section (bio), social links grid
  - Achievements section (if show_achievements = true)
  - Certificates section (if show_certificates = true)
  - Stats grid (courses, member since, achievements/certs if visible)
  - Error state for private/non-existent profiles
  - GSAP animations for smooth transitions
- **js/profile-view.js** (330 lines): Public profile display logic
  - `loadPublicProfile()`, `displayProfile()`, `trackView()`
  - `loadAchievements()`, `loadCertificates()`, `loadStats()`
  - Privacy enforcement (respects all privacy flags)
  - Self-view prevention for analytics
- **profiles-directory.html** (100 lines): Searchable learner directory
  - Search box with real-time filtering (300ms debounce)
  - Role filters (All, Students, Instructors) with live counts
  - Profile cards grid (12 per page)
  - Pagination controls (Previous/Next)
  - Empty state for no results
- **js/profiles-directory.js** (340 lines): Directory functionality
  - `loadProfiles()`, `applyFilters()`, `handleSearch()`, `displayProfiles()`
  - Client-side filtering for instant results
  - Pagination (12 profiles per page)
  - Search by name, headline, or location
  - GSAP-animated profile cards

### 🎨 CSS Styling - 1,330 Lines Added
- **Profile Edit Styles** (lines 4657-4977, 320 lines):
  - Form sections with clean card design
  - Avatar upload area with hover effects
  - Character counters with color coding
  - Privacy toggle switches with smooth animations
  - Animated notifications (success/error with slide-in)
  - Responsive breakpoints for mobile/tablet
- **Profile Display Styles** (lines 4978-5151, 173 lines):
  - Profile details section with icon labels
  - Bio text formatting with pre-wrap for line breaks
  - Social links with gradient buttons and hover effects
  - Empty state styling with call-to-action
  - Button styles for Edit Profile and View Public Profile
- **Public Profile View Styles** (lines 5153-5494, 341 lines):
  - Large profile header with avatar and metadata
  - Profile sections with consistent card design
  - Social links grid with gradient buttons
  - Achievements/certificates grids with tier colors (bronze/silver/gold/platinum)
  - Stats grid with hover effects
  - Loading and error states
- **Directory Styles** (lines 5496-5841, 345 lines):
  - Directory header with large title
  - Search box with icon positioning
  - Filter buttons with active state gradients
  - Profile cards with hover lift effect
  - Avatar with gradient background and initials fallback
  - Empty state styling
  - Pagination controls

### 🔐 Security Enhancements
- **Input Validation**:
  - Bio limited to 5000 characters (client + server)
  - Headline limited to 255 characters (client + server)
  - URL validation with regex (client + server)
  - XSS prevention with `htmlspecialchars()` on output
  - SQL injection prevention with PDO prepared statements
- **Privacy Protection**:
  - Privacy flags enforced server-side (not just client-side)
  - Private profiles return NULL from `getPublicProfileData()`
  - Privacy settings only editable by profile owner (JWT auth)
  - Profile views only tracked when profile is public
- **External Links Security**:
  - All social links use `target="_blank"` (new tab)
  - All social links use `rel="noopener noreferrer"` (security)
  - URL validation prevents javascript: and data: URLs

### ⚡ Performance Optimizations
- Database indexes on profile_views table (viewed_user_id, viewer_user_id, viewed_at)
- CASCADE delete on foreign keys (automatic cleanup)
- Pagination support in searchPublicProfiles() (limit/offset)
- Debounced search input (300ms delay) for reduced server load
- Client-side filtering for instant results
- Lazy loading of achievements/certificates (only if privacy allows)
- GSAP animations use GPU acceleration

### 📚 Documentation
- Created comprehensive `Documentation/PHASE8_COMPLETE.md` (800+ lines)
  - Complete implementation summary (backend + frontend)
  - Database schema documentation
  - API endpoint reference with examples
  - Technical architecture diagrams
  - Testing summary (29/29 tests)
  - Security considerations
  - Performance optimizations
  - User experience highlights
  - Lessons learned and best practices
  - Future enhancements roadmap
- Updated `README.md`:
  - Added "Profile Building & Social Features" section
  - Updated Phase Completion Status (added Phase 8)
  - Updated overall completion to ~90%
- Updated `CHANGELOG.md` with version 0.9.0 release notes

### 📊 Statistics
- **11 files created**: 4 HTML pages, 4 JavaScript files, 1 migration, 1 test file, 1 documentation
- **4 files modified**: User.php, UserController.php, api/routes/api.php, profile.html, styles.css
- **~2,900 lines of code** added (backend + frontend)
- **1,330 lines of CSS** styling added
- **100% test pass rate** (29/29 backend tests)
- **5 new API endpoints** implemented
- **7 new model methods** implemented

### 🎯 Key Features
1. **Profile Completion System**: Tracks 10 fields, displays 0-100% with animated progress bar
2. **Privacy Controls**: 4 granular settings (is_public_profile, show_email, show_achievements, show_certificates)
3. **Profile View Analytics**: Tracks every view with self-view prevention
4. **Search & Discovery**: Search by name/headline/location, filter by role, pagination
5. **Social Integration**: 4 social platforms (Website, GitHub, LinkedIn, Twitter) with URL validation

---

## [0.8.0] - Phase 7 Complete (2025-12-30)

### 🗄️ Database Schema Fixes
- **Migration 019**: Fixed critical schema mismatches in projects and project_submissions tables
- Added `course_id` column to projects table with foreign key to courses (ON DELETE CASCADE)
- Added `slug` column to projects table for SEO-friendly URLs
- Added `order` column to projects table for sortable project sequences
- Added `uploaded_file_id` column to project_submissions with foreign key to uploaded_files (ON DELETE SET NULL)
- Created unique constraint on (course_id, slug) for per-course slug uniqueness
- Added performance indexes: `idx_course_id`, `idx_course_order`, `idx_uploaded_file_id`
- Migrated 6 existing projects with automatic slug generation and course_id derivation
- Zero data loss during migration

### 🔧 Enhanced - BaseModel
- Fixed `create()` method to escape column names with backticks (lines 138-142)
- Fixed `update()` method to escape column names with backticks (lines 172-177)
- **Critical Fix**: Enables use of MySQL reserved keywords like `order` in column names
- Improved robustness for all models using BaseModel

### 📝 Updated Models
- **Project.php**: Added `'order'` to fillable array (line 24)
- **ProjectSubmission.php**: Added `'uploaded_file_id'` to fillable array (line 25)
- Both models now fully support new schema fields for mass assignment

### ✅ Testing & Validation
- Created comprehensive test suite (`test_project_schema_fix.php`) with 8 integration tests
- 87.5% test pass rate (7/8 tests)
- Validated all CRUD operations with new fields
- Confirmed foreign key constraints enforce referential integrity
- Verified index usage with EXPLAIN queries for performance
- Tested slug uniqueness constraint enforcement

### 🚀 API Functionality Restored
- **ProjectController**: All methods now functional with new schema
  - `index()` - Filter projects by course_id
  - `create()` - Create projects with course_id, slug, order
  - `update()` - Update all project fields including order
  - `getByCourse($courseId)` - Course-based queries
  - `findBySlug($slug, $courseId)` - Slug-based lookups
- Fixed broken project creation/update operations
- Enabled course-based project filtering
- Established proper file tracking for submissions

### 📚 Documentation
- Created comprehensive `Documentation/PHASE7_COMPLETE.md` (381 lines)
- Documented schema changes (BEFORE/AFTER comparisons)
- Included migration results with data validation
- Documented rollback procedures
- Added performance impact analysis
- Included security enhancements summary
- Updated README.md with Phase 7 completion status
- Updated CHANGELOG.md with version 0.8.0 release notes

### 📊 Analytics System (Phase 6 Completion)
- Created `AnalyticsController.php` with 6 analytics endpoints
- Added question difficulty analysis (`getDifficultyStats`, `getQuestionDifficultyRanking`)
- Added performance trends (`getPerformanceTrends`, `getUserLearningCurve`)
- Added class comparisons (`getClassComparison`, `getQuizLeaderboard`)
- Enhanced `QuizController.php` to populate quiz_attempt_answers during submission
- Enhanced `QuizAttempt.php` model with detailed answer tracking
- Enhanced `QuizQuestion.php` model with difficulty calculation methods

### 🎨 Added - GSAP Animations System (Phase 4 Enhancement)
- Created centralized GSAP animations library (`/js/animations.js`) with 25+ reusable functions
- Implemented animated counters for dashboard statistics (student, instructor)
- Added progress bar animations with optional pulse effects on completion
- Created circular progress chart animations with synchronized counters
- Implemented fade-in stagger effects for dashboard cards, certificates, badges
- Added slide-in animations for quiz attempts, grading queue, leaderboard entries
- Created achievement unlock animations with pop, bounce, and glow effects
- Added pulse animations for urgent grading items and top leaderboard entries
- Integrated GSAP 3.12.2 CDN in dashboard and achievements pages
- Updated `dashboard.js` to use animated counters and progress bars
- Updated `instructor.js` with grading queue animations
- Updated `achievements.js` with badge reveal and leaderboard animations

### 📚 Documentation
- Created comprehensive `README.md` with full project overview
- Created `CONTRIBUTING.md` with detailed contribution guidelines
- Created `LICENSE` file (MIT License)
- Created `CHANGELOG.md` for version tracking

---

## [0.7.0] - Phase 6 Complete (2025-11-14)

### 🏆 Added - Achievement System
- Created `AchievementController.php` with 7 public methods
- Created `Achievement.php` model with 18 methods including unlock logic
- Implemented 16 achievements across 6 categories
- Added achievement points system with tier-based rewards (Bronze, Silver, Gold, Platinum)
- Created leaderboard functionality with top achievers ranking
- Built `achievements.html` page with tabs for my achievements, all achievements, and leaderboard
- Created `achievements.js` for achievement display and unlock notifications
- Added migrations for achievements, achievement categories, user achievements, and points tables

### 📜 Added - Certificate System
- Enhanced `CertificateController.php` with verification and template support
- Created `Certificate.php` model with PDF generation capabilities
- Implemented 2 certificate templates (professional, modern)
- Added certificate verification system with unique codes
- Created certificate verification log for audit trail
- Built certificate request workflow with auto-generation on course completion

### 📝 Enhanced - Quiz Tracking
- Enhanced `QuizAttempt.php` model with 8 new Phase 6 methods
- Added 11 new columns to quiz_attempts table (IP address, user agent, time tracking, etc.)
- Implemented detailed quiz analytics and attempt history
- Added support for best/latest/average scoring modes
- Created quiz review functionality with answer breakdown

### 👨‍🏫 Added - Grading System
- Created `GradingController.php` for instructor grading workflow
- Built `instructor-grading.html` page with pending submissions queue
- Created `instructor-grading.js` for grading interface
- Implemented grading analytics and pending queue management

### 📤 Added - File Upload System
- Created `FileUploadController.php` for handling file uploads
- Implemented secure file validation (type, size, extension)
- Created `uploads/` directory structure
- Added file management for project submissions and avatars

### 🗄️ Database
- Migration 011: Enhanced quiz_attempts table with 11 new columns
- Migration 012: Created certificate_templates and certificate_verification_log tables
- Migration 013: Created achievements, achievement_categories, user_achievements, user_achievement_points tables
- Migration 014: Populated quizzes table with sample data
- Migration 015: Populated projects table with sample data
- Migration 016: Created test enrollments for development
- Migration 017: Created uploaded_files table for file tracking

### 🐛 Fixed
- Fixed quiz attempt submission with enhanced validation
- Improved error handling in certificate generation
- Enhanced security in file upload validation

---

## [0.6.0] - Phase 5D Complete (2025-11-11)

### ✨ Added - Student Engagement Features
- Created breadcrumb navigation component (`breadcrumb.js`)
- Implemented student notes system with rich text editor (Quill.js)
- Added bookmark functionality for lessons
- Created quiz randomization (questions and answers)
- Built `quiz-history.html` page for viewing past attempts
- Enhanced lesson pages with notes and bookmark buttons
- Added `NotesController.php` and `BookmarksController.php`
- Created `StudentNote.php` and `Bookmark.php` models

### 🗄️ Database
- Migration 009: Created student_notes table
- Migration 010: Created bookmarks table

---

## [0.5.0] - Phase 5A-5C Complete (2025-11-08)

### 🎨 Added - Dynamic Content System
- Created `module-dynamic.html` for dynamic module loading
- Created `lesson-dynamic.html` for dynamic lesson display
- Created `quiz-dynamic.html` for dynamic quiz rendering
- Built `content-loader.js` for fetching and rendering content from API
- Implemented lesson progress tracking with start/complete endpoints

### 🔧 Added - Admin Content Management
- Created `admin-courses.html` for course management
- Created `admin-modules.html` for module management
- Created `admin-lessons.html` for lesson management
- Created `admin-quizzes.html` for quiz management
- Built corresponding JavaScript files for each admin page
- Implemented CRUD operations for all content types

### 🔄 Enhanced Controllers
- Enhanced `LessonController.php` with 7 methods (start, complete, progress tracking)
- Enhanced `QuizController.php` with quiz submission and attempt retrieval
- Enhanced `CourseController.php` with enrolled courses endpoint
- Enhanced `ModuleController.php` with module-lesson relationships

---

## [0.4.0] - Phase 4 Complete (2025-10-28)

### 📊 Added - Dashboard System
- Created `student-dashboard.html` with progress overview
- Created `instructor-dashboard.html` with grading queue
- Created `admin-dashboard.html` with system analytics
- Built `dashboard.js` for student dashboard functionality
- Built `instructor.js` for instructor dashboard functionality
- Built `admin.js` for admin features
- Implemented role-based dashboard routing

### 📈 Added - Progress Tracking
- Created `LessonProgress.php` model for completion tracking
- Implemented progress calculation in `EnrollmentController.php`
- Added progress bars and statistics to student dashboard
- Created quiz attempt history display

---

## [0.3.0] - Phase 3 Complete (2025-10-15)

### 🔐 Added - Authentication System
- Implemented JWT-based authentication
- Created `AuthController.php` with register, login, logout, refresh endpoints
- Created `AuthMiddleware.php` for route protection
- Built `JWTHandler.php` utility class for token management
- Created token blacklist table for secure logout
- Implemented password reset flow
- Added role-based access control (RBAC)

### 🎨 Frontend Authentication
- Created `login.html` and `signup.html` pages
- Built `auth.js` for client-side authentication
- Created `header-template.js` for dynamic header based on auth state
- Implemented "Remember Me" functionality
- Added logout functionality with token invalidation

### 🗄️ Database
- Migration 006: Created token_blacklist table

---

## [0.2.0] - Phase 2 Complete (2025-10-01)

### 🏗️ Added - MVC Architecture
- Created `/api` directory structure following MVC pattern
- Implemented 14 controllers (Auth, User, Course, Module, Lesson, Quiz, Project, Enrollment, Certificate, etc.)
- Created 16 Active Record models with PDO abstraction
- Built RESTful API routing system (`/api/routes/api.php`)
- Implemented `BaseModel.php` with CRUD operations
- Created middleware system (Auth, CORS)
- Added API front controller (`/api/index.php`)
- Configured `.htaccess` for RESTful routing

### 📚 API Endpoints
- Implemented 60+ RESTful API endpoints
- Authentication endpoints (register, login, logout, refresh)
- User management endpoints
- Course/Module/Lesson CRUD endpoints
- Quiz submission and grading endpoints
- Project submission endpoints
- Enrollment management endpoints

---

## [0.1.0] - Phase 1 Complete (2025-09-15)

### 🗄️ Database Design
- Created comprehensive database schema with 34+ tables
- Implemented 17 migration files for version control
- Designed user authentication tables (users, roles)
- Created course structure tables (courses, modules, lessons, chapters)
- Designed assessment tables (quizzes, quiz_questions, quiz_attempts)
- Created project submission tables (projects, project_submissions)
- Implemented enrollment and progress tracking tables
- Added notification and activity log tables

### ⚙️ Configuration
- Created `database.php` configuration with PDO connection
- Implemented prepared statements for security
- Set up migration system for schema versioning

---

## [0.0.1] - Initial PWA Version (2025-08-01)

### ✨ Added - Static PWA
- Created 58+ static HTML pages for course content
- Implemented 6 modules with 45+ chapters
- Built 6 interactive JavaScript-based quizzes
- Created Service Worker for offline functionality
- Designed responsive CSS with CSS variables
- Implemented PWA manifest for installability
- Added Font Awesome icons
- Integrated Google Fonts (Montserrat, Poppins)

### 📱 Progressive Web App Features
- Cache-first strategy with network fallback
- Installable on mobile and desktop devices
- Offline-capable content viewing
- Custom offline page

### 🎨 UI/UX
- Mobile-first responsive design
- CSS variables for consistent theming
- SVG graphics and illustrations
- Interactive navigation with smooth scrolling

---

## Release Notes Format

### Version Number Scheme
- **Major (X.0.0)**: Breaking changes, major milestones
- **Minor (0.X.0)**: New features, backward-compatible
- **Patch (0.0.X)**: Bug fixes, minor improvements

### Change Categories
- 🎨 **Added**: New features
- 🔄 **Changed**: Changes to existing functionality
- 🗑️ **Deprecated**: Soon-to-be removed features
- ❌ **Removed**: Removed features
- 🐛 **Fixed**: Bug fixes
- 🔒 **Security**: Security improvements
- 🗄️ **Database**: Database schema changes
- 📚 **Documentation**: Documentation updates
- ⚡ **Performance**: Performance improvements

---

## Links

- [Repository](https://github.com/sci-bono/sci-bono-aifluency)
- [Issues](https://github.com/sci-bono/sci-bono-aifluency/issues)
- [Pull Requests](https://github.com/sci-bono/sci-bono-aifluency/pulls)
- [Documentation](/Documentation/)

---

**[⬆ back to top](#changelog)**
