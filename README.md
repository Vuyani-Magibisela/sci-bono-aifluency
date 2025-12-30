# Sci-Bono AI Fluency LMS Platform

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![GSAP](https://img.shields.io/badge/GSAP-3.12.2-88CE02?logo=greensock&logoColor=white)](https://greensock.com/gsap/)

A modern, full-featured Learning Management System (LMS) designed to teach artificial intelligence concepts to students in grades 8-12. The platform combines a static Progressive Web App (PWA) frontend with a dynamic PHP/MySQL backend, offering comprehensive course management, interactive assessments, and gamified learning experiences.

---

## 📋 Table of Contents

- [Features](#-features)
- [Technology Stack](#-technology-stack)
- [Architecture](#-architecture)
- [Installation](#-installation)
- [Usage](#-usage)
- [Project Structure](#-project-structure)
- [API Documentation](#-api-documentation)
- [Development](#-development)
- [Phase Completion Status](#-phase-completion-status)
- [Contributing](#-contributing)
- [License](#-license)
- [Contact](#-contact)

---

## ✨ Features

### 🎓 **Learning Management**
- **6 Comprehensive Modules**: AI Foundations, Generative AI, Advanced Search, Responsible AI, Microsoft Copilot, AI Impact
- **45+ Interactive Chapters**: Rich content with SVG graphics, inline examples, and real-world applications
- **Dynamic Content Loading**: Database-driven lesson delivery with bookmark and note-taking support
- **Progress Tracking**: Real-time tracking of lesson completion and course progress

### 📝 **Assessment System**
- **Interactive Quizzes**: 6 module-level assessments with auto-grading
- **Question Randomization**: Randomized question and answer order for academic integrity
- **Multiple Attempts**: Support for retakes with best/latest/average scoring options
- **Quiz Analytics**: Detailed performance metrics and question difficulty analysis
- **Instructor Override**: Manual grading and partial credit assignment

### 🚀 **Project Submission & Grading**
- **File Upload System**: Drag-and-drop interface for PDF, images, and code files
- **Submission History**: Version tracking with multiple submission support
- **Rubric-Based Grading**: Structured grading criteria for consistent evaluation
- **Instructor Dashboard**: Dedicated grading queue with pending submissions

### 🏆 **Gamification & Achievements**
- **16 Achievements**: Unlockable badges across 6 categories (Learning, Mastery, Engagement, Milestones, Special, Loyalty)
- **Points System**: Earn points for completing lessons, quizzes, and projects
- **Leaderboard**: Compete with peers on weekly/monthly/all-time rankings
- **Achievement Tiers**: Bronze, Silver, Gold, Platinum badges
- **Unlock Animations**: Eye-catching GSAP-powered notifications

### 🎨 **Modern UI/UX**
- **GSAP Animations**: Smooth, professional animations across all dashboards
  - Animated counters for statistics
  - Progress bar animations with pulse effects
  - Slide-in/fade-in effects for content
  - Achievement unlock animations with glow effects
- **Responsive Design**: Mobile-first approach, works on all device sizes
- **Role-Based Dashboards**: Customized interfaces for Students, Instructors, and Admins
- **Progressive Web App**: Installable on devices, offline-capable

### 🔐 **User Management & Security**
- **JWT Authentication**: Secure token-based authentication
- **Role-Based Access Control (RBAC)**: Student, Instructor, Admin, Super Admin roles
- **Password Hashing**: bcrypt encryption for secure password storage
- **Token Blacklist**: Secure logout with token invalidation
- **XSS/SQL Injection Protection**: Prepared statements and output escaping

### 📜 **Certification System**
- **Automated Certificate Generation**: PDF certificates on course completion
- **2 Certificate Templates**: Professional designs with verification codes
- **Certificate Verification**: Public verification via unique codes
- **Certificate History**: View and download earned certificates

### 📊 **Admin Features**
- **Content Management**: CRUD operations for courses, modules, lessons, quizzes
- **User Management**: Create, update, deactivate users
- **Enrollment Management**: Assign students to courses
- **Analytics Dashboard**: System-wide statistics and reports
- **Activity Logs**: Track user actions and system events

---

## 🛠 Technology Stack

### **Backend**
- **PHP 8.0+**: Server-side logic with modern OOP patterns
- **MySQL 8.0/MariaDB 10.5+**: Relational database with 34+ tables
- **PDO**: Prepared statements for SQL injection prevention
- **JWT (JSON Web Tokens)**: Stateless authentication

### **Frontend**
- **HTML5**: Semantic markup with 85+ pages
- **CSS3**: Pure CSS with CSS variables for theming (no frameworks)
- **Vanilla JavaScript (ES6+)**: No frameworks, 19 modular JS files
- **GSAP 3.12.2**: GreenSock Animation Platform for smooth animations
- **Font Awesome 6.1.1**: Icon library
- **Quill.js**: Rich text editor for student notes

### **Progressive Web App**
- **Service Worker**: Offline caching strategy
- **Web App Manifest**: Installable on mobile/desktop
- **Cache-First Strategy**: Fast load times with network fallback

### **Development Tools**
- **Composer**: PHP dependency management
- **Git**: Version control
- **Apache/Nginx**: Web server with mod_rewrite

---

## 🏗 Architecture

### **MVC Pattern**
The project follows a Model-View-Controller architecture with a clear separation of concerns:

```
/api                      ← Backend (MVC)
  /controllers            ← Request handlers (14 controllers)
  /models                 ← Data models (16 Active Record models)
  /routes                 ← RESTful API routing
  /middleware             ← Authentication, CORS
  /config                 ← Database, constants
  /migrations             ← Database schema versions
  /utils                  ← Helper classes

/js                       ← Frontend JavaScript (19 files)
/css                      ← Stylesheets (2 files)
/images                   ← Public images
*.html                    ← Frontend views (85 files)
```

### **Database Schema**
- **34+ Tables**: Users, courses, modules, lessons, quizzes, projects, achievements, certificates
- **250+ Columns**: Comprehensive data model with relationships
- **17 Migrations**: Version-controlled schema evolution

### **API Design**
- **RESTful Architecture**: 60+ endpoints following REST principles
- **JSON Responses**: Standardized response format
- **HTTP Status Codes**: Proper use of 200, 201, 400, 401, 403, 404, 500
- **Error Handling**: Consistent error messages and logging

---

## 📦 Installation

### **Prerequisites**
- PHP 8.0 or higher
- MySQL 8.0 / MariaDB 10.5+
- Apache/Nginx with mod_rewrite enabled
- Composer (optional, for future dependencies)

### **Step 1: Clone the Repository**
```bash
cd /var/www/html
git clone https://github.com/your-org/sci-bono-aifluency.git
cd sci-bono-aifluency
```

### **Step 2: Database Setup**
1. Create a MySQL database:
```sql
CREATE DATABASE ai_fluency_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the database schema:
```bash
mysql -u root -p ai_fluency_lms < api/migrations/000_full_schema.sql
```

3. Run migrations in order (if needed):
```bash
# Execute each migration file in /api/migrations/ sequentially
for file in api/migrations/*.sql; do
    mysql -u root -p ai_fluency_lms < "$file"
done
```

### **Step 3: Configure Database Connection**
Edit `/api/config/database.php`:
```php
<?php
return [
    'host' => 'localhost',
    'dbname' => 'ai_fluency_lms',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4'
];
```

### **Step 4: Set File Permissions**
```bash
# Make uploads directory writable
mkdir -p uploads
chmod 755 uploads

# Make logs directory writable (if exists)
mkdir -p api/logs
chmod 755 api/logs
```

### **Step 5: Configure Web Server**

#### **Apache (.htaccess)**
Ensure `.htaccess` files are present in root and `/api/`:

**Root `.htaccess`**:
```apache
RewriteEngine On
RewriteBase /

# Redirect API requests to /api/
RewriteRule ^api/(.*)$ api/index.php [L,QSA]

# Serve static files directly
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
```

**`/api/.htaccess`**:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]
```

#### **Nginx Configuration**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/sci-bono-aifluency;
    index index.html;

    # API routing
    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    # Static files
    location / {
        try_files $uri $uri/ =404;
    }

    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### **Step 6: Create Admin User**
```bash
php api/utils/create_admin.php
# Follow prompts to create your first admin account
```

### **Step 7: Test Installation**
Navigate to `http://localhost/sci-bono-aifluency/` in your browser.

---

## 🎯 Usage

### **For Students**
1. **Register/Login**: Create an account or login at `/login.html`
2. **Browse Courses**: View available modules on the dashboard
3. **Start Learning**: Click on a module to see chapters
4. **Take Notes**: Use the built-in note-taking feature during lessons
5. **Bookmark Lessons**: Mark lessons for later review
6. **Complete Quizzes**: Test your knowledge after each module
7. **Submit Projects**: Upload project files for instructor review
8. **Track Progress**: Monitor completion percentage on your dashboard
9. **Earn Achievements**: Unlock badges and climb the leaderboard
10. **Download Certificates**: Download PDF certificates on course completion

### **For Instructors**
1. **Login**: Access instructor dashboard at `/instructor-dashboard.html`
2. **View Courses**: See courses you're teaching
3. **Monitor Students**: Track student enrollment and progress
4. **Grade Submissions**: Review and grade pending quizzes and projects
5. **Provide Feedback**: Leave comments on student submissions
6. **View Analytics**: Access performance metrics and class statistics

### **For Administrators**
1. **Login**: Access admin dashboard at `/admin-dashboard.html`
2. **Manage Content**: Create/edit courses, modules, lessons, quizzes
3. **User Management**: Add users, assign roles, manage enrollments
4. **System Analytics**: View platform-wide statistics
5. **Content Publishing**: Publish/unpublish courses and lessons

---

## 📁 Project Structure

```
sci-bono-aifluency/
├── api/                                 # Backend (MVC Architecture)
│   ├── controllers/                     # 14 controllers
│   │   ├── AuthController.php           # Authentication & JWT
│   │   ├── UserController.php           # User management
│   │   ├── CourseController.php         # Course CRUD
│   │   ├── ModuleController.php         # Module management
│   │   ├── LessonController.php         # Lesson delivery & progress
│   │   ├── QuizController.php           # Quiz management & submission
│   │   ├── ProjectController.php        # Project submissions
│   │   ├── EnrollmentController.php     # Course enrollments
│   │   ├── CertificateController.php    # Certificate generation
│   │   ├── AchievementController.php    # Gamification system
│   │   ├── GradingController.php        # Grading queue
│   │   ├── FileUploadController.php     # File handling
│   │   ├── NotesController.php          # Student notes
│   │   └── BookmarksController.php      # Lesson bookmarks
│   ├── models/                          # 16 Active Record models
│   │   ├── BaseModel.php                # PDO abstraction
│   │   ├── User.php                     # User model
│   │   ├── Course.php                   # Course model
│   │   ├── Module.php                   # Module model
│   │   ├── Lesson.php                   # Lesson model
│   │   ├── LessonProgress.php           # Progress tracking
│   │   ├── Quiz.php                     # Quiz model
│   │   ├── QuizQuestion.php             # Question bank
│   │   ├── QuizAttempt.php              # Quiz submissions
│   │   ├── Project.php                  # Project assignments
│   │   ├── ProjectSubmission.php        # Project submissions
│   │   ├── Enrollment.php               # User-course relationships
│   │   ├── Certificate.php              # Certificate model
│   │   ├── Achievement.php              # Achievement model
│   │   ├── StudentNote.php              # Notes model
│   │   └── Bookmark.php                 # Bookmarks model
│   ├── routes/
│   │   └── api.php                      # 60+ RESTful routes
│   ├── middleware/
│   │   ├── AuthMiddleware.php           # JWT validation
│   │   └── CorsMiddleware.php           # CORS handling
│   ├── config/
│   │   ├── database.php                 # DB configuration
│   │   └── constants.php                # App constants
│   ├── utils/
│   │   ├── JWTHandler.php               # JWT encode/decode
│   │   └── Validator.php                # Input validation
│   ├── migrations/                      # 17 SQL migration files
│   ├── logs/                            # Application logs
│   ├── index.php                        # API entry point
│   └── .htaccess                        # API routing rules
│
├── css/
│   ├── styles.css                       # Global styles (3,500+ lines)
│   └── stylesModules.css                # Module-specific styles
│
├── js/                                  # 19 JavaScript modules
│   ├── api.js                           # API wrapper (fetch)
│   ├── auth.js                          # Authentication module
│   ├── storage.js                       # LocalStorage abstraction
│   ├── header-template.js               # Dynamic header
│   ├── footer-template.js               # Dynamic footer
│   ├── animations.js                    # GSAP animations library ⭐ NEW
│   ├── dashboard.js                     # Student dashboard
│   ├── instructor.js                    # Instructor dashboard
│   ├── admin.js                         # Admin features
│   ├── admin-courses.js                 # Course management UI
│   ├── admin-modules.js                 # Module management UI
│   ├── admin-lessons.js                 # Lesson management UI
│   ├── admin-quizzes.js                 # Quiz management UI
│   ├── achievements.js                  # Achievement system
│   ├── quiz-history.js                  # Quiz history UI
│   ├── project-upload.js                # Project submission UI
│   ├── instructor-grading.js            # Grading queue UI
│   ├── content-loader.js                # Dynamic content loading
│   └── script.js                        # Legacy PWA scripts
│
├── images/                              # Icons, logos, graphics
├── uploads/                             # User-uploaded files
│
├── *.html                               # 85 HTML pages
│   ├── index.html                       # Landing page
│   ├── login.html                       # Login page
│   ├── signup.html                      # Registration
│   ├── student-dashboard.html           # Student dashboard
│   ├── instructor-dashboard.html        # Instructor dashboard
│   ├── admin-dashboard.html             # Admin dashboard
│   ├── profile.html                     # User profile
│   ├── achievements.html                # Achievements page
│   ├── certificates.html                # Certificates page
│   ├── quiz-history.html                # Quiz history
│   ├── project-submit.html              # Project submission
│   ├── instructor-grading.html          # Grading interface
│   ├── module-dynamic.html              # Dynamic module page
│   ├── lesson-dynamic.html              # Dynamic lesson page
│   ├── quiz-dynamic.html                # Dynamic quiz page
│   ├── admin-courses.html               # Course management
│   ├── admin-modules.html               # Module management
│   ├── admin-lessons.html               # Lesson management
│   ├── admin-quizzes.html               # Quiz management
│   ├── module[1-6].html                 # 6 module overview pages
│   ├── chapter[1-12].html               # 45+ chapter pages
│   └── module[1-6]Quiz.html             # 6 quiz pages
│
├── Documentation/                       # Comprehensive documentation
│   ├── README.md                        # Documentation overview
│   ├── DOCUMENTATION_PROGRESS.md        # Progress tracker
│   ├── MVC_TRANSFORMATION_PLAN.md       # Transformation roadmap
│   ├── ARCHITECTURE_DECISION.md         # Architecture rationale
│   ├── PHASE[1-6]_COMPLETE.md           # Phase completion summaries
│   └── 01-Technical/                    # Technical documentation
│
├── service-worker.js                    # PWA service worker
├── manifest.json                        # PWA manifest
├── .htaccess                            # Root routing rules
├── .gitignore                           # Git ignore rules
└── README.md                            # This file
```

---

## 🔌 API Documentation

### **Base URL**
```
http://localhost/sci-bono-aifluency/api
```

### **Authentication**
All protected endpoints require a JWT token in the `Authorization` header:
```
Authorization: Bearer <your-jwt-token>
```

### **Endpoints**

#### **Authentication**
```
POST   /api/auth/register          # Register new user
POST   /api/auth/login             # Login and get JWT
POST   /api/auth/logout            # Logout and blacklist token
POST   /api/auth/refresh           # Refresh JWT token
GET    /api/auth/me                # Get current user info
```

#### **Users**
```
GET    /api/users                  # Get all users (admin only)
GET    /api/users/:id              # Get user by ID
PUT    /api/users/:id              # Update user
DELETE /api/users/:id              # Delete user (admin only)
GET    /api/users/me/stats         # Get current user statistics
```

#### **Courses**
```
GET    /api/courses                # Get all courses
GET    /api/courses/:id            # Get course by ID
POST   /api/courses                # Create course (admin)
PUT    /api/courses/:id            # Update course (admin)
DELETE /api/courses/:id            # Delete course (admin)
GET    /api/courses/enrolled       # Get user's enrolled courses
```

#### **Modules**
```
GET    /api/modules/:id            # Get module by ID
GET    /api/modules/:id/lessons    # Get all lessons in module
POST   /api/modules                # Create module (admin)
PUT    /api/modules/:id            # Update module (admin)
DELETE /api/modules/:id            # Delete module (admin)
```

#### **Lessons**
```
GET    /api/lessons/:id            # Get lesson by ID
POST   /api/lessons/:id/start      # Mark lesson as started
POST   /api/lessons/:id/complete   # Mark lesson as completed
GET    /api/lessons/:id/progress   # Get lesson progress
POST   /api/lessons                # Create lesson (admin)
PUT    /api/lessons/:id            # Update lesson (admin)
DELETE /api/lessons/:id            # Delete lesson (admin)
```

#### **Quizzes**
```
GET    /api/quizzes/:id            # Get quiz by ID
POST   /api/quizzes/:id/submit     # Submit quiz attempt
GET    /api/quizzes/attempts/recent # Get recent attempts
GET    /api/quizzes/attempts/:id   # Get specific attempt
POST   /api/quizzes                # Create quiz (admin)
PUT    /api/quizzes/:id            # Update quiz (admin)
DELETE /api/quizzes/:id            # Delete quiz (admin)
```

#### **Projects**
```
GET    /api/projects/:id           # Get project by ID
POST   /api/projects/:id/submit    # Submit project
GET    /api/projects/submissions/:id # Get submission details
POST   /api/projects/submissions/:id/grade # Grade submission (instructor)
GET    /api/projects/pending-grading # Get pending submissions (instructor)
```

#### **Enrollments**
```
GET    /api/enrollments            # Get all enrollments
POST   /api/enrollments            # Create enrollment
GET    /api/enrollments/:id        # Get enrollment details
GET    /api/enrollments/:id/progress # Calculate course progress
DELETE /api/enrollments/:id        # Delete enrollment (admin)
```

#### **Certificates**
```
GET    /api/certificates/my-certificates # Get user's certificates
GET    /api/certificates/:id       # Get certificate by ID
POST   /api/certificates/request   # Request certificate generation
GET    /api/certificates/verify/:code # Verify certificate by code
```

#### **Achievements**
```
GET    /api/achievements           # Get all achievements
GET    /api/achievements/user      # Get user's unlocked achievements
GET    /api/achievements/points    # Get user's achievement points
GET    /api/achievements/leaderboard # Get leaderboard
POST   /api/achievements/check     # Check for new achievements
```

### **Response Format**

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

**Error Response (400/401/403/404/500)**:
```json
{
  "success": false,
  "message": "Error message",
  "error": "Detailed error description"
}
```

---

## 💻 Development

### **Local Development Server**
```bash
# Using PHP built-in server
php -S localhost:8000

# Using Apache/Nginx
# Access at http://localhost/sci-bono-aifluency/
```

### **Database Migrations**
To create a new migration:
```bash
# Create file: api/migrations/018_your_migration_name.sql
# Execute migration:
mysql -u root -p ai_fluency_lms < api/migrations/018_your_migration_name.sql
```

### **Adding New API Endpoints**
1. Create method in appropriate controller (`/api/controllers/`)
2. Add route in `/api/routes/api.php`
3. Test endpoint with Postman/curl
4. Document in this README

### **Adding GSAP Animations**
All animations are centralized in `/js/animations.js`. Available functions:
```javascript
// Counter animations
Animations.animateCounter(element, value, options);
Animations.animatePercentage(element, percentage, options);

// Progress animations
Animations.animateProgressBar(bar, percentage, options);
Animations.animateCircularProgress(circle, percentage, options);

// Entrance animations
Animations.fadeInStagger(elements, options);
Animations.slideIn(elements, direction, options);

// Special effects
Animations.achievementUnlock(element, options);
Animations.pulse(element, options);
Animations.shake(element, options);
```

### **Testing**
```bash
# Manual testing checklist:
# 1. Test registration/login flow
# 2. Test course enrollment
# 3. Test lesson progress tracking
# 4. Test quiz submission
# 5. Test project upload
# 6. Test achievement unlocking
# 7. Test certificate generation
# 8. Test admin content management
```

### **Code Style**
- **PHP**: PSR-4 autoloading, camelCase methods, PascalCase classes
- **JavaScript**: ES6+ syntax, camelCase variables, `const`/`let` (no `var`)
- **CSS**: BEM-like naming, CSS variables for theming
- **HTML**: Semantic HTML5, accessibility attributes (ARIA)

---

## 📊 Phase Completion Status

The platform was built in 6 phases following the MVC Transformation Plan:

### ✅ **Phase 1: Database Design & Setup** (100%)
- 34+ tables created
- 17 migration files
- Full schema with relationships

### ✅ **Phase 2: MVC Architecture Setup** (100%)
- 14 controllers implemented
- 16 Active Record models
- RESTful routing with 60+ endpoints

### ✅ **Phase 3: Authentication & Authorization** (100%)
- JWT authentication
- Role-based access control (RBAC)
- Token blacklist for secure logout
- Password reset flow

### ✅ **Phase 4: Dashboard Development** (95%)
- Student dashboard with animations ⭐
- Instructor dashboard with grading queue ⭐
- Admin dashboard with content management
- GSAP animations integrated ⭐ NEW

### ✅ **Phase 5: Content Migration & Management** (95%)
- Admin content management UI
- Dynamic lesson/module/quiz loading
- Breadcrumb navigation
- Student notes & bookmarks
- Quiz randomization

### ✅ **Phase 6: Quiz Tracking & Grading** (100%)
- Enhanced quiz tracking (11 new columns)
- Instructor grading override
- Certificate system (2 templates)
- Achievement badges (16 achievements, 6 categories)
- Leaderboard

### ⚠️ **Remaining Work** (Optional Enhancements)
- Profile editing interface (60% complete)
- Static content migration (45+ chapters to database)
- Messaging/notification system
- Advanced analytics & reporting
- GDPR compliance tools
- Automated testing suite

**Overall Completion**: **~75%** of full MVC Transformation Plan

---

## 🤝 Contributing

### **How to Contribute**
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature-name`)
3. Commit your changes (`git commit -m 'Add some feature'`)
4. Push to the branch (`git push origin feature/your-feature-name`)
5. Open a Pull Request

### **Contribution Guidelines**
- Follow existing code style and conventions
- Write clear commit messages
- Update documentation for new features
- Test your changes thoroughly
- Update `DOCUMENTATION_PROGRESS.md` with your changes

### **Documentation Requirements**
**ALL code changes MUST include documentation updates**. Before submitting a PR:
- Update relevant files in `/Documentation/`
- Add entry to `DOCUMENTATION_PROGRESS.md` change log
- Include code examples where applicable
- Update this README if API/features changed

See `/Documentation/README.md` for full documentation standards.

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 📞 Contact

**Sci-Bono Discovery Centre**
- Website: [https://www.sci-bono.co.za](https://www.sci-bono.co.za)
- Email: info@sci-bono.co.za

**Project Maintainers**
- Vuyani Magibisela - ICT Trainer, Web/App Developer
- Email: vuyani@sci-bono.co.za

---

## 🙏 Acknowledgments

- **GSAP (GreenSock)**: For the amazing animation library
- **Font Awesome**: For comprehensive icon set
- **Quill.js**: For rich text editing capabilities
- **Sci-Bono Discovery Centre**: For supporting AI education initiatives

---

## 📸 Screenshots

### Student Dashboard
![Student Dashboard](images/screenshots/student-dashboard.png)
*Animated counters, progress tracking, and course overview*

### Achievements System
![Achievements](images/screenshots/achievements.png)
*Gamified learning with badges, points, and leaderboard*

### Instructor Grading Queue
![Grading Queue](images/screenshots/instructor-grading.png)
*Streamlined grading workflow with pending submissions*

### Admin Content Management
![Admin Panel](images/screenshots/admin-courses.png)
*Comprehensive content management interface*

---

## 🚀 Quick Start

```bash
# Clone the repository
git clone https://github.com/your-org/sci-bono-aifluency.git
cd sci-bono-aifluency

# Set up database
mysql -u root -p < api/migrations/000_full_schema.sql

# Configure database connection
nano api/config/database.php

# Create admin user
php api/utils/create_admin.php

# Start development server
php -S localhost:8000

# Visit http://localhost:8000 in your browser
```

---

## 📚 Additional Resources

- **Documentation**: See `/Documentation/` directory
- **MVC Transformation Plan**: `/Documentation/MVC_TRANSFORMATION_PLAN.md`
- **Architecture Decision**: `/Documentation/ARCHITECTURE_DECISION.md`
- **Phase Summaries**: `/Documentation/PHASE[1-6]_COMPLETE.md`
- **API Reference**: Full API docs in `/Documentation/01-Technical/02-Code-Reference/`
- **Database Schema**: `/Documentation/01-Technical/03-Database/schema-design.md`

---

<div align="center">

**Made with ❤️ for AI Education**

[⭐ Star this repo](https://github.com/your-org/sci-bono-aifluency) | [🐛 Report Bug](https://github.com/your-org/sci-bono-aifluency/issues) | [💡 Request Feature](https://github.com/your-org/sci-bono-aifluency/issues)

</div>
