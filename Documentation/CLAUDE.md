# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Sci-Bono AI Fluency Course**, an educational Progressive Web App (PWA) designed to teach artificial intelligence concepts to students in grades 8-12. The application is a static HTML/CSS/JavaScript website that can be installed on devices for offline access.

## Architecture

### Application Type
- **Static PWA**: No backend server or build process required
- **Progressive Web App**: Installable, works offline via Service Worker
- **Multi-page Structure**: Each chapter and module is a separate HTML file

### Content Hierarchy
1. **Landing Page** (`index.html`): Main entry point with course overview
2. **Module Pages** (`module1.html` - `module6.html`): Module overviews with chapter listings
3. **Chapter Pages** (`chapter*.html`): Individual lessons (e.g., `chapter1.html`, `chapter1_11.html`)
4. **Quiz Pages** (`module*Quiz.html`): Interactive quizzes for each module
5. **Offline Page** (`offline.html`): Fallback when network is unavailable

### Module Structure
The course is organized into 6 modules:
- **Module 1**: AI Foundations (11 chapters)
- **Module 2**: Generative AI
- **Module 3**: Advanced Search
- **Module 4**: Responsible AI
- **Module 5**: Microsoft Copilot
- **Module 6**: AI Impact

Each module has:
- A module overview page listing all chapters
- Individual chapter pages for lessons
- A quiz page to test understanding

### File Naming Convention
- Main chapters: `chapter[1-12].html` (e.g., `chapter1.html`)
- Sub-chapters/lessons: `chapter[1-12]_[11-43].html` (e.g., `chapter1_11.html`, `chapter2_12.html`)
- Module pages: `module[1-6].html`
- Quiz pages: `module[1-6]Quiz.html`

## Key Technologies

### PWA Components
- **Service Worker** (`service-worker.js`): Handles caching and offline functionality
- **Manifest** (`manifest.json`): Defines app metadata, icons, and install behavior
- **Cache Strategy**: Cache-first approach with network fallback

### Styling
- **Primary CSS**: `css/styles.css` - Global styles, landing page, chapter pages
- **Module CSS**: `css/stylesModules.css` - Module-specific styling
- **Design System**: CSS variables defined in `:root` for consistent theming
  - Primary color: `#4B6EFB` (blue)
  - Secondary color: `#6E4BFB` (purple)
  - Accent color: `#FB4B4B` (red)
  - Accent green: `#4BFB9D`

### JavaScript
- **Main Script** (`js/script.js`): Handles navigation, PDF generation, PWA installation
- **Quiz Logic**: Embedded in quiz HTML files (no separate quiz.js file)

### Frontend Stack Policy
**⚠️ CRITICAL: This project uses NO frameworks - vanilla web technologies only.**

This is a strict architectural requirement that must be followed:

- **HTML**: Pure HTML5 only (no JSX, no templating frameworks, no preprocessors)
- **CSS**: Pure CSS3 only (no Bootstrap, Tailwind, Foundation, Bulma, or any CSS frameworks)
- **JavaScript**: Vanilla ES6+ only (no React, Vue, Angular, Svelte, jQuery, or any JS frameworks/libraries except those listed below)
- **Progressive Enhancement**: Build with standards-based web technologies that work across all browsers
- **Zero Build Process**: No webpack, Vite, Parcel, or build tools - works directly in browser

**Why No Frameworks?**
- Maximum compatibility across devices and browsers
- Minimal dependencies and attack surface
- Educational transparency - students can view source and learn
- Offline-first PWA that works without any bundling
- Faster load times and better performance
- Future-proof - no framework deprecation concerns

**Allowed External Libraries** (CDN only, no npm):
- Font Awesome (icons)
- Google Fonts (typography)
- jsPDF (PDF generation)
- html2canvas (screenshot capture)

Any frontend integration with the backend API must use native `fetch()` API, not axios or other HTTP libraries.

### External Dependencies
- Font Awesome 6.1.1 (icons)
- Google Fonts (Montserrat, Poppins)
- jsPDF 2.5.1 (PDF generation)
- html2canvas 1.4.1 (screenshot capture for PDFs)

## Development Workflow

