# MVC Transformation Plan: AI Fluency LMS Platform

## Executive Summary
Transform the static PWA (58 HTML files, 6 modules, 45+ chapters, 6 quizzes) into a dynamic MVC PHP/MySQL platform with user management, role-based dashboards, quiz/project tracking, and profile building.

## Phase 1: Database Design & Setup (Foundation)

### 1.1 Database Schema
**Core Tables:**
- `users` - User authentication (email, password_hash, role, status, created_at)
- `user_profiles` - Extended profiles (bio, avatar, skills, achievements, social_links)
- `roles` - Role definitions (student, instructor, admin, super_admin)
- `modules` - Course modules (6 modules from current structure)
- `chapters` - Chapter content (45+ chapters from HTML files)
- `quizzes` - Quiz metadata (6 quizzes + grading settings)
- `quiz_questions` - Questions (extracted from current JS quiz arrays)
- `quiz_attempts` - User quiz submissions and scores
- `projects` - Project assignments per module
- `project_submissions` - User project uploads and grades
- `user_progress` - Chapter completion tracking
- `enrollments` - User-module enrollment relationships
- `instructor_assignments` - Instructor-module mapping
- `notifications` - System notifications
- `activity_log` - User activity tracking

### 1.2 Database Configuration
- Create `config/database.php` with PDO connection
- Implement prepared statements for security
- Set up migration system for version control

## Phase 2: MVC Architecture Setup

### 2.1 Directory Structure

**⚠️ ARCHITECTURE UPDATE: We use `/api` instead of `/app`**
See `/Documentation/ARCHITECTURE_DECISION.md` for full rationale.

```
/api                                 ← Backend Application (MVC)
  /controllers                       ← Request handlers
    AuthController.php
    UserController.php
    CourseController.php
    EnrollmentController.php
    ModuleController.php
    LessonController.php
    QuizController.php
    ProjectController.php
    CertificateController.php
  /models                            ← Data models (Active Record)
    BaseModel.php
    User.php
    Course.php
    Module.php
    Lesson.php
    LessonProgress.php
    Quiz.php
    QuizAttempt.php
    Project.php
    ProjectSubmission.php
    Enrollment.php
    Certificate.php
  /views                             ← Server-rendered templates
    /emails                          ← Email templates
    /pdf                             ← PDF templates (certificates, reports)
    /reports                         ← Report templates
  /routes                            ← API routing definitions
    api.php
  /middleware                        ← Request middleware
    AuthMiddleware.php
    CorsMiddleware.php
  /config                            ← Configuration
    database.php
    constants.php
  /utils                             ← Utility classes
    JWTHandler.php
    Validator.php
  /migrations                        ← Database migrations
    001_create_users.sql
    002_create_courses.sql
    ...
  /tests                             ← Backend tests
    test_auth.php
    test_users.php
    run_all_tests.sh
  /logs                              ← Application logs
  /vendor                            ← Composer dependencies
  index.php                          ← API front controller
  .htaccess                          ← API routing rules
  composer.json
  .env

/css                                 ← Public stylesheets
  styles.css
  stylesModules.css

/js                                  ← Public JavaScript
  storage.js                         ← LocalStorage abstraction
  api.js                             ← API wrapper
  auth.js                            ← Authentication module
  header-template.js                 ← Dynamic header
  script.js                          ← Legacy scripts

/images                              ← Public images
/assets                              ← Additional assets
/scripts                             ← Build/deployment scripts
/Documentation                       ← Project documentation

*.html                               ← Frontend views (PWA pages)
  index.html
  login.html
  signup.html
  student-dashboard.html
  instructor-dashboard.html
  admin-dashboard.html
  profile.html
  module1.html
  chapter1.html
  ...

service-worker.js                    ← PWA service worker
manifest.json                        ← PWA manifest
.htaccess                            ← Root routing rules
```

### 2.2 Routing System

**REST API Endpoints** (Backend - `/api/*`):
- Authentication: `/api/auth/register`, `/api/auth/login`, `/api/auth/logout`, `/api/auth/refresh`
- Users: `/api/users`, `/api/users/:id`
- Courses: `/api/courses`, `/api/courses/:id`
- Modules: `/api/modules/:id`, `/api/modules/:id/lessons`
- Lessons: `/api/lessons/:id`, `/api/lessons/:id/complete`
- Quizzes: `/api/quizzes/:id`, `/api/quizzes/:id/submit`
- Projects: `/api/projects/:id/submit`, `/api/projects/submissions/:id/grade`
- Enrollments: `/api/enrollments`, `/api/enrollments/:id`
- Certificates: `/api/certificates/:id`

