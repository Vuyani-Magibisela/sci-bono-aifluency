# HTML Structure Guide - Sci-Bono AI Fluency Platform

**Document Version:** 1.0
**Last Updated:** 2025-10-27
**Author:** Development Team
**Status:** Complete

---

## Table of Contents

1. [Introduction](#introduction)
2. [Document Structure](#document-structure)
3. [Page Templates](#page-templates)
4. [Component Patterns](#component-patterns)
5. [Semantic HTML](#semantic-html)
6. [Accessibility Features](#accessibility-features)
7. [SVG Graphics](#svg-graphics)
8. [Forms & Interactive Elements](#forms--interactive-elements)
9. [Meta Tags & SEO](#meta-tags--seo)
10. [PWA Integration](#pwa-integration)
11. [Best Practices](#best-practices)
12. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This document provides comprehensive documentation for the HTML structure and patterns used throughout the Sci-Bono AI Fluency platform. It covers page templates, component structures, semantic HTML usage, and accessibility features.

### HTML Philosophy

**Design Principles:**
- **Semantic HTML5** - Use appropriate tags for content
- **Accessibility First** - ARIA labels, keyboard navigation
- **Progressive Enhancement** - Works without JavaScript
- **SEO Optimized** - Proper meta tags, structured data
- **Component-Based** - Reusable HTML patterns

### File Organization

**Total Pages:** 70+ HTML files
- 1 landing page (`index.html`)
- 6 module overview pages (`module*.html`)
- 50+ chapter pages (`chapter*.html`)
- 6 quiz pages (`module*Quiz.html`)
- Additional pages (login, signup, offline, etc.)

---

## Document Structure

### Standard HTML5 Document

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- PWA Meta Tags -->
    <!-- Service Worker Registration -->
    <!-- Standard Meta Tags -->
    <!-- External Resources -->
    <!-- Analytics -->
</head>
<body>
    <!-- Header -->
    <!-- Mobile Menu -->
    <!-- Main Content -->
    <!-- Footer -->
    <!-- Scripts -->
</body>
</html>
```

---

### Head Section Structure

#### 1. Analytics (First)

**Location:** Top of `<head>`

```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-VNN90D4GDE"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-VNN90D4GDE');
</script>

<!-- Google ads -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6423925713865339"
crossorigin="anonymous"></script>
```

**Why First?**
- Tracks page views accurately
- Minimizes missed events
- Loads asynchronously

---

#### 2. PWA Meta Tags

```html
<!-- PWA Meta Tags -->
<meta name="theme-color" content="#4B6EFB">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="AI Fluency">
```

**Purpose:**
- `theme-color` - Browser toolbar color (Android)
- `apple-mobile-web-app-capable` - Enables standalone mode on iOS
- `apple-mobile-web-app-status-bar-style` - iOS status bar appearance
- `apple-mobile-web-app-title` - App name on iOS home screen

---

#### 3. Manifest & Icons

```html
<!-- Manifest and Icons -->
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
```

**Purpose:**
- `manifest.json` - PWA configuration (name, icons, colors)
- `apple-touch-icon` - iOS home screen icon (180x180px)

---

#### 4. Service Worker Registration

```html
<!-- Service Worker Registration -->
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js')
        .then(registration => {
          console.log('Service Worker registered with scope:', registration.scope);
        })
        .catch(error => {
          console.error('Service Worker registration failed:', error);
        });
    });
  }
</script>
```

**Key Points:**
- Feature detection (`if ('serviceWorker' in navigator)`)
- Registers after page load (performance optimization)
- Logs success/failure for debugging
- Absolute path (`/service-worker.js`)

---

#### 5. Standard Meta Tags

```html
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Fluency - Digital Infographic</title>
```

**Essential Tags:**
- `charset="UTF-8"` - Character encoding (supports emojis, special characters)
- `viewport` - Responsive design (mobile-friendly)
- `title` - Page title (SEO, browser tab)

---

#### 6. External Resources

```html
<!-- CSS -->
<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/stylesModules.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="images/favicon.ico">

<!-- JavaScript Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
```

**Loading Strategy:**
- CSS loaded in `<head>` (render-blocking, but necessary)
- JS libraries loaded in `<head>` (needed by inline scripts)
- Custom JS (`script.js`) loaded at end of `<body>` (deferred)

---

## Page Templates

### Template 1: Landing Page (`index.html`)

**Structure:**
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Standard head content -->
</head>
<body>
    <!-- Header with Logo & Navigation -->
    <header>...</header>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu">...</div>

    <!-- Main Content -->
    <main>
        <!-- iOS Install Banner -->
        <div id="ios-install-banner">...</div>

        <!-- Hero Section -->
        <section class="hero">...</section>

        <!-- Courses Section -->
        <section class="courses" id="courses">...</section>

        <!-- Features Section -->
        <section class="features">...</section>

        <!-- Call to Action -->
        <section class="cta">...</section>
    </main>

    <!-- Footer -->
    <footer>...</footer>

    <!-- Scripts -->
    <script src="js/script.js"></script>
</body>
</html>
```

**Key Sections:**
1. **Hero** - Main headline, CTA, visual
2. **Courses** - Course cards/timeline
3. **Features** - Platform highlights
4. **CTA** - Call to action (sign up, start learning)

---

### Template 2: Module Overview Page (`module*.html`)

**Structure:**
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Standard head content -->
    <link rel="stylesheet" href="css/stylesModules.css"> <!-- Additional module CSS -->
</head>
<body>
    <header>...</header>

    <main>
        <div class="module-container">
            <!-- Module Header -->
            <div class="module-header">
                <h1>Module 1: AI Foundations</h1>
                <p class="subtitle">Explore the history and fundamental concepts of AI</p>
            </div>

            <!-- Module Introduction -->
            <div class="module-intro">
                <div class="module-intro-content">...</div>
                <div class="module-intro-image"><!-- SVG --></div>
            </div>

            <!-- Chapters Grid -->
            <div class="chapters-grid">
                <div class="chapter-card">...</div>
                <div class="chapter-card">...</div>
                <!-- More chapters -->
            </div>

            <!-- Quiz Section -->
            <div class="module-quiz">...</div>

            <!-- Navigation Buttons -->
            <div class="module-navigation">...</div>
        </div>
    </main>

    <footer>...</footer>
    <script src="js/script.js"></script>
</body>
</html>
```

**Key Sections:**
1. **Module Header** - Title, subtitle, badge
2. **Module Intro** - Overview text + visual
3. **Chapters Grid** - Clickable chapter cards
4. **Quiz Section** - Link to module quiz
5. **Navigation** - Previous/next module buttons

---

### Template 3: Chapter Page (`chapter*.html`)

**Structure:**
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Standard head content -->
</head>
<body>
    <header>
        <div class="logo">...</div>
        <div class="header-controls">
            <button id="downloadPdf">Download PDF</button>
        </div>
    </header>

    <main>
        <div class="chapter-container">
            <!-- Chapter Header -->
            <div class="chapter-header">
                <div class="module-badge">Module 1: AI Foundations</div>
                <h1>Chapter 1.00: AI History</h1>
                <p class="subtitle">A brief history of Artificial Intelligence</p>
            </div>

            <!-- Chapter Navigation Tabs -->
            <div class="chapter-nav">
                <a href="#introduction" class="nav-tab active">Introduction</a>
                <a href="#ai-timeline" class="nav-tab">Timeline</a>
                <a href="#ai-milestones" class="nav-tab">Milestones</a>
                <a href="#ai-today" class="nav-tab">AI Today</a>
            </div>

            <!-- Chapter Content -->
            <div class="chapter-content">
                <section class="content-section" id="introduction">...</section>
                <section class="content-section" id="ai-timeline">...</section>
                <section class="content-section" id="ai-milestones">...</section>
                <section class="content-section" id="ai-today">...</section>
            </div>

            <!-- Navigation Buttons -->
            <div class="nav-buttons">
                <a href="module1.html" class="nav-button previous">← Back to Module</a>
                <a href="chapter1_11.html" class="nav-button next">Next Chapter →</a>
            </div>
        </div>
    </main>

    <footer>...</footer>
    <script src="js/script.js"></script>
</body>
</html>
```

**Key Sections:**
1. **Chapter Header** - Badge, title, subtitle
2. **Chapter Nav** - Section tabs (anchor links)
3. **Content Sections** - Multiple `<section>` elements
4. **Nav Buttons** - Previous/next chapter links

---

### Template 4: Quiz Page (`module*Quiz.html`)

**Structure:**
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Standard head content -->
    <style>
        /* Inline quiz-specific styles */
    </style>
</head>
<body>
    <header>...</header>

    <main>
        <div class="quiz-container">
            <!-- Quiz Header -->
            <div class="quiz-header">
                <div class="module-badge">Module 1: AI Foundations</div>
                <h1>Knowledge Check Quiz</h1>
                <p class="subtitle">Test your understanding of AI fundamentals</p>
            </div>

            <!-- Progress Bar -->
            <div class="progress-container">...</div>

            <!-- Quiz Content -->
            <div id="quizContent">
                <!-- Instructions -->
                <div class="quiz-instructions">...</div>

                <!-- Quiz Form -->
                <form id="quiz-form">
                    <div id="questions-container"></div>
                    <div class="pagination"></div>
                    <button type="submit" class="submit-btn">Submit Quiz</button>
                </form>

                <!-- Results (hidden initially) -->
                <div class="results" id="results" style="display: none;">...</div>
            </div>
        </div>
    </main>

    <footer>...</footer>

    <script>
        // Inline quiz data and logic
        const quizData = [...];
        // Quiz functions
    </script>
</body>
</html>
```

**Key Features:**
1. **Quiz Data** - Embedded JavaScript array
2. **Dynamic Question Generation** - JavaScript creates HTML
3. **Progress Tracking** - Visual progress bar
4. **Pagination** - Page through questions
5. **Results Display** - Score, feedback, review option

---

## Component Patterns

### Header Component

```html
<header>
    <!-- Logo -->
    <div class="logo">
        <a href="index.html">
            <svg width="50" height="50" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="45" fill="#4B6EFB" />
                <text x="30" y="68" fill="white" font-family="Arial" font-weight="bold" font-size="40">AI</text>
            </svg>
        </a>
        <h1>AI Fluency</h1>
    </div>

    <!-- Desktop Navigation -->
    <nav class="main-nav">
        <ul class="nav-links">
            <li><a href="index.html" class="nav-link active">Home</a></li>
            <li><a href="courses.html" class="nav-link">Courses</a></li>
            <li><a href="projects.html" class="nav-link">Projects</a></li>
            <li><a href="#about" class="nav-link">About</a></li>
        </ul>
    </nav>

    <!-- Hamburger Menu (Mobile) -->
    <button class="hamburger" id="hamburger" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Header Controls -->
    <div class="header-controls">
        <a href="login.html" class="header-btn login-btn">Login</a>
        <a href="signup.html" class="header-btn signup-btn">Sign Up</a>
    </div>
</header>
```

**Structure Breakdown:**
- **Logo** - SVG + text, linked to home
- **Main Nav** - Desktop navigation (hidden on mobile)
- **Hamburger** - Mobile menu trigger (hidden on desktop)
- **Header Controls** - Login/signup buttons

**Accessibility:**
- `aria-label="Toggle menu"` on hamburger button
- Semantic `<nav>` element
- Keyboard navigable links

---

### Mobile Menu Component

```html
<!-- Mobile Menu Overlay -->
<div class="mobile-menu" id="mobileMenu">
    <button class="close-menu" id="closeMenu" aria-label="Close menu">
        <i class="fas fa-times"></i>
    </button>
    <ul class="mobile-nav-links">
        <li><a href="index.html" class="mobile-nav-link">Home</a></li>
        <li><a href="courses.html" class="mobile-nav-link">Courses</a></li>
        <li><a href="projects.html" class="mobile-nav-link">Projects</a></li>
        <li><a href="#about" class="mobile-nav-link">About</a></li>
    </ul>
</div>
```

**Behavior:**
- Initially off-screen (`right: -100%`)
- Slides in when `.active` class added
- Close button and backdrop click close menu
- JavaScript in `script.js` handles interactions

---

### Hero Section

```html
<section class="hero">
    <div class="hero-content">
        <h1>Welcome to <span class="highlight">AI Fluency</span></h1>
        <p>Learn artificial intelligence concepts through interactive lessons, quizzes, and hands-on projects.</p>
        <div class="cta-buttons">
            <button id="startCourse" class="btn-primary">Start Learning</button>
            <button id="viewContents" class="btn-outline">View Contents</button>
        </div>
    </div>
    <div class="hero-visual">
        <svg width="400" height="400" viewBox="0 0 400 400">
            <!-- SVG illustration -->
        </svg>
    </div>
</section>
```

**Layout:**
- Two-column flex layout (desktop)
- Stacks vertically on mobile
- CTA buttons with different styles

---

### Card Component

#### Chapter Card

```html
<div class="chapter-card">
    <div class="chapter-card-icon">
        <i class="fas fa-brain"></i>
    </div>
    <div class="chapter-card-content">
        <h3>Chapter 1: AI History</h3>
        <p>Explore the history of artificial intelligence from its origins to modern day.</p>
        <a href="chapter1.html" class="card-link">Start Chapter →</a>
    </div>
</div>
```

**Structure:**
- **Icon Section** - Font Awesome icon or emoji
- **Content Section** - Title, description, link
- Hover effect lifts card

---

#### Course Card

```html
<div class="course-card">
    <div class="course-icon">
        <i class="fas fa-robot"></i>
    </div>
    <h3>AI Foundations</h3>
    <p>Learn the basics of artificial intelligence, machine learning, and neural networks.</p>
    <div class="course-meta">
        <span><i class="fas fa-clock"></i> 6 hours</span>
        <span><i class="fas fa-book"></i> 12 chapters</span>
    </div>
    <button class="btn-primary">Explore Course</button>
</div>
```

---

### Module Badge

```html
<div class="module-badge">Module 1: AI Foundations</div>
```

**Usage:**
- Chapter pages (indicates parent module)
- Quiz pages (context for quiz)
- Always blue pill shape

---

### Chapter Navigation Tabs

```html
<div class="chapter-nav">
    <div class="nav-buttons-container">
        <a href="#introduction" class="nav-tab active">
            <i class="fas fa-info-circle"></i> Introduction
        </a>
        <a href="#ai-timeline" class="nav-tab">
            <i class="fas fa-history"></i> Timeline
        </a>
        <a href="#ai-milestones" class="nav-tab">
            <i class="fas fa-award"></i> Milestones
        </a>
        <a href="#ai-today" class="nav-tab">
            <i class="fas fa-rocket"></i> AI Today
        </a>
    </div>
</div>
```

**Behavior:**
- Anchor links to sections on same page
- Active tab highlighted
- JavaScript updates active state on scroll
- Icons from Font Awesome

---

### Navigation Buttons

```html
<div class="nav-buttons">
    <a href="module1.html" class="nav-button previous">
        ← Back to Module
    </a>
    <a href="chapter1_11.html" class="nav-button next">
        Next Chapter →
    </a>
</div>
```

**Placement:** Bottom of chapter pages
**Direction:** Previous (left), Next (right)

---

### Footer Component

```html
<footer>
    <div class="footer-content">
        <p>&copy; 2025 AI Fluency Course</p>
        <div class="footer-links">
            <a href="#">About</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Use</a>
        </div>
    </div>
</footer>
```

**Simple Structure:**
- Copyright notice
- Footer links
- Centered layout

---

## Semantic HTML

### Proper Use of HTML5 Elements

**Document Structure:**
```html
<header>     <!-- Page header (logo, nav) -->
<nav>        <!-- Navigation links -->
<main>       <!-- Main content area -->
<section>    <!-- Thematic grouping -->
<article>    <!-- Self-contained content -->
<aside>      <!-- Supplementary content -->
<footer>     <!-- Page footer -->
```

**Content Elements:**
```html
<h1> to <h6>  <!-- Headings (hierarchy) -->
<p>           <!-- Paragraphs -->
<ul>, <ol>    <!-- Lists -->
<figure>      <!-- Self-contained content -->
<figcaption>  <!-- Figure caption -->
<blockquote>  <!-- Quoted content -->
<time>        <!-- Date/time -->
```

---

### Example: Proper Heading Hierarchy

```html
<main>
    <h1>Module 1: AI Foundations</h1>  <!-- Only one h1 per page -->

    <section id="introduction">
        <h2>Introduction</h2>  <!-- h2 for main sections -->

        <h3>What is AI?</h3>  <!-- h3 for subsections -->
        <p>Content...</p>

        <h3>History of AI</h3>
        <p>Content...</p>
    </section>

    <section id="concepts">
        <h2>Core Concepts</h2>

        <h3>Machine Learning</h3>
        <h4>Types of ML</h4>  <!-- h4 for sub-subsections -->
        <p>Content...</p>
    </section>
</main>
```

**Rules:**
- One `<h1>` per page (page title)
- Don't skip levels (h1 → h2 → h3, not h1 → h3)
- Headings describe content structure
- Screen readers use headings for navigation

---

### Lists

**Unordered List (bullets):**
```html
<ul>
    <li>Machine Learning</li>
    <li>Neural Networks</li>
    <li>Natural Language Processing</li>
</ul>
```

**Ordered List (numbered):**
```html
<ol>
    <li>Watch the video</li>
    <li>Read the chapter</li>
    <li>Complete the quiz</li>
</ol>
```

**Definition List:**
```html
<dl>
    <dt>AI</dt>
    <dd>Artificial Intelligence - simulation of human intelligence in machines</dd>

    <dt>ML</dt>
    <dd>Machine Learning - subset of AI that learns from data</dd>
</dl>
```

---

### Links

**Internal Links:**
```html
<a href="chapter2.html">Next Chapter</a>
```

**External Links:**
```html
<a href="https://example.com" target="_blank" rel="noopener noreferrer">
    External Resource
</a>
```

**Anchor Links (same page):**
```html
<a href="#introduction">Jump to Introduction</a>

<!-- Target -->
<section id="introduction">...</section>
```

**Best Practices:**
- Descriptive link text (not "click here")
- `rel="noopener noreferrer"` for external links (security)
- `target="_blank"` for external links (optional)

---

## Accessibility Features

### ARIA Labels

**Button with Icon:**
```html
<button class="hamburger" id="hamburger" aria-label="Toggle menu">
    <span></span>
    <span></span>
    <span></span>
</button>
```

**Close Button:**
```html
<button class="close-menu" id="closeMenu" aria-label="Close menu">
    <i class="fas fa-times"></i>
</button>
```

**Why?**
- Screen readers can't "see" icons
- `aria-label` provides text description
- Essential for icon-only buttons

---

### Alt Text for Images

**Inline SVG with title:**
```html
<svg role="img" aria-labelledby="brain-icon-title">
    <title id="brain-icon-title">Brain with neural connections</title>
    <!-- SVG paths -->
</svg>
```

**Image with alt:**
```html
<img src="images/logo.svg" alt="Sci-Bono Discovery Center logo">
```

**Decorative images:**
```html
<img src="decoration.svg" alt="" role="presentation">
```

**Rules:**
- All images need `alt` attribute
- Decorative images: `alt=""` (empty, not missing)
- Informative images: Descriptive alt text
- Complex images: Consider longer description

---

### Keyboard Navigation

**Focus Styles:**
```css
button:focus,
a:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}
```

**Tab Order:**
- Natural DOM order = tab order
- Don't use `tabindex` > 0 (breaks natural order)
- Use `tabindex="-1"` to programmatically focus
- Use `tabindex="0"` to add to tab order

---

### Form Accessibility

**Labels:**
```html
<label for="email">Email Address</label>
<input type="email" id="email" name="email" required>
```

**Required Fields:**
```html
<input type="text" required aria-required="true">
```

**Error Messages:**
```html
<input type="email" id="email" aria-describedby="email-error">
<span id="email-error" class="error">Please enter a valid email</span>
```

---

### Skip Links

**For keyboard users:**
```html
<a href="#main-content" class="skip-link">Skip to main content</a>

<header>...</header>

<main id="main-content">...</main>
```

**CSS:**
```css
.skip-link {
    position: absolute;
    top: -40px;
    left: 0;
    background: var(--primary-color);
    color: white;
    padding: 8px;
    z-index: 1000;
}

.skip-link:focus {
    top: 0;
}
```

---

## SVG Graphics

### Inline SVG Benefits

**Advantages:**
- ✅ Scalable (no pixelation)
- ✅ Stylable with CSS
- ✅ Animatable with CSS/JS
- ✅ Small file size
- ✅ No HTTP request

**Disadvantages:**
- ❌ Increases HTML file size
- ❌ Not cached separately
- ❌ Can be verbose for complex graphics

---

### SVG Structure

**Basic SVG:**
```html
<svg width="100" height="100" viewBox="0 0 100 100">
    <!-- Shapes go here -->
</svg>
```

**Attributes:**
- `width`, `height` - Display size
- `viewBox="x y width height"` - Coordinate system
- `preserveAspectRatio` - Scaling behavior

---

### Common SVG Shapes

**Circle:**
```html
<circle cx="50" cy="50" r="40" fill="#4B6EFB" />
```

**Rectangle:**
```html
<rect x="10" y="10" width="80" height="60" rx="5" fill="#4B6EFB" />
```

**Line:**
```html
<line x1="0" y1="0" x2="100" y2="100" stroke="#4B6EFB" stroke-width="2" />
```

**Path:**
```html
<path d="M10 10 L90 90" stroke="#4B6EFB" fill="none" />
```

**Text:**
```html
<text x="50" y="50" fill="white" font-size="20" text-anchor="middle">AI</text>
```

---

### Logo SVG Example

```html
<svg width="50" height="50" viewBox="0 0 100 100">
    <!-- Background circle -->
    <circle cx="50" cy="50" r="45" fill="#4B6EFB" />

    <!-- "AI" text -->
    <text x="30" y="68" fill="white" font-family="Arial" font-weight="bold" font-size="40">
        AI
    </text>
</svg>
```

**Usage:** Header logo, consistent across all pages

---

### Complex SVG Graphics

**Brain Icon (Module Pages):**
```html
<svg width="200" height="200" viewBox="0 0 200 200">
    <!-- Brain outline -->
    <ellipse cx="100" cy="100" rx="70" ry="60" fill="none" stroke="#4B6EFB" stroke-width="2" />

    <!-- Neural connections -->
    <path d="M70 80 Q100 40, 130 80" fill="none" stroke="#4B6EFB" stroke-width="2" />
    <path d="M70 120 Q100 160, 130 120" fill="none" stroke="#4B6EFB" stroke-width="2" />
    <line x1="70" y1="100" x2="130" y2="100" stroke="#4B6EFB" stroke-width="2" />

    <!-- Nodes -->
    <circle cx="70" cy="80" r="5" fill="#FB4B4B" />
    <circle cx="130" cy="80" r="5" fill="#FB4B4B" />
    <circle cx="70" cy="120" r="5" fill="#FB4B4B" />
    <circle cx="130" cy="120" r="5" fill="#FB4B4B" />
</svg>
```

---

### SVG Accessibility

**With Title and Description:**
```html
<svg role="img" aria-labelledby="icon-title icon-desc">
    <title id="icon-title">Brain Icon</title>
    <desc id="icon-desc">A stylized brain with neural network connections</desc>
    <!-- SVG content -->
</svg>
```

**Decorative SVG:**
```html
<svg aria-hidden="true">
    <!-- Decorative content -->
</svg>
```

---

## Forms & Interactive Elements

### Quiz Question Structure

**Radio Button Question:**
```html
<div class="question-container" id="question-0">
    <div class="question">1. What is artificial intelligence (AI)?</div>

    <div class="options">
        <div class="option">
            <input type="radio" name="question-0" id="question-0-option-0" value="0">
            <label for="question-0-option-0">
                A computer program that can only perform predetermined tasks
            </label>
        </div>

        <div class="option">
            <input type="radio" name="question-0" id="question-0-option-1" value="1">
            <label for="question-0-option-1">
                The ability of a computer system to learn from past data and errors
            </label>
        </div>

        <!-- More options -->
    </div>
</div>
```

**Key Points:**
- Unique `name` per question (groups radio buttons)
- Unique `id` per option
- `<label>` linked to input via `for` attribute
- Value indicates option index

---

### Button Types

**Submit Button:**
```html
<button type="submit" class="submit-btn" id="submit-btn">
    Submit Quiz
</button>
```

**Regular Button:**
```html
<button type="button" class="btn-primary" id="startCourse">
    Start Learning
</button>
```

**Link styled as Button:**
```html
<a href="chapter1.html" class="btn-primary">Start Chapter</a>
```

**Icon Button:**
```html
<button id="downloadPdf">
    <i class="fas fa-download"></i> Download PDF
</button>
```

---

## Meta Tags & SEO

### Essential Meta Tags

**Character Set:**
```html
<meta charset="UTF-8">
```

**Viewport (Mobile):**
```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```

**Title:**
```html
<title>AI Fluency - Learn Artificial Intelligence</title>
```

**Description:**
```html
<meta name="description" content="Learn artificial intelligence concepts through interactive lessons, quizzes, and projects. Free AI education for students.">
```

---

### Open Graph Tags (Social Sharing)

```html
<meta property="og:title" content="AI Fluency - Learn Artificial Intelligence">
<meta property="og:description" content="Interactive AI education platform">
<meta property="og:image" content="https://scibono.co.za/images/og-image.png">
<meta property="og:url" content="https://scibono.co.za/">
<meta property="og:type" content="website">
```

**Twitter Cards:**
```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="AI Fluency">
<meta name="twitter:description" content="Interactive AI education">
<meta name="twitter:image" content="https://scibono.co.za/images/twitter-card.png">
```

---

### Canonical URL

```html
<link rel="canonical" href="https://scibono.co.za/module1.html">
```

**Purpose:**
- Prevents duplicate content issues
- Tells search engines the "official" URL
- Useful for URL parameters

---

## PWA Integration

### iOS Install Banner

```html
<div id="ios-install-banner" style="display: none;">
    <p>Install this app on your iPhone: tap
        <i class="fas fa-share"></i> and then "Add to Home Screen"
        <i class="fas fa-plus"></i>
    </p>
    <button onclick="this.parentNode.style.display='none'">
        <i class="fas fa-times"></i>
    </button>
</div>

<script>
    // Show banner only on iOS devices
    if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
        if (!navigator.standalone) {
            document.getElementById('ios-install-banner').style.display = 'block';
        }
    }
</script>
```

**Why?**
- iOS doesn't support `beforeinstallprompt` event
- Users must manually add to home screen
- Banner provides instructions

---

### PWA Install Button

```html
<!-- Created dynamically by JavaScript -->
<button class="install-button" style="display: none;">
    Install AI Fluency
</button>
```

**JavaScript (in script.js):**
```javascript
const installButton = document.createElement('button');
installButton.className = 'install-button';
installButton.textContent = 'Install AI Fluency';

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installButton.style.display = 'block';
});
```

---

## Best Practices

### HTML Validation

**Validate with W3C:**
- URL: https://validator.w3.org/
- Checks for syntax errors
- Ensures standards compliance

**Common Errors to Avoid:**
- Missing closing tags
- Duplicate IDs
- Improper nesting
- Missing required attributes

---

### Performance

**1. Minimize HTML Size:**
```html
<!-- Good: Concise -->
<div class="card">

<!-- Avoid: Unnecessary whitespace -->
<div      class="card"      >
```

**2. Load Scripts at End:**
```html
<body>
    <!-- Content -->

    <script src="js/script.js"></script> <!-- At end -->
</body>
```

**3. Async/Defer for External Scripts:**
```html
<!-- Async: Load and execute ASAP -->
<script async src="https://example.com/script.js"></script>

<!-- Defer: Load in order, execute after parse -->
<script defer src="script1.js"></script>
<script defer src="script2.js"></script>
```

---

### Maintainability

**1. Consistent Indentation:**
```html
<div class="container">
    <div class="row">
        <div class="col">
            <p>Content</p>
        </div>
    </div>
</div>
```

**2. Commenting:**
```html
<!-- Header Section -->
<header>...</header>

<!-- Main Content -->
<main>
    <!-- Hero Section -->
    <section class="hero">...</section>

    <!-- Courses Section -->
    <section class="courses">...</section>
</main>

<!-- Footer Section -->
<footer>...</footer>
```

**3. Meaningful Class Names:**
```html
<!-- Good -->
<div class="chapter-card">
<button class="btn-primary">

<!-- Avoid -->
<div class="box1">
<button class="blue-btn">
```

---

### Security

**1. Escape User Content:**
```javascript
// Never do this:
element.innerHTML = userInput; // XSS risk!

// Do this:
element.textContent = userInput; // Safe
```

**2. Validate Inputs:**
```html
<input type="email" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
```

**3. Use HTTPS:**
```html
<!-- Good -->
<script src="https://cdn.example.com/lib.js"></script>

<!-- Avoid -->
<script src="http://cdn.example.com/lib.js"></script>
```

---

## Related Documents

### Technical Documentation
- [CSS System Documentation](css-system.md) - Styling reference
- [JavaScript API Reference](javascript-api.md) - Frontend code
- [Service Worker Guide](service-worker.md) - PWA implementation
- [Current Architecture](../01-Architecture/current-architecture.md) - System overview

### Development Guides
- [Development Setup](../04-Development/setup-guide.md) - Environment setup
- [Coding Standards](../04-Development/coding-standards.md) (coming soon) - Code style

### External Resources
- [MDN: HTML Reference](https://developer.mozilla.org/en-US/docs/Web/HTML)
- [HTML5 Doctor](http://html5doctor.com/) - Semantic HTML guide
- [W3C Validator](https://validator.w3.org/) - HTML validation
- [WCAG Guidelines](https://www.w3.org/WAI/WCAG21/quickref/) - Accessibility

---

## Document Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2025-10-27 | 1.0 | Dev Team | Initial HTML structure documentation |

---

**END OF DOCUMENT**

*This HTML structure guide provides complete documentation for all HTML patterns and templates used in the Sci-Bono AI Fluency platform. Use this as a reference for creating new pages and maintaining consistent markup across the application.*