### Testing the Application
Since this is a static site, you can test it by:
```bash
# Serve locally (from project root)
python3 -m http.server 8000
# Or using PHP
php -S localhost:8000
```
Then navigate to `http://localhost:8000` in a browser.

### Working with Service Worker
After modifying cached resources:
1. Update the `CACHE_NAME` version in `service-worker.js` (e.g., `'ai-fluency-cache-v2'`)
2. Add any new files to the `urlsToCache` array
3. Test in browser DevTools > Application > Service Workers

### PWA Testing
- **Desktop**: Chrome/Edge will show install prompt automatically
- **Mobile**: Use browser's "Add to Home Screen" option
- **iOS**: Requires manual "Add to Home Screen" from Safari share menu

## Common Tasks

### Adding a New Chapter
1. **Read Documentation**: Review `/Documentation/01-Technical/02-Code-Reference/html-structure.md` for chapter template patterns
2. Create HTML file following naming convention (e.g., `chapter7_35.html`)
3. Copy structure from existing chapter file
4. Update the `service-worker.js` `urlsToCache` array
5. Add chapter link to corresponding module page
6. Update chapter navigation (previous/next buttons)
7. **Document Changes**:
   - Update `/Documentation/03-Content-Management/creating-modules.md` if adding new patterns
   - Update `service-worker.md` if cache strategy changes
   - Add entry to `DOCUMENTATION_PROGRESS.md` change log

### Modifying Quiz Questions
Quiz data is stored as a JavaScript array (`quizData`) embedded in each quiz HTML file. Each question object contains:
- `id`: Unique identifier
- `question`: Question text
- `options`: Array of answer choices
- `correctAnswer`: Index of correct option (0-based)
- `explanation`: Feedback shown after submission

**Documentation Requirements:**
- Update `/Documentation/03-Content-Management/quiz-creation.md` with any new question patterns
- Document quiz logic changes in `javascript-api.md` if modifying quiz functionality
- Add change log entry to `DOCUMENTATION_PROGRESS.md`

### Styling Changes
- **Read First**: Review `/Documentation/01-Technical/02-Code-Reference/css-system.md` for current design system
- Global changes: Edit `css/styles.css`
- Module-specific changes: Edit `css/stylesModules.css`
- CSS variables are defined in `:root` - modify these for theme changes

**Documentation Requirements:**
- Update `css-system.md` for new classes, variables, or components
- Document color/typography changes in design system section
- Update `html-structure.md` if styling affects HTML patterns
- Add change log entry to `DOCUMENTATION_PROGRESS.md`

### PDF Download Feature
The PDF generation uses html2canvas to capture page content and jsPDF to create the document. This feature is currently commented out in most pages but can be enabled by uncommenting the download button in the header.

**Documentation Requirements:**
- Document implementation details in `javascript-api.md` PDF generation section
- Update `html-structure.md` if download button placement changes
- Note feature status in `current-architecture.md`

### Working with Service Worker
**Before Changes**: Read `/Documentation/01-Technical/02-Code-Reference/service-worker.md` for caching strategy

After modifying cached resources:
1. Update the `CACHE_NAME` version in `service-worker.js` (e.g., `'ai-fluency-cache-v2'`)
2. Add any new files to the `urlsToCache` array
3. Test in browser DevTools > Application > Service Workers

**Documentation Requirements:**
- Update `service-worker.md` with cache strategy changes
- Document new cached resources
- Note version changes in change log

### Adding Dependencies
When adding new external libraries or dependencies:
1. **Read First**: Check `/Documentation/01-Technical/01-Architecture/current-architecture.md` technology stack
2. Add script/link tags to relevant HTML files
3. Update Service Worker cache if needed
4. Test across all pages

**Documentation Requirements:**
- Add dependency to technology stack in `current-architecture.md`
- Document usage in relevant code reference files
- Update `setup-guide.md` if dev environment setup changes
- Include CDN links, versions, and purpose

## Important Patterns

### Navigation Structure
- Each chapter includes a `.chapter-nav` with `.nav-tab` buttons for internal sections
- Bottom navigation: `.nav-buttons` with `.nav-button.previous` and `.nav-button.next`
- Module pages have `.chapter-card` elements linking to individual chapters