**Frontend Routes** (HTML Pages):
- Landing: `/index.html`
- Auth: `/login.html`, `/signup.html`
- Dashboards: `/student-dashboard.html`, `/instructor-dashboard.html`, `/admin-dashboard.html`
- Profile: `/profile.html`, `/profile-edit.html`
- Modules: `/module1.html`, `/module2.html`, ...
- Chapters: `/chapter1.html`, `/chapter1_17.html`, ...
- Quizzes: `/module1Quiz.html`, `/module2Quiz.html`, ...

**Configuration**:
- API: `/api/.htaccess` for RESTful routing
- Root: `/.htaccess` for static file serving
- Route middleware: Authentication via JWT tokens
- CORS middleware: Cross-origin request handling

## Phase 3: Authentication & Authorization

### 3.1 User System
**Features:**
- Registration with email verification
- Login with "Remember Me" option
- Password reset flow
- Session management with CSRF protection
- Role-based access control (RBAC)

### 3.2 Access Levels
- **Student**: View content, take quizzes, submit projects, view own progress
- **Instructor**: All student features + grade projects/quizzes for assigned modules
- **Admin**: Manage users, modules, content, view analytics
- **Super Admin**: Full system access + user role management

## Phase 4: Dashboard Development

### 4.1 Student Dashboard
- Progress overview (% complete per module)
- Recent quiz scores chart (GSAP animated)
- Upcoming/pending projects
- Achievement badges
- Learning streak calendar
- Quick access to continue learning

### 4.2 Instructor Dashboard
- Assigned modules overview
- Pending grading queue (quizzes/projects)
- Student performance analytics
- Class roster management
- Bulk grading interface
- Communication tools

### 4.3 Admin Dashboard
- User management (CRUD operations)
- Module/chapter content management
- System-wide analytics (GSAP charts)
- Enrollment management
- Content publishing workflow
- Activity logs

### 4.4 Super Admin Dashboard
- All admin features +
- Role assignment interface
- System configuration
- Database backup/restore
- User impersonation (for support)

## Phase 5: Content Migration & Management

### 5.1 HTML Content Migration
**Process:**
- Extract chapter content from 45+ HTML files
- Store in `chapters` table with module relationships
- Preserve SVG graphics and inline styles
- Maintain chapter navigation structure
- Create content editor for admins (rich text)

### 5.2 Quiz System Overhaul
**Features:**
- Extract quiz data from JavaScript arrays to database
- Dynamic quiz generation from DB
- Real-time progress tracking
- Multiple attempt support with best/latest/average scoring
- Timed quiz option
- Question randomization
- Immediate feedback vs. submit-all modes
- Detailed results with explanations
- Quiz analytics per user/module

### 5.3 Module Structure
- Preserve 6-module hierarchy
- Chapter prerequisites (must complete Ch1 before Ch2)
- Module completion certificates (PDF generation)
- Module-level quizzes as final assessments

## Phase 6: Quiz Tracking & Grading System

### 6.1 Auto-Grading
- Multiple choice: Automatic scoring
- Store correct/incorrect answers
- Calculate percentage, letter grade
- Track time spent per quiz
- Generate grade reports

### 6.2 Instructor Grading Override
- Manual review interface
- Partial credit assignment
- Feedback comments
- Grade appeals system

### 6.3 Analytics
- Student performance trends
- Question difficulty analysis (success rate)
- Time-on-quiz metrics
- Comparison to class average

## Phase 7: Project System

### 7.1 Project Assignments
- Per-module project definitions
- File upload support (PDF, images, code files)
- Rubric-based grading criteria
- Deadline management
- Late submission penalties (configurable)

### 7.2 Submission & Grading
- Drag-drop upload interface
- Version history (multiple submissions)
- Instructor grading interface with rubric
- Inline comments/annotations
- Peer review option (student-to-student)

### 7.3 Project Showcase
- Public portfolio option
- Featured projects gallery
- Social sharing integration

## Phase 8: Profile Building & Viewing

### 8.1 User Profiles
**Elements:**
- Avatar upload/cropping
- Bio/introduction
- Skills & interests (tags)
- Learning goals
- Completed modules showcase
- Quiz score statistics
- Project portfolio
- Achievement badges
- Certificates earned
- Social links

### 8.2 Privacy Controls
- Public/private profile toggle
- Selective visibility (show/hide sections)
- Profile URL customization

### 8.3 Gamification
- XP points system
- Leaderboards (weekly/monthly/all-time)
- Achievement badges (First Quiz, Perfect Score, Fast Learner, etc.)
- Learning streaks
- GSAP animations for unlocking achievements

