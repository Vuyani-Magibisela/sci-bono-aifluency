# Contributing to Sci-Bono AI Fluency LMS

Thank you for considering contributing to the Sci-Bono AI Fluency LMS platform! This document provides guidelines and instructions for contributing to the project.

---

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Documentation Requirements](#documentation-requirements)
- [Pull Request Process](#pull-request-process)
- [Testing Guidelines](#testing-guidelines)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)

---

## 📜 Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inclusive environment for all contributors. We expect all participants to:

- Use welcoming and inclusive language
- Be respectful of differing viewpoints and experiences
- Gracefully accept constructive criticism
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Harassment, discriminatory language, or personal attacks
- Publishing others' private information without permission
- Trolling, insulting/derogatory comments, and personal or political attacks
- Other conduct which could reasonably be considered inappropriate

---

## 🚀 Getting Started

### Prerequisites

Before contributing, ensure you have:

1. **Development Environment**:
   - PHP 8.0+
   - MySQL 8.0 / MariaDB 10.5+
   - Apache/Nginx with mod_rewrite
   - Git
   - Text editor (VS Code, Sublime, etc.)

2. **Project Knowledge**:
   - Read the [README.md](README.md)
   - Review the [MVC Transformation Plan](Documentation/MVC_TRANSFORMATION_PLAN.md)
   - Understand the [Architecture Decision](Documentation/ARCHITECTURE_DECISION.md)

3. **Fork and Clone**:
   ```bash
   # Fork the repository on GitHub
   # Clone your fork
   git clone https://github.com/YOUR-USERNAME/sci-bono-aifluency.git
   cd sci-bono-aifluency

   # Add upstream remote
   git remote add upstream https://github.com/sci-bono/sci-bono-aifluency.git
   ```

4. **Set Up Development Database**:
   ```bash
   mysql -u root -p -e "CREATE DATABASE ai_fluency_lms_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p ai_fluency_lms_dev < api/migrations/000_full_schema.sql
   ```

---

## 🔄 Development Workflow

### 1. Create a Feature Branch

```bash
# Update your fork with latest upstream changes
git checkout main
git fetch upstream
git merge upstream/main

# Create a feature branch
git checkout -b feature/your-feature-name
# OR for bug fixes
git checkout -b fix/bug-description
```

**Branch Naming Conventions**:
- `feature/feature-name` - New features
- `fix/bug-description` - Bug fixes
- `docs/update-description` - Documentation updates
- `refactor/component-name` - Code refactoring
- `test/test-description` - Test additions
- `chore/task-description` - Maintenance tasks

### 2. Make Your Changes

- Write clean, readable code
- Follow existing code style and conventions
- Add comments for complex logic
- Keep commits focused and atomic

### 3. Commit Your Changes

```bash
# Stage changes
git add .

# Commit with descriptive message
git commit -m "feat: Add GSAP animations to student dashboard

- Implement animated counters for statistics
- Add progress bar animations with pulse effects
- Create slide-in effects for quiz history
- Update dashboard.js to use Animations library

Closes #123"
```

**Commit Message Format**:
```
<type>: <subject>

<body>

<footer>
```

**Types**:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding/updating tests
- `chore`: Maintenance tasks

### 4. Push to Your Fork

```bash
git push origin feature/your-feature-name
```

### 5. Create Pull Request

- Go to your fork on GitHub
- Click "New Pull Request"
- Select your feature branch
- Fill out the PR template
- Submit for review

---

## 📝 Coding Standards

### PHP Code Style

#### PSR-4 Autoloading
```php
namespace App\Controllers;

use App\Models\User;
use App\Utils\Validator;

class UserController {
    // Class implementation
}
```

#### Naming Conventions
- **Classes**: PascalCase (`UserController`, `BaseModel`)
- **Methods**: camelCase (`getUserById`, `createCourse`)
- **Variables**: camelCase (`$userId`, `$courseData`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_ATTEMPTS`, `DEFAULT_ROLE`)

#### Method Documentation
```php
/**
 * Get user by ID
 *
 * @param int $userId User ID
 * @return array|null User data or null if not found
 * @throws Exception If database error occurs
 */
public function getUserById($userId) {
    // Implementation
}
```

#### Security Best Practices
```php
// ✅ ALWAYS use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);

// ❌ NEVER use string concatenation
$query = "SELECT * FROM users WHERE id = " . $userId; // SQL INJECTION!

// ✅ ALWAYS escape HTML output
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// ✅ ALWAYS validate input
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new InvalidArgumentException('Invalid email');
}
```

### JavaScript Code Style

#### ES6+ Syntax
```javascript
// ✅ Use const/let, NOT var
const API_BASE = '/api';
let currentUser = null;

// ✅ Use arrow functions
const fetchUser = async (userId) => {
    const response = await fetch(`${API_BASE}/users/${userId}`);
    return response.json();
};

// ✅ Use template literals
console.log(`User ${user.name} logged in at ${new Date()}`);

// ✅ Use destructuring
const { id, name, email } = user;
```

#### Naming Conventions
- **Variables**: camelCase (`userId`, `courseData`)
- **Constants**: UPPER_SNAKE_CASE (`API_BASE_URL`, `MAX_RETRIES`)
- **Functions**: camelCase (`getUserData`, `handleSubmit`)
- **Classes**: PascalCase (`StudentDashboard`, `Animations`)

#### Function Documentation
```javascript
/**
 * Animate counter from 0 to target value
 * @param {string|HTMLElement} element - Element selector or DOM element
 * @param {number} endValue - Target number
 * @param {object} options - Animation options
 * @param {number} options.duration - Animation duration in seconds
 * @param {string} options.suffix - Suffix to append (e.g., '%', ' pts')
 */
animateCounter(element, endValue, options = {}) {
    // Implementation
}
```

#### Async/Await Pattern
```javascript
// ✅ Use async/await for asynchronous code
async function loadDashboardData() {
    try {
        const [courses, stats] = await Promise.all([
            this.loadCourses(),
            this.loadStats()
        ]);

        this.renderCourses(courses);
        this.renderStats(stats);
    } catch (error) {
        console.error('Error loading dashboard:', error);
        this.showError(error.message);
    }
}

// ❌ Avoid callback hell
loadCourses(function(courses) {
    loadStats(function(stats) {
        renderCourses(courses);
        renderStats(stats);
    });
});
```

### CSS Code Style

#### BEM-Like Naming
```css
/* Block */
.dashboard-card { }

/* Element */
.dashboard-card__header { }
.dashboard-card__title { }

/* Modifier */
.dashboard-card--highlighted { }
```

#### CSS Variables
```css
:root {
    --primary-color: #4B6EFB;
    --secondary-color: #6E4BFB;
    --text-color: #333;
    --background-light: #f5f5f5;
}

.button {
    background-color: var(--primary-color);
    color: white;
}
```

#### Mobile-First Approach
```css
/* Mobile styles first */
.container {
    padding: 1rem;
}

/* Tablet and up */
@media (min-width: 768px) {
    .container {
        padding: 2rem;
    }
}

/* Desktop and up */
@media (min-width: 1024px) {
    .container {
        padding: 3rem;
    }
}
```

### HTML Code Style

#### Semantic HTML5
```html
<!-- ✅ Use semantic elements -->
<header>
    <nav>...</nav>
</header>

<main>
    <article>
        <section>...</section>
    </article>
</main>

<footer>...</footer>

<!-- ❌ Avoid generic divs when semantic elements exist -->
<div class="header">
    <div class="nav">...</div>
</div>
```

#### Accessibility
```html
<!-- ✅ Include ARIA attributes -->
<button aria-label="Close modal" aria-expanded="false">
    <i class="fas fa-times"></i>
</button>

<!-- ✅ Use alt text for images -->
<img src="logo.svg" alt="Sci-Bono Discovery Centre Logo">

<!-- ✅ Use labels for form inputs -->
<label for="email">Email Address</label>
<input type="email" id="email" name="email" required>
```

---

## 📚 Documentation Requirements

**⚠️ CRITICAL: ALL code changes MUST include documentation updates.**

### Before Making Changes
1. Read relevant existing documentation in `/Documentation/`
2. Review `DOCUMENTATION_PROGRESS.md` for current status
3. Identify which documentation files will be affected

### During Implementation
1. Document design decisions and rationale
2. Note any deviations from existing patterns
3. Keep track of files modified

### After Completion
1. Update all affected documentation files
2. Add entry to change log in `DOCUMENTATION_PROGRESS.md`
3. Verify documentation is complete and accurate

### Documentation Checklist

Before submitting a PR, verify:

- [ ] **Read First**: Reviewed existing documentation before making changes
- [ ] **Architecture**: Updated architecture docs if structure/design changed
- [ ] **Code Reference**: Updated JavaScript/CSS/HTML docs for code changes
- [ ] **Database**: Updated schema-design.md for any database modifications
- [ ] **User Impact**: Updated user guides if interface/workflow changed
- [ ] **Setup Changes**: Updated setup-guide.md for new dependencies/requirements
- [ ] **Change Log**: Added detailed entry to `DOCUMENTATION_PROGRESS.md`
- [ ] **Completeness**: Verified documentation is clear, accurate, and complete
- [ ] **Examples**: Added code examples where applicable
- [ ] **Cross-References**: Updated related documentation files

### Documentation Locations

| Change Type | File to Update |
|-------------|----------------|
| New JS function | `01-Technical/02-Code-Reference/javascript-api.md` |
| New CSS class | `01-Technical/02-Code-Reference/css-system.md` |
| New HTML pattern | `01-Technical/02-Code-Reference/html-structure.md` |
| Database change | `01-Technical/03-Database/schema-design.md` |
| API endpoint | README.md + relevant controller docs |
| Bug fix | `05-Maintenance/troubleshooting.md` |
| Setup change | `01-Technical/04-Development/setup-guide.md` |

---

## 🔍 Pull Request Process

### PR Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Related Issue
Closes #(issue number)

## Changes Made
- Change 1
- Change 2
- Change 3

## Testing
Describe how you tested your changes

## Documentation
- [ ] Updated relevant documentation in /Documentation/
- [ ] Added entry to DOCUMENTATION_PROGRESS.md
- [ ] Updated README.md if necessary
- [ ] Included code examples where applicable

## Screenshots (if applicable)
Add screenshots to demonstrate changes

## Checklist
- [ ] My code follows the project's code style
- [ ] I have performed a self-review of my code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] I have made corresponding changes to the documentation
- [ ] My changes generate no new warnings
- [ ] I have tested my changes thoroughly
- [ ] Any dependent changes have been merged and published
```

### Review Process

1. **Automated Checks**: PR must pass all automated checks (if configured)
2. **Code Review**: At least one maintainer must review and approve
3. **Documentation Review**: Documentation changes must be verified
4. **Testing**: All tests must pass (when testing framework is implemented)
5. **Merge**: Maintainer will merge after approval

---

## 🧪 Testing Guidelines

### Manual Testing Checklist

#### For Backend Changes
- [ ] Test API endpoint with Postman/curl
- [ ] Verify correct HTTP status codes
- [ ] Check response JSON format
- [ ] Test authentication/authorization
- [ ] Test error handling
- [ ] Verify database changes
- [ ] Test edge cases

#### For Frontend Changes
- [ ] Test on Chrome, Firefox, Safari
- [ ] Test responsive design (mobile, tablet, desktop)
- [ ] Verify animations work smoothly
- [ ] Test form validation
- [ ] Check console for errors
- [ ] Verify accessibility (keyboard navigation)
- [ ] Test with slow network connection

#### For Full-Stack Features
- [ ] Test complete user flow
- [ ] Verify data persistence
- [ ] Test error scenarios
- [ ] Check performance
- [ ] Verify security measures

### Test Data

Use the following test accounts (if available):
- **Admin**: admin@test.com / password
- **Instructor**: instructor@test.com / password
- **Student**: student@test.com / password

---

## 🐛 Reporting Bugs

### Before Submitting a Bug Report

1. **Check Existing Issues**: Search existing issues to avoid duplicates
2. **Update to Latest**: Ensure you're on the latest version
3. **Reproduce**: Verify the bug is reproducible
4. **Isolate**: Try to isolate the problem

### Bug Report Template

```markdown
**Bug Description**
Clear and concise description of the bug

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '...'
3. Scroll down to '...'
4. See error

**Expected Behavior**
What you expected to happen

**Screenshots**
Add screenshots if applicable

**Environment**
- OS: [e.g., Ubuntu 20.04]
- Browser: [e.g., Chrome 120]
- PHP Version: [e.g., 8.1]
- MySQL Version: [e.g., 8.0.28]

**Additional Context**
Any other context about the problem

**Error Messages**
```
Paste error messages or logs here
```
```

---

## 💡 Suggesting Enhancements

### Enhancement Request Template

```markdown
**Feature Description**
Clear and concise description of the feature

**Problem it Solves**
What problem does this feature solve?

**Proposed Solution**
How should this feature work?

**Alternatives Considered**
What alternatives have you considered?

**Benefits**
- Benefit 1
- Benefit 2

**Potential Drawbacks**
- Drawback 1
- Drawback 2

**Additional Context**
Mockups, examples, or references

**Implementation Ideas**
(Optional) Suggestions on how to implement
```

---

## 🏆 Recognition

### Contributors

Contributors will be recognized in:
- CONTRIBUTORS.md file
- Release notes
- Project documentation

### Types of Contributions

We value all contributions, including:
- 💻 Code contributions
- 📚 Documentation improvements
- 🐛 Bug reports
- 💡 Feature suggestions
- 🎨 Design improvements
- ✅ Testing and QA
- 🌍 Translations (future)

---

## 📞 Getting Help

### Communication Channels

- **GitHub Issues**: For bug reports and feature requests
- **GitHub Discussions**: For questions and general discussions
- **Email**: vuyani@sci-bono.co.za (for sensitive issues)

### Response Times

- **Bug Reports**: Within 48 hours
- **Feature Requests**: Within 1 week
- **Pull Requests**: Within 1 week

---

## 📄 License

By contributing to Sci-Bono AI Fluency LMS, you agree that your contributions will be licensed under the MIT License.

---

## 🙏 Thank You!

Thank you for contributing to Sci-Bono AI Fluency LMS! Your efforts help improve AI education for students worldwide.

---

<div align="center">

**Questions?** Open a [GitHub Discussion](https://github.com/sci-bono/sci-bono-aifluency/discussions)

**Found a Bug?** [Report it](https://github.com/sci-bono/sci-bono-aifluency/issues/new?template=bug_report.md)

**Have an Idea?** [Suggest it](https://github.com/sci-bono/sci-bono-aifluency/issues/new?template=feature_request.md)

</div>
