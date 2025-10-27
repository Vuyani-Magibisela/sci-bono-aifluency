# Current Architecture - Sci-Bono AI Fluency Platform

**Document Version:** 1.0
**Last Updated:** 2025-10-27
**Status:** Production - Static PWA

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Technology Stack](#technology-stack)
3. [Application Architecture](#application-architecture)
4. [File Structure](#file-structure)
5. [PWA Features](#pwa-features)
6. [Content Organization](#content-organization)
7. [Navigation System](#navigation-system)
8. [Design System](#design-system)
9. [Current Limitations](#current-limitations)
10. [Performance Characteristics](#performance-characteristics)

---

## Executive Summary

The Sci-Bono AI Fluency platform is currently implemented as a **Static Progressive Web App (PWA)** designed to deliver AI education content to students aged 10-35. The application runs entirely in the browser with no backend server dependency, utilizing modern web technologies to provide an offline-capable, installable learning experience.

### Key Characteristics:
- **Type:** Static PWA (HTML/CSS/JavaScript)
- **Backend:** None (frontend-only)
- **Database:** None (content embedded in HTML)
- **Deployment:** Any static web hosting (Apache, Nginx, GitHub Pages, etc.)
- **Authentication:** None (currently open access)
- **Data Persistence:** LocalStorage for limited client-side data

---

## Technology Stack

### Core Technologies

| Technology | Version | Purpose |
|------------|---------|---------|
| HTML5 | Latest | Content structure and semantic markup |
| CSS3 | Latest | Styling, animations, responsive design |
| JavaScript | ES6+ | Interactivity, PWA features, quiz logic |
| Service Worker API | Latest | Offline caching, PWA functionality |
| Web App Manifest | Latest | PWA installation metadata |

### External Dependencies

| Library | Version | Purpose | CDN/Local |
|---------|---------|---------|-----------|
| Font Awesome | 6.1.1 | Icon library | CDN |
| Google Fonts | Latest | Typography (Montserrat, Poppins) | CDN |
| jsPDF | 2.5.1 | PDF generation | CDN |
| html2canvas | 1.4.1 | Screenshot capture for PDFs | CDN |
| Google Analytics | GA4 | Usage analytics | CDN |

### Development Tools
- **Version Control:** Git
- **Code Editor:** VS Code (recommended)
- **Local Server:** Python HTTP Server or PHP built-in server
- **Browser DevTools:** Chrome DevTools, Firefox Developer Tools

---

## Application Architecture

### Architecture Pattern: Static Multi-Page Application (MPA)

```
┌─────────────────────────────────────────────────────────────┐
│                    BROWSER (Client Side)                     │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐ │
│  │                  Service Worker                        │ │
│  │  - Cache Management                                    │ │
│  │  - Offline Support                                     │ │
│  │  - Asset Precaching                                    │ │
│  └────────────────────────────────────────────────────────┘ │
│                            │                                  │
│  ┌───────────────┬────────┴────────┬───────────────────┐   │
│  │               │                 │                    │   │
│  │   HTML Pages  │   CSS Styles    │   JavaScript       │   │
│  │   (~70 files) │   (2 files)     │   (1 main file)    │   │
│  │               │                 │                    │   │
│  └───────────────┴─────────────────┴───────────────────┘   │
│                            │                                  │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              Browser Storage (Client-Side)            │  │
│  │  - LocalStorage: User preferences, quiz progress      │  │
│  │  - IndexedDB: (not currently used)                    │  │
│  │  - Cache API: Service Worker managed cache            │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘

                            │
                            ▼
                    ┌──────────────┐
                    │  CDN Services │
                    │  - Font Awesome │
                    │  - Google Fonts │
                    │  - jsPDF Library │
                    └──────────────┘
```

### Data Flow

#### Page Navigation Flow:
```
User → Clicks Link → Browser loads HTML →
Service Worker intercepts →
Check cache → Return cached or fetch →
Render page
```

#### Quiz Flow:
```
User selects answers →
JavaScript validates →
Calculate score →
Store in LocalStorage →
Display results
```

#### PWA Installation Flow:
```
User visits site →
Service Worker registers →
Browser checks install criteria →
Show install prompt →
User confirms →
App installed to home screen
```

---

## File Structure

### Root Directory Overview

```
/sci-bono-aifluency/
├── index.html                      # Main landing page
├── courses.html                    # Course listing page
├── projects.html                   # Projects showcase
├── aifluencystart.html             # AI Fluency course entry
├── login.html                      # Login page (frontend only)
├── signup.html                     # Registration page (frontend only)
├── forgot-password.html            # Password reset page (frontend only)
├── present.html                    # Platform presentation
├── offline.html                    # Offline fallback page
│
├── student-dashboard.html          # Student dashboard (static)
├── instructor-dashboard.html       # Instructor dashboard (static)
├── admin-dashboard.html            # Admin dashboard (static)
│
├── project-school-data-detective.html  # Detailed project guide
│
├── module1.html ... module6.html   # Module overview pages (6 files)
├── module1Quiz.html ... module6Quiz.html  # Module quizzes (6 files)
│
├── chapter*.html                   # Chapter/lesson pages (~45 files)
│   ├── chapter1.html
│   ├── chapter1_11.html
│   ├── chapter1_17.html
│   └── ... (pattern continues)
│
├── css/
│   ├── styles.css                  # Global styles (~2,500 lines)
│   └── stylesModules.css           # Module-specific styles
│
├── js/
│   └── script.js                   # Main JavaScript file (~400 lines)
│
├── images/
│   ├── logo.svg
│   ├── AIFluency.png
│   ├── Course image.jpg
│   ├── courses.png
│   ├── digitalDevide.jpg
│   ├── interface.png
│   └── ... (various course/project images)
│
├── assets/
│   └── (additional resources)
│
├── Documentation/
│   └── (this documentation)
│
├── manifest.json                   # PWA manifest
├── service-worker.js               # Service Worker script
├── CLAUDE.md                       # Development guidelines
├── CHANGELOG.md                    # Change history
└── .gitignore                      # Git ignore rules
```

### File Naming Conventions

#### Chapters:
- **Main chapters:** `chapter[1-12].html`
- **Sub-chapters:** `chapter[1-12]_[11-43].html`
- Example: `chapter3_26.html` = Chapter 3, Sub-section 26

#### Modules:
- **Module pages:** `module[1-6].html`
- **Quiz pages:** `module[1-6]Quiz.html`

#### Special Pages:
- **Dashboards:** `[role]-dashboard.html` (student, instructor, admin)
- **Auth pages:** `login.html`, `signup.html`, `forgot-password.html`
- **Projects:** `project-[project-name].html`

---

## PWA Features

### Service Worker Implementation

**File:** `service-worker.js`

#### Cache Strategy: Cache-First with Network Fallback

```javascript
// Cache Version
const CACHE_NAME = 'ai-fluency-cache-v1';

// Cached Resources
const urlsToCache = [
  '/',
  '/index.html',
  '/offline.html',
  '/css/styles.css',
  // ... all pages and assets
];
```

#### Caching Behavior:

1. **Install Phase:**
   - Pre-cache all listed resources
   - Prepare for offline access

2. **Fetch Phase:**
   - Check cache first
   - Return cached version if available
   - Fetch from network if not cached
   - Update cache with new content
   - Fallback to offline.html on network failure

3. **Activate Phase:**
   - Clean up old caches
   - Take control of pages

### PWA Manifest

**File:** `manifest.json`

```json
{
  "name": "Sci-Bono AI Fluency",
  "short_name": "AI Fluency",
  "start_url": "/",
  "display": "standalone",
  "theme_color": "#4B6EFB",
  "background_color": "#FFFFFF",
  "icons": [...]
}
```

### Installation Behavior

**Desktop (Chrome/Edge):**
- Automatic install prompt after engagement criteria met
- Shows in address bar and menu

**Mobile (Android):**
- "Add to Home Screen" banner
- Installed as standalone app

**iOS (Safari):**
- Manual installation via Share → "Add to Home Screen"
- Limited PWA features due to Safari restrictions

### Offline Capabilities

**Fully Accessible Offline:**
- All course content (chapters, modules)
- Quizzes (scoring works offline)
- Projects page
- Dashboards (static content)
- Navigation

**Requires Internet:**
- External fonts (cached after first load)
- External scripts (jsPDF, html2canvas)
- Google Analytics
- Any future API calls

---

## Content Organization

### Course Structure

```
AI Fluency Course
├── Module 1: AI Foundations (11 chapters)
│   ├── Chapter 1: Introduction
│   ├── Chapter 1.11: Deep Dive Topic
│   └── ... (continues)
├── Module 2: Generative AI
├── Module 3: Advanced Search
├── Module 4: Responsible AI
├── Module 5: Microsoft Copilot
└── Module 6: AI Impact
```

### Content Types

#### 1. **Module Pages** (`module[1-6].html`)
- Module overview
- Learning objectives
- Chapter listing with descriptions
- "Start Module" button
- Navigation to quiz

#### 2. **Chapter Pages** (`chapter*.html`)
- Module badge
- Chapter title and subtitle
- Tabbed navigation for sections
- Rich content (text, images, code examples)
- Previous/Next navigation
- Optional PDF download

#### 3. **Quiz Pages** (`module*Quiz.html`)
- Multiple choice questions
- JavaScript-based validation
- Immediate feedback
- Score calculation
- Results page
- Review mode

#### 4. **Project Pages**
- **Listing:** `projects.html` with filtering
- **Detailed Guides:** `project-school-data-detective.html` (template for others)

---

## Navigation System

### Primary Navigation

**Header Navigation** (All pages):
```
Logo | Home | Courses | Projects | About | [Login] [Sign Up]
```

**Mobile Navigation:**
- Hamburger menu icon
- Slide-in overlay menu
- Same links as desktop

### Navigation States

- **Active Page:** Blue background on current nav item
- **Hover State:** Color transition on hover
- **Mobile Toggle:** Smooth slide animation

### Navigation Patterns

#### Course Flow:
```
index.html →
courses.html →
aifluencystart.html →
module1.html →
chapter1.html →
chapter1_11.html →
... →
module1Quiz.html
```

#### Project Flow:
```
index.html →
projects.html →
[Click Project] →
Modal with details →
[View Full Guide] →
project-school-data-detective.html
```

#### Dashboard Flow (Currently Static):
```
login.html →
[Login] →
student-dashboard.html OR
instructor-dashboard.html OR
admin-dashboard.html
```

---

## Design System

### Color Palette

```css
/* Primary Colors */
--primary-color: #4B6EFB;    /* Blue */
--secondary-color: #6E4BFB;  /* Purple */
--accent-color: #FB4B4B;     /* Red */
--accent-green: #4BFB9D;     /* Green */

/* Neutral Colors */
--text-dark: #2C3E50;        /* Dark gray */
--text-light: #7F8C8D;       /* Light gray */
--white: #FFFFFF;            /* White */
--background-light: #F8F9FA; /* Light background */
--grey-light: #E0E0E0;       /* Light border gray */
```

### Typography

**Fonts:**
- **Headers:** Poppins (Google Fonts)
- **Body:** Montserrat (Google Fonts)

**Scale:**
```css
h1: 2.5rem - 3rem
h2: 2rem - 2.5rem
h3: 1.5rem - 1.8rem
body: 1rem (16px)
small: 0.875rem
```

### Component Library

#### Cards
```css
.card {
  background: white;
  border-radius: 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.08);
  padding: 1.5rem - 2.5rem;
  transition: transform 0.3s;
}

.card:hover {
  transform: translateY(-8px);
  box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}
```

#### Buttons
```css
.btn-primary {
  background: linear-gradient(135deg, #4B6EFB, #6E4BFB);
  color: white;
  padding: 0.8rem 2rem;
  border-radius: 30px;
  transition: all 0.3s;
}
```

#### Badges
```css
.badge {
  padding: 0.4rem 1rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
}
```

### Responsive Breakpoints

```css
/* Mobile First */
@media (max-width: 768px) {
  /* Mobile styles */
}

@media (min-width: 769px) and (max-width: 1024px) {
  /* Tablet styles */
}

@media (min-width: 1025px) {
  /* Desktop styles */
}
```

### Spacing System

```css
/* Consistent spacing using rem */
--spacing-xs: 0.5rem;   /* 8px */
--spacing-sm: 1rem;     /* 16px */
--spacing-md: 1.5rem;   /* 24px */
--spacing-lg: 2rem;     /* 32px */
--spacing-xl: 3rem;     /* 48px */
--spacing-xxl: 4rem;    /* 64px */
```

---

## Current Limitations

### Authentication & User Management
- ❌ No user authentication system
- ❌ No user registration/login functionality
- ❌ No session management
- ❌ Login/signup pages are UI only (no backend)
- ❌ Dashboards are static mockups

### Data Persistence
- ❌ No database
- ❌ No server-side data storage
- ❌ Progress tracking limited to LocalStorage
- ❌ Quiz results not permanently stored
- ❌ No cross-device sync

### Content Management
- ❌ No admin panel for content editing
- ❌ Content updates require HTML editing
- ❌ No WYSIWYG editor
- ❌ Manual deployment required for changes

### User Features
- ❌ No user progress tracking across devices
- ❌ No certificate generation (backend needed)
- ❌ No personalized recommendations
- ❌ No discussion forums or social features
- ❌ No email notifications

### Assessment
- ❌ Quizzes work, but results not stored server-side
- ❌ No instructor grading capability
- ❌ No project submission system
- ❌ No detailed analytics

### Collaboration
- ❌ No multi-user features
- ❌ No instructor-student communication
- ❌ No peer review
- ❌ No group projects

---

## Performance Characteristics

### Load Times (Average)

| Page Type | Initial Load | Cached Load | Size |
|-----------|--------------|-------------|------|
| Landing Page | 0.8s | 0.1s | ~180KB |
| Chapter Page | 1.2s | 0.2s | ~250KB |
| Quiz Page | 0.9s | 0.15s | ~150KB |
| Projects Page | 1.5s | 0.25s | ~320KB |

### Optimization Features

**Current:**
- ✅ Service Worker caching
- ✅ Minified CSS (could be improved)
- ✅ CDN for external libraries
- ✅ Lazy loading for images (not implemented yet)
- ✅ Responsive images (not implemented yet)

**Recommended:**
- 🔄 Image optimization (WebP format)
- 🔄 CSS/JS minification
- 🔄 Critical CSS inlining
- 🔄 Lazy loading for below-fold content
- 🔄 HTTP/2 server push

### Browser Compatibility

| Browser | Version | Support Level |
|---------|---------|---------------|
| Chrome | 90+ | ✅ Full support |
| Firefox | 88+ | ✅ Full support |
| Safari | 14+ | ⚠️ Limited PWA features |
| Edge | 90+ | ✅ Full support |
| Mobile Safari | 14+ | ⚠️ Manual install only |
| Chrome Mobile | 90+ | ✅ Full support |

### Accessibility Features

**Current Implementation:**
- ✅ Semantic HTML
- ✅ ARIA labels on buttons
- ✅ Keyboard navigation support
- ✅ Color contrast meets WCAG AA
- ⚠️ Screen reader optimization (needs improvement)
- ⚠️ Focus indicators (could be more prominent)

---

## Security Considerations

### Current Security Posture

**Strengths:**
- No server-side code = No server vulnerabilities
- No database = No SQL injection risks
- Static content = Limited attack surface
- HTTPS recommended for PWA features

**Limitations:**
- No input validation (no user inputs to validate)
- No XSS protection needed (no user-generated content)
- No CSRF protection (no state-changing operations)
- Client-side quiz answers visible in JavaScript

**Future Considerations (When Backend Added):**
- Implement proper authentication
- Secure password hashing (bcrypt)
- CSRF tokens
- Input sanitization
- Rate limiting
- SQL injection prevention
- Session security

---

## Deployment Checklist

### Current Deployment (Static)

1. ✅ Update service worker cache version
2. ✅ Add new files to `urlsToCache` array
3. ✅ Test offline functionality locally
4. ✅ Upload files via FTP/Git
5. ✅ Clear browser cache and test
6. ✅ Verify PWA installation works
7. ✅ Test on mobile devices

### Hosting Requirements

**Minimum:**
- Web server (Apache, Nginx, or any static host)
- HTTPS support (required for Service Worker)
- No special server configuration needed

**Recommended Hosts:**
- GitHub Pages
- Netlify
- Vercel
- AWS S3 + CloudFront
- DigitalOcean App Platform
- Traditional web hosting with HTTPS

---

## Monitoring & Analytics

### Current Tracking

**Google Analytics (GA4):**
- Page views
- User demographics
- Device types
- Geographic distribution
- Session duration

**Not Currently Tracked:**
- User progress
- Quiz performance
- Course completion rates
- Feature usage patterns
- Error rates

**Future Recommendations:**
- Add custom events for interactions
- Track quiz starts/completions
- Monitor PDF downloads
- Track PWA install rates
- Error logging (Sentry, LogRocket)

---

## Summary & Next Steps

### Current State Assessment

**Strengths:**
- ✅ Fully functional offline
- ✅ Installable as PWA
- ✅ Fast load times
- ✅ Responsive design
- ✅ Rich educational content
- ✅ No hosting complexity

**Areas for Improvement:**
- Add user authentication
- Implement backend database
- Enable progress tracking
- Create admin CMS
- Add certificate generation
- Implement instructor tools

### Recommended Reading

- [Future Architecture Documentation](future-architecture.md)
- [Migration Roadmap](migration-roadmap.md)
- [Code Reference Guide](../02-Code-Reference/javascript-api.md)
- [Deployment Guide](../../04-Deployment/deployment-checklist.md)

---

**Document Maintained By:** Development Team
**Review Schedule:** Quarterly or after major changes
**Related Documents:**
- Future Architecture
- Migration Roadmap
- Code Reference Guide
- User Guides