### Responsive Design
- Mobile-first approach with `@media (max-width: 768px)` breakpoints
- Timeline layout switches from two-column to single-column on mobile
- Install button shows as floating button on mobile, header button on desktop

### Analytics
Google Analytics (GA4) is integrated with tracking ID `G-VNN90D4GDE` and Google Ads with client ID `ca-pub-6423925713865339`. The tracking script is included in page `<head>`.

## Content Guidelines

### Chapter Page Structure
1. **Header**: Logo, title, controls (PDF download)
2. **Chapter Header**: Module badge, chapter title, subtitle
3. **Chapter Navigation**: Section tabs for within-page navigation
4. **Chapter Content**: Multiple `.content-section` elements with IDs matching nav tabs
5. **Navigation Buttons**: Previous/next chapter links
6. **Footer**: Copyright, links

### Interactive Elements
- Quizzes use radio buttons with JavaScript for validation and scoring
- Progress tracking shows completion percentage
- Results page provides score-based feedback
- Review mode highlights correct/incorrect answers

## Deployment Notes

This is deployed in a LAMP environment (`/var/www/html/sci-bono-aifluency/`) but can be hosted anywhere that serves static files. No server-side processing is required.

The PWA requires HTTPS for full functionality (Service Worker, install prompts) except on localhost.

## Code Style

- **HTML**: Semantic HTML5 structure, inline SVG for graphics
- **CSS**: BEM-like naming, CSS variables for theming
- **JavaScript**: Vanilla JS (no frameworks), ES6+ features, event delegation
- **Comments**: Limited inline comments; code should be self-documenting

## Documentation Standards

**⚠️ CRITICAL: Documentation is mandatory for ALL project work.**

This project maintains comprehensive documentation in the `/Documentation/` directory (13,836+ lines covering architecture, code, database, and operations). All changes to the codebase MUST be accompanied by corresponding documentation updates.

### Documentation-First Approach

1. **Before Making Changes:**
   - Read relevant existing documentation to understand current implementation
   - Review `/Documentation/DOCUMENTATION_PROGRESS.md` to see what's documented
   - Check if your changes affect multiple documentation areas

2. **During Implementation:**
   - Document design decisions and rationale
   - Note any deviations from existing patterns
   - Keep track of files modified

3. **After Completion:**
   - Update all affected documentation files
   - Add entry to change log in `DOCUMENTATION_PROGRESS.md`
   - Verify documentation is complete and accurate

### Required Documentation for Changes

#### New Features
- **Architecture Docs**: Update `current-architecture.md` if app structure changes
- **Code Reference**: Document new functions in `javascript-api.md`, new styles in `css-system.md`, new HTML patterns in `html-structure.md`
- **User Guides**: Update relevant user guides if UX changes
- **Content Management**: Document how to use/configure the feature

#### Bug Fixes
- **Troubleshooting**: Add entry to `troubleshooting.md` with problem/solution
- **Code Reference**: Update affected code documentation
- **Change Log**: Record fix in `DOCUMENTATION_PROGRESS.md`

#### Code Modifications
- **JavaScript Changes**: Update `01-Technical/02-Code-Reference/javascript-api.md`
- **CSS Changes**: Update `01-Technical/02-Code-Reference/css-system.md`
- **HTML Changes**: Update `01-Technical/02-Code-Reference/html-structure.md`
- **Service Worker**: Update `01-Technical/02-Code-Reference/service-worker.md`

#### Database/Backend Work
- **Schema Changes**: Update `01-Technical/03-Database/schema-design.md`
- **Migration Scripts**: Add scripts to `01-Technical/03-Database/migration-scripts/`
- **Architecture**: Update `future-architecture.md` if backend design changes

#### Configuration/Setup Changes
- **Development**: Update `01-Technical/04-Development/setup-guide.md`
- **Deployment**: Update relevant files in `04-Deployment/`
- **Dependencies**: Document in `current-architecture.md` technology stack

### Documentation Directory Structure