## Phase 9: Frontend Integration

### 9.1 CSS Migration
- Keep existing design system (CSS variables intact)
- No CSS frameworks (as requested)
- Responsive design maintained
- Add dashboard-specific styles
- Profile card components

### 9.2 JavaScript Enhancements
**GSAP Animations:**
- Dashboard metric counters (animated numbers)
- Progress bar animations
- Chart transitions (quiz scores over time)
- Achievement unlock animations
- Page transitions
- Parallax scrolling effects
- SVG morph animations

**Other Libraries:**
- Chart.js for analytics visualization
- Dropzone.js for file uploads
- SortableJS for drag-drop interfaces
- Quill.js for rich text editing (admin)

### 9.3 Progressive Enhancement
- Maintain core functionality without JS
- AJAX for dynamic content loading
- Real-time notifications (WebSockets optional)
- Autosave for quiz progress

## Phase 10: Additional Features

### 10.1 Search & Filtering
- Global content search
- Filter modules by difficulty
- Search quizzes/projects
- User directory search

### 10.2 Communication
- Instructor-student messaging
- Announcement system
- Email notifications (quiz graded, new content, etc.)
- In-app notification center

### 10.3 Reporting
- Student progress reports (PDF export)
- Transcript generation
- Instructor grade books
- Admin analytics dashboards

### 10.4 PWA Preservation
- Maintain offline capability for content viewing
- Service worker for caching authenticated content
- Install prompt for logged-in users
- Sync quiz attempts when back online

## Phase 11: Security Implementation

### 11.1 Security Measures
- Password hashing (bcrypt)
- SQL injection prevention (prepared statements)
- XSS protection (output escaping)
- CSRF tokens on all forms
- Rate limiting on auth endpoints
- Secure session management
- File upload validation
- Input sanitization

### 11.2 Data Protection
- GDPR compliance tools (data export/deletion)
- Privacy policy integration
- Cookie consent management
- Audit logs for sensitive operations

## Phase 12: Testing & Deployment

### 12.1 Testing Strategy
- Unit tests for models
- Integration tests for controllers
- User acceptance testing (UAT)
- Performance testing (page load times)
- Security audit

### 12.2 Migration Strategy
- Run both systems in parallel initially
- Data migration scripts
- User notification of new features
- Training materials for instructors/admins

### 12.3 Deployment Checklist
- Environment configuration (.env files)
- Database optimization (indexes)
- Caching strategy (Redis/Memcached optional)
- CDN for static assets
- Error logging (Monolog)
- Backup automation

## Implementation Priority

**High Priority (MVP):**
1. Database setup
2. MVC structure
3. Authentication system
4. Student dashboard
5. Quiz system with grading
6. Basic profile viewing

**Medium Priority:**
7. Project system
8. Instructor dashboard
9. Progress tracking
10. GSAP animations
11. Admin dashboard

**Lower Priority:**
12. Advanced analytics
13. Messaging system
14. Peer review
15. Public profiles
16. Super admin features

## Estimated Timeline
- Phase 1-3: 2-3 weeks (Foundation)
- Phase 4-6: 3-4 weeks (Core Features)
- Phase 7-9: 2-3 weeks (Extended Features)
- Phase 10-12: 2 weeks (Polish & Deploy)

**Total: 9-12 weeks for full implementation**

## Technical Stack Summary
- **Backend**: PHP 8.0+ (vanilla, no frameworks)
- **Database**: MySQL 8.0/MariaDB
- **Frontend**: HTML5, CSS3 (existing design), Vanilla JS
- **Animations**: GSAP 3.x
- **Additional Libraries**: Chart.js, Dropzone.js, Quill.js, SortableJS
- **Server**: Apache/Nginx with mod_rewrite
- **Security**: bcrypt, PDO prepared statements, CSRF tokens

## Next Steps for Planning

### Areas Requiring Further Detail:
1. **Database Schema Refinement** - Full table structures with field types, indexes, foreign keys
2. **API Endpoints** - Define all AJAX endpoints for dynamic features
3. **File Structure Detail** - Complete file listing for MVC architecture
4. **Migration Scripts** - Strategy for extracting content from existing HTML files
5. **UI/UX Wireframes** - Dashboard layouts and user flows
6. **Grading Rubric System** - Detailed specification for project grading
7. **Notification System** - Event triggers and delivery methods
8. **Performance Optimization** - Caching strategies and database optimization
9. **Accessibility Compliance** - WCAG 2.1 AA standards
10. **Mobile App Consideration** - Future native app development plan
