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
1. Create HTML file following naming convention (e.g., `chapter7_35.html`)
2. Copy structure from existing chapter file
3. Update the `service-worker.js` `urlsToCache` array
4. Add chapter link to corresponding module page
5. Update chapter navigation (previous/next buttons)

### Modifying Quiz Questions
Quiz data is stored as a JavaScript array (`quizData`) embedded in each quiz HTML file. Each question object contains:
- `id`: Unique identifier
- `question`: Question text
- `options`: Array of answer choices
- `correctAnswer`: Index of correct option (0-based)
- `explanation`: Feedback shown after submission

### Styling Changes
- Global changes: Edit `css/styles.css`
- Module-specific changes: Edit `css/stylesModules.css`
- CSS variables are defined in `:root` - modify these for theme changes

### PDF Download Feature
The PDF generation uses html2canvas to capture page content and jsPDF to create the document. This feature is currently commented out in most pages but can be enabled by uncommenting the download button in the header.

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

## Recent Changes

Based on git history:
- Holiday program feature work in progress
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