```
/Documentation/
├── README.md                          # Documentation overview
├── DOCUMENTATION_PROGRESS.md          # Progress tracker & change log
├── DOCUMENTATION_TEMPLATE.md          # Template for new docs
│
├── 01-Technical/
│   ├── 01-Architecture/
│   │   ├── current-architecture.md    # Static PWA architecture (721 lines)
│   │   ├── future-architecture.md     # Full LMS design (1,124 lines)
│   │   └── migration-roadmap.md       # Migration strategy (2,019 lines)
│   ├── 02-Code-Reference/
│   │   ├── javascript-api.md          # JS functions & APIs (1,725 lines)
│   │   ├── css-system.md              # Design system (1,625 lines)
│   │   ├── html-structure.md          # HTML patterns (1,380 lines)
│   │   └── service-worker.md          # PWA implementation (2,220 lines)
│   ├── 03-Database/
│   │   ├── schema-design.md           # Database schema (1,682 lines)
│   │   ├── erd-diagrams/              # Entity relationship diagrams
│   │   └── migration-scripts/         # SQL migration scripts
│   └── 04-Development/
│       ├── setup-guide.md             # Dev environment setup (1,340 lines)
│       ├── coding-standards.md        # Code style guide (pending)
│       └── testing-procedures.md      # QA procedures (pending)
│
├── 02-User-Guides/                    # End-user documentation (pending)
├── 03-Content-Management/             # Content creation guides (pending)
├── 04-Deployment/                     # Deployment procedures (pending)
├── 05-Maintenance/                    # Operations & troubleshooting (pending)
└── 06-Interactive/                    # Documentation portal (pending)
```

### Mandatory Documentation Checklist

Before marking any work as complete, verify:

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

### Documentation Quality Standards

All documentation must:
- Use clear, concise language
- Include code examples with syntax highlighting
- Provide context and rationale for decisions
- Be kept up-to-date with code changes
- Follow markdown formatting standards
- Include table of contents for long documents (>100 lines)
- Use consistent terminology across all docs

### Documentation Resources

- **Documentation Home**: `/Documentation/README.md`
- **Progress Tracker**: `/Documentation/DOCUMENTATION_PROGRESS.md`
- **Template**: `/Documentation/DOCUMENTATION_TEMPLATE.md`
- **Current Status**: 11 of 28 docs complete (39.3%), 13,836 lines written

### Quick Reference: What to Document Where

| Change Type | Documentation Location | File to Update |
|-------------|----------------------|----------------|
| New JS function | Code Reference | `javascript-api.md` |
| New CSS class/style | Code Reference | `css-system.md` |
| New HTML pattern | Code Reference | `html-structure.md` |
| New chapter/module | Content Management | `creating-modules.md` |
| New quiz questions | Content Management | `quiz-creation.md` |
| Service Worker changes | Code Reference | `service-worker.md` |
| Database table/field | Database | `schema-design.md` |
| New dependency | Architecture | `current-architecture.md` |
| Setup/config change | Development | `setup-guide.md` |
| Bug fix | Maintenance | `troubleshooting.md` |
| Deployment change | Deployment | `deployment-checklist.md` |
| User workflow change | User Guides | Relevant guide (student/instructor/admin) |

## Recent Changes

**November 2025 - LMS Backend Infrastructure Complete:**
- ✅ Full backend API implementation (26 RESTful endpoints)
- ✅ 9 controllers: Auth, User, Course, Enrollment, Lesson, Quiz, Project, Certificate
- ✅ 13 models with Active Record pattern
- ✅ JWT authentication with token blacklist
- ✅ Role-based access control (student, instructor, admin)
- ✅ Automatic progress tracking and certificate generation
- ✅ Quiz validation and grading system
- ✅ Project submission and grading workflow
- ~21,400 lines of production PHP code

**Previous Changes (Based on git history):**
- PWA installation improvements (iOS install banner, mobile install button enhancements)
- Download button removed from index and module pages (only on lesson pages)
- PWA manifest and screenshots added
- Chapter 6 modifications

## Git Workflow

Current branch: `main`
Main branch for PRs: `main`

Standard workflow:
```bash
git status                    # Check current changes
git add .                     # Stage all changes
git commit -m "message"       # Commit with message
git push                      # Push to remote
```
