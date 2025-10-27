# JavaScript API Reference - Sci-Bono AI Fluency Platform

**Document Version:** 1.0
**Last Updated:** 2025-10-27
**Author:** Development Team
**Status:** Complete - Current Static PWA

---

## Table of Contents

1. [Introduction](#introduction)
2. [File Structure](#file-structure)
3. [Core Module: script.js](#core-module-scriptjs)
4. [Quiz System](#quiz-system)
5. [PWA Installation](#pwa-installation)
6. [PDF Generation](#pdf-generation)
7. [Navigation System](#navigation-system)
8. [Event Handlers](#event-handlers)
9. [Utility Functions](#utility-functions)
10. [Global Variables](#global-variables)
11. [Browser Compatibility](#browser-compatibility)
12. [Future Enhancements](#future-enhancements)
13. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This document provides a comprehensive reference for all JavaScript code used in the Sci-Bono AI Fluency platform. It covers the current static PWA implementation, including navigation, PWA installation, PDF generation, and interactive quiz functionality.

### JavaScript Architecture

**Current Implementation:**
- **Type:** Vanilla JavaScript (ES6+)
- **Frameworks:** None (framework-free)
- **Libraries Used:**
  - jsPDF 2.5.1 (PDF generation)
  - html2canvas 1.4.1 (screenshot capture)
  - Font Awesome 6.1.1 (icons, loaded via CDN)
- **Structure:** Single main file (`js/script.js`) + embedded quiz logic in HTML files

### Coding Philosophy

- **Progressive Enhancement:** Core functionality works without JavaScript
- **Vanilla JS First:** Avoid unnecessary dependencies
- **ES6+ Features:** Use modern JavaScript syntax
- **Event Delegation:** Efficient event handling
- **Accessibility:** ARIA labels and keyboard navigation
- **Performance:** Minimal DOM manipulation, efficient selectors

---

## File Structure

### JavaScript Files

```
/js/
├── script.js (288 lines)           # Main application logic
└── (No other separate JS files)    # Quiz logic is embedded in HTML files
```

### External Libraries

**Loaded via CDN:**

```html
<!-- jsPDF for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- html2canvas for capturing page content -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Font Awesome icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
```

---

## Core Module: script.js

### Overview

The `script.js` file contains all core application logic for the landing page, chapter pages, and general site functionality. It handles:

- Page initialization
- Navigation interactions
- Mobile menu functionality
- Chapter navigation (tabs and sections)
- PWA installation prompts
- PDF generation
- Smooth scrolling
- Visual enhancements

### File Location

`/var/www/html/sci-bono-aifluency/js/script.js`

---

## Core Functions

### 1. DOMContentLoaded Handler

**Purpose:** Initializes all page functionality after DOM is fully loaded

**Location:** `script.js:1-128`

**Usage:**
```javascript
document.addEventListener('DOMContentLoaded', function() {
    // All initialization code runs here
});
```

**What It Does:**
- Sets up button event listeners
- Initializes mobile menu
- Configures smooth scrolling
- Enables chapter navigation
- Adds hover effects to graphics
- Initializes PWA install button

---

### 2. Navigation Functions

#### 2.1 Start Course Button

**Function:** Navigates to course start page

**Location:** `script.js:2-8`

**Code:**
```javascript
const startCourseBtn = document.getElementById('startCourse');
if (startCourseBtn) {
    startCourseBtn.addEventListener('click', function() {
        window.location.href = 'aifluencystart.html';
    });
}
```

**Usage:**
- **Element ID:** `startCourse`
- **Action:** Redirects to `aifluencystart.html`
- **Found On:** `index.html` (landing page)

---

#### 2.2 View Contents Button

**Function:** Smooth scrolls to courses section

**Location:** `script.js:10-21`

**Code:**
```javascript
const viewContentsBtn = document.getElementById('viewContents');
if (viewContentsBtn) {
    viewContentsBtn.addEventListener('click', function() {
        const coursesSection = document.getElementById('courses');
        if (coursesSection) {
            coursesSection.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
}
```

**Parameters:**
- `behavior: 'smooth'` - Enables smooth scrolling animation

**Usage:**
- **Element ID:** `viewContents`
- **Target:** Section with `id="courses"`
- **Found On:** `index.html`

---

#### 2.3 Smooth Scrolling for Anchor Links

**Function:** Enables smooth scrolling for all hash links

**Location:** `script.js:43-51`

**Code:**
```javascript
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});
```

**How It Works:**
1. Finds all links starting with `#`
2. Prevents default jump behavior
3. Smoothly scrolls to target element

**Example HTML:**
```html
<a href="#introduction">Go to Introduction</a>
<!-- Smoothly scrolls to element with id="introduction" -->
```

---

### 3. Mobile Menu System

**Purpose:** Handles responsive mobile navigation menu

**Components:**
- Hamburger menu button
- Slide-out mobile menu
- Close button
- Backdrop overlay

#### 3.1 Open Mobile Menu

**Location:** `script.js:59-65`

**Code:**
```javascript
const hamburger = document.getElementById('hamburger');
if (hamburger) {
    hamburger.addEventListener('click', function() {
        mobileMenu.classList.add('active');
        document.body.classList.add('menu-open');
    });
}
```

**Element IDs:**
- `hamburger` - Menu toggle button
- `mobileMenu` - Slide-out menu container

**CSS Classes:**
- `active` - Shows the mobile menu
- `menu-open` - Added to body to prevent scrolling

---

#### 3.2 Close Mobile Menu

**Location:** `script.js:68-73`

**Code:**
```javascript
const closeMenu = document.getElementById('closeMenu');
if (closeMenu) {
    closeMenu.addEventListener('click', function() {
        mobileMenu.classList.remove('active');
        document.body.classList.remove('menu-open');
    });
}
```

**Trigger Conditions:**
1. Click on close button (`X`)
2. Click on navigation link
3. Click on backdrop (outside menu)

---

#### 3.3 Close on Link Click

**Location:** `script.js:76-81`

**Code:**
```javascript
mobileNavLinks.forEach(link => {
    link.addEventListener('click', function() {
        mobileMenu.classList.remove('active');
        document.body.classList.remove('menu-open');
    });
});
```

**Purpose:** Automatically closes menu after navigation

---

#### 3.4 Close on Backdrop Click

**Location:** `script.js:84-92`

**Code:**
```javascript
document.body.addEventListener('click', function(e) {
    if (document.body.classList.contains('menu-open')) {
        if (!mobileMenu.contains(e.target) && !hamburger.contains(e.target)) {
            mobileMenu.classList.remove('active');
            document.body.classList.remove('menu-open');
        }
    }
});
```

**Logic:**
- Only runs when menu is open
- Closes if click is outside menu and not on hamburger
- Prevents closing when clicking inside menu

---

### 4. Chapter Navigation System

**Purpose:** Handles tab navigation within chapter pages

**Features:**
- Clickable section tabs
- Auto-highlight based on scroll position
- Smooth scroll to sections

#### 4.1 Tab Click Handler

**Location:** `script.js:95-107`

**Code:**
```javascript
const sectionLinks = document.querySelectorAll('.nav-tab');
const sections = document.querySelectorAll('.content-section');

if (sectionLinks.length > 0 && sections.length > 0) {
    sectionLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            sectionLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });
}
```

**CSS Classes:**
- `.nav-tab` - Navigation tab button
- `.content-section` - Content section
- `.active` - Highlighted tab

**Example HTML:**
```html
<nav class="chapter-nav">
    <a href="#introduction" class="nav-tab">Introduction</a>
    <a href="#examples" class="nav-tab">Examples</a>
    <a href="#summary" class="nav-tab">Summary</a>
</nav>

<section id="introduction" class="content-section">...</section>
<section id="examples" class="content-section">...</section>
<section id="summary" class="content-section">...</section>
```

---

#### 4.2 Scroll-Based Tab Highlighting

**Location:** `script.js:109-127`

**Code:**
```javascript
window.addEventListener('scroll', function() {
    let current = '';

    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        if (pageYOffset >= sectionTop - 200) {
            current = section.getAttribute('id');
        }
    });

    sectionLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href').includes(current)) {
            link.classList.add('active');
        }
    });
});
```

**How It Works:**
1. Listens to scroll events
2. Calculates which section is currently in view (200px offset)
3. Updates active tab to match current section

**Threshold:** 200px from top of section triggers highlight

---

### 5. Visual Enhancements

#### 5.1 Graphic Element Hover Effects

**Location:** `script.js:31-41`

**Code:**
```javascript
const graphicElements = document.querySelectorAll('.graphic-element');
graphicElements.forEach(element => {
    element.addEventListener('mouseover', function() {
        this.style.transform = 'scale(1.1)';
    });

    element.addEventListener('mouseout', function() {
        this.style.transform = 'scale(1)';
    });
});
```

**Effect:** Scales graphic elements to 110% on hover

**CSS Class:** `.graphic-element`

**Example:**
```html
<div class="graphic-element">
    <!-- SVG or image content -->
</div>
```

---

## PWA Installation

**Purpose:** Manages Progressive Web App installation flow

**Location:** `script.js:130-207`

### Components

#### 5.1 Global Variables

```javascript
let deferredPrompt;  // Stores the install prompt event
const installButton = document.createElement('button');
```

**`deferredPrompt`:**
- Type: `BeforeInstallPromptEvent`
- Scope: Global
- Purpose: Stores the browser's install prompt to trigger later

---

#### 5.2 Install Button Creation

**Location:** `script.js:132-142`

**Code:**
```javascript
const installButton = document.createElement('button');
installButton.style.display = 'none';
installButton.className = 'install-button';
installButton.textContent = 'Install AI Fluency';
installButton.setAttribute('aria-label', 'Install AI Fluency app');

document.addEventListener('DOMContentLoaded', function() {
    const headerControls = document.querySelector('.header-controls');
    if (headerControls) {
        headerControls.prepend(installButton);
    }
});
```

**Properties:**
- **Class:** `install-button`
- **Text:** "Install AI Fluency"
- **Initial State:** Hidden (`display: none`)
- **Location:** Prepended to `.header-controls`

**CSS (from styles.css):**
```css
.install-button {
    background-color: var(--secondary-color);
    color: var(--white);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    /* ... additional styling ... */
}
```

---

#### 5.3 Mobile Install Button

**Location:** `script.js:144-156`

**Code:**
```javascript
if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
    const mobileInstallBtn = document.createElement('button');
    mobileInstallBtn.className = 'mobile-install-button';
    mobileInstallBtn.innerHTML = '<i class="fas fa-download"></i> Install App';
    mobileInstallBtn.style.display = 'none';
    document.body.appendChild(mobileInstallBtn);

    mobileInstallBtn.addEventListener('click', promptInstall);
}
```

**Condition:** Only created on mobile devices (Android/iOS)

**CSS Class:** `.mobile-install-button` (floating button)

**Icon:** Font Awesome download icon

---

#### 5.4 beforeinstallprompt Event

**Location:** `script.js:159-174`

**Code:**
```javascript
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installButton.style.display = 'block';

    const mobileInstallBtn = document.querySelector('.mobile-install-button');
    if (mobileInstallBtn) {
        mobileInstallBtn.style.display = 'block';
    }

    installButton.addEventListener('click', promptInstall);
});
```

**What It Does:**
1. **Prevents default:** Stops browser's automatic install prompt
2. **Stores event:** Saves event in `deferredPrompt` variable
3. **Shows buttons:** Makes install buttons visible
4. **Attaches handler:** Connects `promptInstall` function to button

**Browser Support:**
- ✅ Chrome/Edge (Android & Desktop)
- ✅ Samsung Internet
- ❌ Safari (iOS) - Uses manual "Add to Home Screen"
- ❌ Firefox - Limited PWA support

---

#### 5.5 promptInstall Function

**Location:** `script.js:177-196`

**Code:**
```javascript
async function promptInstall() {
    if (!deferredPrompt) return;

    // Hide the install buttons
    installButton.style.display = 'none';
    const mobileInstallBtn = document.querySelector('.mobile-install-button');
    if (mobileInstallBtn) {
        mobileInstallBtn.style.display = 'none';
    }

    // Show the installation prompt
    deferredPrompt.prompt();

    // Wait for the user to respond to the prompt
    const { outcome } = await deferredPrompt.userChoice;
    console.log(`User response to installation: ${outcome}`);

    // Clear the deferred prompt variable
    deferredPrompt = null;
}
```

**Function Type:** `async function`

**Return Value:** `Promise<void>`

**Process Flow:**
1. **Guard clause:** Returns early if no stored prompt
2. **Hide buttons:** Removes install buttons from UI
3. **Show prompt:** Triggers browser's native install dialog
4. **Wait for response:** Awaits user's accept/dismiss decision
5. **Log outcome:** Logs 'accepted' or 'dismissed' to console
6. **Cleanup:** Clears `deferredPrompt` variable

**User Outcomes:**
- `'accepted'` - User clicked "Install"
- `'dismissed'` - User clicked "Cancel"

---

#### 5.6 appinstalled Event

**Location:** `script.js:199-207`

**Code:**
```javascript
window.addEventListener('appinstalled', (e) => {
    console.log('AI Fluency has been installed');
    installButton.style.display = 'none';
    const mobileInstallBtn = document.querySelector('.mobile-install-button');
    if (mobileInstallBtn) {
        mobileInstallBtn.style.display = 'none';
    }
});
```

**Purpose:** Handles successful installation

**Actions:**
1. Logs installation to console
2. Permanently hides install buttons
3. Cleans up mobile install button

---

### PWA Installation Flow Diagram

```
User visits site
       ↓
beforeinstallprompt fires
       ↓
Event prevented & stored
       ↓
Install button shown
       ↓
User clicks "Install"
       ↓
promptInstall() called
       ↓
deferredPrompt.prompt()
       ↓
Browser shows install dialog
       ↓
┌──────┴──────┐
↓             ↓
User Accepts  User Dismisses
↓             ↓
appinstalled  Buttons hidden
event fires   deferredPrompt = null
↓
App installed
Buttons hidden
```

---

## PDF Generation

**Purpose:** Allows users to download chapter content as PDF

**Location:** `script.js:23-29, 210-288`

**Dependencies:**
- jsPDF 2.5.1
- html2canvas 1.4.1

### 6.1 PDF Download Button Handler

**Location:** `script.js:24-29`

**Code:**
```javascript
const downloadPdfBtn = document.getElementById('downloadPdf');
if (downloadPdfBtn) {
    downloadPdfBtn.addEventListener('click', function() {
        generatePDF();
    });
}
```

**Element ID:** `downloadPdf`

**Action:** Calls `generatePDF()` function

**Button Location:** Chapter pages only (commented out in most pages)

---

### 6.2 generatePDF Function

**Location:** `script.js:210-288`

**Function Signature:**
```javascript
function generatePDF()
```

**Return Value:** `void` (generates and downloads PDF)

**Dependencies:**
```javascript
const { jsPDF } = window.jspdf;  // Access jsPDF from global scope
```

---

#### PDF Configuration

**Location:** `script.js:212-216`

**Code:**
```javascript
const doc = new jsPDF('p', 'mm', 'a4');
const pageWidth = doc.internal.pageSize.getWidth();
const pageHeight = doc.internal.pageSize.getHeight();
const margin = 10;
```

**Parameters:**
- `'p'` - Portrait orientation
- `'mm'` - Millimeter units
- `'a4'` - A4 page size (210 x 297 mm)

**Constants:**
- `pageWidth` = 210mm
- `pageHeight` = 297mm
- `margin` = 10mm

---

#### Title Page Generation

**Location:** `script.js:218-238`

**Code:**
```javascript
// Title
doc.setFontSize(24);
doc.setTextColor(75, 110, 251); // Primary color (#4B6EFB)
doc.text('AI Fluency', pageWidth/2, 60, { align: 'center' });

// Subtitle
doc.setFontSize(16);
doc.setTextColor(51, 51, 51); // Text dark
doc.text('Digital Infographic for Students', pageWidth/2, 75, { align: 'center' });

// Description
doc.setFontSize(12);
doc.text('An interactive guide to understanding Artificial Intelligence',
         pageWidth/2, 85, { align: 'center' });

// Logo/graphic (circle with "AI" text)
doc.setLineWidth(0.5);
doc.setDrawColor(75, 110, 251);
doc.circle(pageWidth/2, 120, 20, 'S'); // 'S' = Stroke only
doc.setFontSize(14);
doc.text('AI', pageWidth/2 - 5, 124);

// Footer with date
doc.setFontSize(10);
doc.text(`Generated on ${new Date().toLocaleDateString()}`,
         pageWidth/2, pageHeight - 20, { align: 'center' });
```

**Title Page Elements:**
1. **Title:** "AI Fluency" (24pt, blue)
2. **Subtitle:** "Digital Infographic for Students" (16pt)
3. **Description:** Guide description (12pt)
4. **Logo:** Circle with "AI" text
5. **Date:** Generation timestamp (10pt, footer)

---

#### Content Capture with html2canvas

**Location:** `script.js:241-284`

**Code:**
```javascript
const currentPage = document.querySelector('main');

if (currentPage) {
    html2canvas(currentPage, {
        scale: 2,
        logging: false,
        useCORS: true
    }).then(canvas => {
        // Process canvas and add to PDF
    });
} else {
    alert('Cannot generate PDF: Content not found');
}
```

**html2canvas Options:**
- `scale: 2` - High resolution (2x)
- `logging: false` - Suppress console logs
- `useCORS: true` - Allow cross-origin images

**Target Element:** `<main>` tag (entire page content)

---

#### Image to PDF Conversion

**Location:** `script.js:251-256`

**Code:**
```javascript
doc.addPage();  // Add new page after title page

const imgData = canvas.toDataURL('image/png');
const imgWidth = pageWidth - (margin * 2);
const imgHeight = (canvas.height * imgWidth) / canvas.width;
```

**Calculations:**
- `imgData` - Base64 PNG image
- `imgWidth` - Page width minus margins (190mm)
- `imgHeight` - Proportional height based on aspect ratio

---

#### Multi-Page Content Handling

**Location:** `script.js:258-280`

**Code:**
```javascript
if (imgHeight > pageHeight - (margin * 2)) {
    // Content spans multiple pages
    let heightLeft = imgHeight;
    let position = 0;
    let page = 1;

    while (heightLeft > 0) {
        doc.addImage(imgData, 'PNG', margin, margin + position, imgWidth, imgHeight);
        heightLeft -= (pageHeight - margin * 2);
        position -= pageHeight;

        if (heightLeft > 0) {
            doc.addPage();
            page++;
        }
    }
} else {
    // Content fits on one page
    doc.addImage(imgData, 'PNG', margin, margin, imgWidth, imgHeight);
}
```

**Algorithm:**
1. Check if content height exceeds page height
2. If yes, loop and add content across multiple pages
3. Calculate remaining height after each page
4. Shift position upward for next page (`position -= pageHeight`)
5. Add new page if content remains

**Visual Representation:**
```
Page 1: [Content Top]
        ↓
Page 2: [Content Middle]
        ↓
Page 3: [Content Bottom]
```

---

#### PDF Download

**Location:** `script.js:283`

**Code:**
```javascript
doc.save('AI_Fluency_Infographic.pdf');
```

**Filename:** `AI_Fluency_Infographic.pdf`

**Action:** Triggers browser download dialog

---

### PDF Generation Flow Diagram

```
User clicks "Download PDF"
       ↓
generatePDF() called
       ↓
Create jsPDF instance
       ↓
Add title page
(logo, title, date)
       ↓
Select <main> element
       ↓
html2canvas captures content
       ↓
Convert canvas to PNG
       ↓
Calculate dimensions
       ↓
┌──────┴──────┐
Single Page?   Multiple Pages?
↓              ↓
Add to PDF     Loop: Add pages
               Split content
       ↓              ↓
       └──────┬───────┘
              ↓
       doc.save()
              ↓
    Download triggered
```

---

## Quiz System

**Purpose:** Interactive quiz functionality for module assessments

**Location:** Embedded in `module*Quiz.html` files (e.g., `module1Quiz.html`)

**Structure:** JavaScript embedded in `<script>` tags within HTML

### Architecture

**Data Structure:**
```javascript
const quizData = [
    {
        id: 1,
        question: "What is artificial intelligence (AI)?",
        options: [
            "Option A",
            "Option B",
            "Option C",
            "Option D"
        ],
        correctAnswer: 1,  // Index of correct option (0-based)
        explanation: "Explanation text shown after submission"
    },
    // ... more questions
];
```

---

### Quiz Global Variables

**Location:** `module1Quiz.html:495-517`

```javascript
// DOM elements
const questionsContainer = document.getElementById('questions-container');
const pagination = document.getElementById('pagination');
const progressFill = document.getElementById('progressFill');
const progressPercentage = document.getElementById('progressPercentage');
const answeredCount = document.getElementById('answeredCount');
const questionLabel = document.getElementById('questionLabel');
const quizForm = document.getElementById('quiz-form');
const submitBtn = document.getElementById('submit-btn');
const resultsDiv = document.getElementById('results');
const scoreValue = document.getElementById('score-value');
const feedback = document.getElementById('feedback');
const restartBtn = document.getElementById('restart-btn');
const reviewBtn = document.getElementById('review-btn');
const progressContainer = document.getElementById('progressContainer');

// State variables
let currentPage = 0;
const questionsPerPage = 5;
const totalPages = Math.ceil(quizData.length / questionsPerPage);
let userAnswers = new Array(quizData.length).fill(null);
let quizSubmitted = false;
```

**State Variables:**
- `currentPage` - Current page index (0-based)
- `questionsPerPage` - Questions shown per page (5)
- `totalPages` - Calculated total pages
- `userAnswers` - Array storing user selections
- `quizSubmitted` - Boolean flag for submission state

---

### Quiz Functions

#### 7.1 initQuiz()

**Location:** `module1Quiz.html:520-524`

**Code:**
```javascript
function initQuiz() {
    createPagination();
    showPage(0);
    updateProgress();
}
```

**Purpose:** Initializes the quiz interface

**Call Order:**
1. Create pagination buttons
2. Show first page of questions
3. Update progress bar

**Called:** Automatically on page load

---

#### 7.2 createPagination()

**Location:** `module1Quiz.html:527-537`

**Code:**
```javascript
function createPagination() {
    pagination.innerHTML = '';
    for (let i = 0; i < totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.className = 'page-btn';
        pageBtn.textContent = i + 1;
        pageBtn.addEventListener('click', () => showPage(i));
        pagination.appendChild(pageBtn);
    }
}
```

**Purpose:** Creates numbered pagination buttons

**Generates:**
```html
<div class="pagination">
    <button class="page-btn">1</button>
    <button class="page-btn">2</button>
</div>
```

**Button Properties:**
- **Type:** `button` (prevents form submission)
- **Class:** `page-btn`
- **Text:** Page number (1, 2, 3, ...)
- **Event:** Click calls `showPage(index)`

---

#### 7.3 showPage(pageIndex)

**Location:** `module1Quiz.html:540-568`

**Function Signature:**
```javascript
function showPage(pageIndex)
```

**Parameters:**
- `pageIndex` (Number) - Page to display (0-based)

**Code:**
```javascript
function showPage(pageIndex) {
    if (pageIndex < 0 || pageIndex >= totalPages) return;

    currentPage = pageIndex;
    questionsContainer.innerHTML = '';

    const startIndex = pageIndex * questionsPerPage;
    const endIndex = Math.min(startIndex + questionsPerPage, quizData.length);

    for (let i = startIndex; i < endIndex; i++) {
        const question = quizData[i];
        createQuestionElement(question, i);
    }

    // Update active page button
    const pageButtons = pagination.querySelectorAll('.page-btn');
    pageButtons.forEach((btn, index) => {
        if (index === pageIndex) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    questionLabel.textContent = `Page ${pageIndex + 1} of ${totalPages}`;
    updateProgress();
}
```

**What It Does:**
1. **Validates** page index
2. **Clears** question container
3. **Calculates** question range (e.g., questions 0-4 for page 0)
4. **Creates** question elements for the page
5. **Highlights** active pagination button
6. **Updates** progress bar

**Example:**
- Page 0: Questions 0-4
- Page 1: Questions 5-9
- Page 2: Questions 10-14 (if applicable)

---

#### 7.4 createQuestionElement(question, index)

**Location:** `module1Quiz.html:571-631`

**Function Signature:**
```javascript
function createQuestionElement(question, index)
```

**Parameters:**
- `question` (Object) - Question data object
- `index` (Number) - Question index in `quizData` array

**Code:**
```javascript
function createQuestionElement(question, index) {
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-container';
    questionDiv.id = `question-${index}`;

    // Question text
    const questionTitle = document.createElement('div');
    questionTitle.className = 'question';
    questionTitle.textContent = `${index + 1}. ${question.question}`;

    // Options container
    const optionsDiv = document.createElement('div');
    optionsDiv.className = 'options';

    // Create radio buttons for each option
    question.options.forEach((option, optionIndex) => {
        const optionDiv = document.createElement('div');
        optionDiv.className = 'option';

        // Highlight correct/incorrect if submitted
        if (quizSubmitted) {
            if (optionIndex === question.correctAnswer) {
                optionDiv.classList.add('correct');
            } else if (userAnswers[index] === optionIndex &&
                       userAnswers[index] !== question.correctAnswer) {
                optionDiv.classList.add('incorrect');
            }
        }

        // Radio input
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = `question-${index}`;
        radio.id = `question-${index}-option-${optionIndex}`;
        radio.value = optionIndex;
        radio.checked = userAnswers[index] === optionIndex;
        radio.disabled = quizSubmitted;

        radio.addEventListener('change', () => {
            userAnswers[index] = optionIndex;
            updateProgress();
        });

        // Label
        const label = document.createElement('label');
        label.htmlFor = `question-${index}-option-${optionIndex}`;
        label.textContent = option;

        optionDiv.appendChild(radio);
        optionDiv.appendChild(label);
        optionsDiv.appendChild(optionDiv);
    });

    questionDiv.appendChild(questionTitle);
    questionDiv.appendChild(optionsDiv);

    // Add explanation if quiz is submitted
    if (quizSubmitted) {
        const explanationDiv = document.createElement('div');
        explanationDiv.className = 'explanation';
        explanationDiv.style.display = 'block';
        explanationDiv.innerHTML = `<strong>Explanation:</strong> ${question.explanation}`;
        questionDiv.appendChild(explanationDiv);
    }

    questionsContainer.appendChild(questionDiv);
}
```

**Generated HTML Structure:**
```html
<div class="question-container" id="question-0">
    <div class="question">1. What is artificial intelligence (AI)?</div>
    <div class="options">
        <div class="option">
            <input type="radio" name="question-0" id="question-0-option-0" value="0">
            <label for="question-0-option-0">Option A</label>
        </div>
        <!-- More options... -->
    </div>
    <!-- Explanation (if submitted) -->
</div>
```

**CSS Classes:**
- `.correct` - Green background for correct answer
- `.incorrect` - Red background for incorrect answer
- `.explanation` - Explanation text box

---

#### 7.5 updateProgress()

**Location:** `module1Quiz.html:634-641`

**Code:**
```javascript
function updateProgress() {
    const answeredQuestionsCount = userAnswers.filter(answer => answer !== null).length;
    const progressPercent = (answeredQuestionsCount / quizData.length) * 100;

    progressFill.style.width = `${progressPercent}%`;
    progressPercentage.textContent = `${Math.round(progressPercent)}%`;
    answeredCount.textContent = `${answeredQuestionsCount} of ${quizData.length} answered`;
}
```

**Purpose:** Updates progress bar based on answered questions

**Calculates:**
- Count of answered questions (non-null values in `userAnswers`)
- Progress percentage
- Updates UI elements

**Updates:**
- Progress bar width
- Progress percentage text
- "X of Y answered" counter

---

#### 7.6 calculateScore()

**Location:** `module1Quiz.html:655-663`

**Code:**
```javascript
function calculateScore() {
    let score = 0;
    userAnswers.forEach((answer, index) => {
        if (answer === quizData[index].correctAnswer) {
            score++;
        }
    });
    return score;
}
```

**Function Signature:**
```javascript
function calculateScore(): number
```

**Return Value:** Number of correct answers

**Algorithm:**
- Loops through `userAnswers`
- Compares each answer with `quizData[index].correctAnswer`
- Increments score for correct matches

---

#### 7.7 generateFeedback(score, total)

**Location:** `module1Quiz.html:666-680`

**Code:**
```javascript
function generateFeedback(score, total) {
    const percentage = (score / total) * 100;

    if (percentage >= 90) {
        return "Outstanding! You have an excellent understanding of AI fundamentals!";
    } else if (percentage >= 80) {
        return "Great job! You've demonstrated a solid understanding of AI fundamentals.";
    } else if (percentage >= 70) {
        return "Good work! You have a good grasp of the basics of AI.";
    } else if (percentage >= 60) {
        return "Not bad! You're on the right track, but might want to review some concepts.";
    } else {
        return "It looks like you might need to revisit the module content. Keep learning!";
    }
}
```

**Function Signature:**
```javascript
function generateFeedback(score: number, total: number): string
```

**Parameters:**
- `score` - Number of correct answers
- `total` - Total number of questions

**Return Value:** Feedback message string

**Grading Scale:**
- 90-100%: Outstanding
- 80-89%: Great job
- 70-79%: Good work
- 60-69%: Not bad
- 0-59%: Need to review

---

#### 7.8 showResults()

**Location:** `module1Quiz.html:683-690`

**Code:**
```javascript
function showResults() {
    const score = calculateScore();
    scoreValue.textContent = `${score}/${quizData.length}`;
    feedback.textContent = generateFeedback(score, quizData.length);
    resultsDiv.style.display = 'block';
    submitBtn.style.display = 'none';
    progressContainer.style.display = 'none';
}
```

**Purpose:** Displays quiz results after submission

**Actions:**
1. Calculates final score
2. Updates score display (e.g., "8/10")
3. Generates and shows feedback message
4. Shows results container
5. Hides submit button
6. Hides progress bar

---

#### 7.9 Quiz Submission Handler

**Location:** `module1Quiz.html:720-735`

**Code:**
```javascript
quizForm.addEventListener('submit', function(e) {
    e.preventDefault();

    // Check if all questions are answered
    const allAnswered = userAnswers.every(answer => answer !== null);

    if (!allAnswered) {
        alert('Please answer all questions before submitting.');
        return;
    }

    quizSubmitted = true;
    showResults();

    // Scroll to top to show results
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
```

**Validation:**
- Checks if all questions are answered
- Shows alert if incomplete
- Prevents submission if not all answered

**Actions on Submit:**
1. Sets `quizSubmitted = true`
2. Calls `showResults()`
3. Scrolls to top of page

---

#### 7.10 Restart Quiz

**Location:** `module1Quiz.html:738-749`

**Code:**
```javascript
restartBtn.addEventListener('click', function() {
    quizSubmitted = false;
    userAnswers = new Array(quizData.length).fill(null);
    resultsDiv.style.display = 'none';
    submitBtn.style.display = 'block';
    progressContainer.style.display = 'flex';
    showPage(0);
    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
```

**Purpose:** Resets quiz to initial state

**Actions:**
1. Resets `quizSubmitted` flag
2. Clears all user answers
3. Hides results
4. Shows submit button
5. Shows progress bar
6. Returns to page 1
7. Resets progress to 0%
8. Scrolls to top

---

#### 7.11 Review Answers

**Location:** `module1Quiz.html:752-767`

**Code:**
```javascript
reviewBtn.addEventListener('click', function() {
    resultsDiv.style.display = 'none';
    submitBtn.style.display = 'none';
    progressContainer.style.display = 'flex';

    if (!quizSubmitted) {
        quizSubmitted = true;
    }

    showPage(0);
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
```

**Purpose:** Allows user to review answers with explanations

**Behavior:**
- Keeps `quizSubmitted = true` (shows correct/incorrect highlighting)
- Hides results panel
- Shows all questions with:
  - ✅ Green highlight for correct answers
  - ❌ Red highlight for incorrect selected answers
  - Explanation text below each question

---

### Quiz Flow Diagram

```
Page Load
    ↓
initQuiz()
    ↓
Create pagination
Show page 1
    ↓
User answers questions
(updateProgress() on each answer)
    ↓
User navigates pages
(showPage(index))
    ↓
User clicks "Submit"
    ↓
Validate all answered?
    ↓ No          ↓ Yes
Alert        calculateScore()
             generateFeedback()
             showResults()
    ↓
Results displayed
    ↓
┌───────────────┴────────────┐
↓                             ↓
"Take Again"              "Review Answers"
restartBtn clicked        reviewBtn clicked
↓                             ↓
Reset quiz                Show highlighted answers
Return to page 1          with explanations
```

---

## Event Handlers

### Summary of All Event Listeners

| Element/Event | Location | Handler | Purpose |
|--------------|----------|---------|---------|
| `DOMContentLoaded` | script.js:1 | Anonymous | Initialize page |
| `#startCourse` click | script.js:5 | Anonymous | Navigate to course start |
| `#viewContents` click | script.js:12 | Anonymous | Scroll to courses section |
| `#downloadPdf` click | script.js:26 | `generatePDF()` | Download PDF |
| `.graphic-element` mouseover | script.js:34 | Anonymous | Scale up graphic |
| `.graphic-element` mouseout | script.js:38 | Anonymous | Scale down graphic |
| `a[href^="#"]` click | script.js:45 | Anonymous | Smooth scroll |
| `#hamburger` click | script.js:61 | Anonymous | Open mobile menu |
| `#closeMenu` click | script.js:69 | Anonymous | Close mobile menu |
| `.mobile-nav-link` click | script.js:77 | Anonymous | Close menu on nav |
| `body` click | script.js:84 | Anonymous | Close menu on backdrop |
| `.nav-tab` click | script.js:101 | Anonymous | Activate tab |
| `window` scroll | script.js:110 | Anonymous | Update active tab |
| `beforeinstallprompt` | script.js:159 | Anonymous | Handle PWA install |
| `#install-button` click | script.js:173 | `promptInstall()` | Trigger install |
| `appinstalled` | script.js:199 | Anonymous | Handle post-install |

---

## Utility Functions

### None Currently

**Note:** All functions in `script.js` are specific event handlers or feature implementations. There are no general-purpose utility functions.

**Future Considerations:**
When migrating to LMS with backend, consider creating utility modules:

```javascript
// utils/dom.js
export function querySelector(selector) { ... }
export function createElement(tag, attrs, children) { ... }

// utils/storage.js
export function saveToLocalStorage(key, value) { ... }
export function getFromLocalStorage(key) { ... }

// utils/api.js
export async function fetchJSON(url, options) { ... }
export async function postJSON(url, data) { ... }
```

---

## Global Variables

### script.js Global Scope

```javascript
let deferredPrompt;  // BeforeInstallPromptEvent
const installButton;  // HTMLButtonElement
```

### Quiz Files Global Scope

```javascript
const quizData = [];  // Array<QuizQuestion>
let currentPage = 0;  // number
const questionsPerPage = 5;  // number
const totalPages;  // number
let userAnswers = [];  // Array<number|null>
let quizSubmitted = false;  // boolean
```

---

## Browser Compatibility

### ES6+ Features Used

| Feature | Browser Support | Fallback |
|---------|----------------|----------|
| `const`/`let` | IE11+ | Transpile with Babel |
| Arrow functions `=>` | IE11+ | Transpile with Babel |
| Template literals | IE11+ | Transpile with Babel |
| `Array.forEach()` | IE9+ | ✅ Native |
| `Array.filter()` | IE9+ | ✅ Native |
| `Array.every()` | IE9+ | ✅ Native |
| `querySelector()` | IE8+ | ✅ Native |
| `classList` | IE10+ | ✅ Native |
| `async/await` | IE Not supported | Transpile with Babel |
| `Promise` | IE Not supported | Polyfill required |

### API Support

| API | Chrome | Firefox | Safari | Edge | Notes |
|-----|--------|---------|--------|------|-------|
| Service Worker | 40+ | 44+ | 11.1+ | 17+ | ✅ Full PWA support |
| Web App Manifest | 39+ | 53+ | 13+ | 79+ | ✅ Full PWA support |
| beforeinstallprompt | 68+ | ❌ | ❌ | 79+ | ⚠️ Limited to Chromium |
| LocalStorage | 4+ | 3.5+ | 4+ | 8+ | ✅ Universal |
| html2canvas | All modern | All modern | All modern | All modern | ✅ Library handles compatibility |
| jsPDF | All modern | All modern | All modern | All modern | ✅ Library handles compatibility |

### Recommendations

**For Maximum Compatibility:**
1. **Transpile ES6+** with Babel for IE11 support
2. **Add Polyfills:**
   ```html
   <script src="https://polyfill.io/v3/polyfill.min.js?features=Promise,Array.prototype.forEach"></script>
   ```
3. **Test on:**
   - Chrome (latest)
   - Firefox (latest)
   - Safari (iOS & macOS)
   - Edge (Chromium)
   - Safari iOS (PWA install testing)

---

## Future Enhancements

### When Backend is Implemented

#### 1. AJAX API Calls

**Replace static data with API calls:**

```javascript
// Current: Static quiz data
const quizData = [ /* hardcoded */ ];

// Future: Fetch from API
async function loadQuizData(moduleId) {
    const response = await fetch(`/api/quizzes/${moduleId}`);
    const quizData = await response.json();
    return quizData;
}
```

#### 2. Progress Persistence

**Save progress to database:**

```javascript
async function saveProgress(chapterId, completed) {
    await fetch('/api/progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            chapter_id: chapterId,
            completed: completed,
            time_spent: timeSpentSeconds
        })
    });
}
```

#### 3. Quiz Submission

**Submit to backend for grading:**

```javascript
async function submitQuiz(quizId, answers) {
    const response = await fetch('/api/quiz-attempts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            quiz_id: quizId,
            answers: answers
        })
    });
    return await response.json();
}
```

#### 4. Authentication

**Add JWT authentication:**

```javascript
// Store token after login
function storeAuthToken(token) {
    localStorage.setItem('auth_token', token);
}

// Add to API requests
async function authenticatedFetch(url, options = {}) {
    const token = localStorage.getItem('auth_token');
    options.headers = {
        ...options.headers,
        'Authorization': `Bearer ${token}`
    };
    return fetch(url, options);
}
```

#### 5. Real-Time Features

**WebSocket for live updates:**

```javascript
const socket = new WebSocket('wss://scibono.co.za/ws');

socket.onmessage = function(event) {
    const data = JSON.parse(event.data);
    if (data.type === 'progress_update') {
        updateDashboard(data.progress);
    }
};
```

---

## Related Documents

### Technical Documentation
- [Current Architecture](../01-Architecture/current-architecture.md) - Overall system design
- [Future Architecture](../01-Architecture/future-architecture.md) - Planned LMS architecture
- [Service Worker Guide](service-worker.md) (coming soon) - PWA caching implementation

### Code Reference
- [CSS System Documentation](css-system.md) (coming soon) - Styling reference
- [HTML Structure Guide](html-structure.md) (coming soon) - Markup patterns

### Development
- [Development Setup](../04-Development/setup-guide.md) (coming soon) - Local environment
- [Coding Standards](../04-Development/coding-standards.md) (coming soon) - Code style guide

### External Resources
- [MDN: JavaScript Reference](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
- [jsPDF Documentation](https://github.com/parallax/jsPDF)
- [html2canvas Documentation](https://html2canvas.hertzen.com/)
- [PWA Documentation](https://web.dev/progressive-web-apps/)

---

## Document Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2025-10-27 | 1.0 | Dev Team | Initial JavaScript API documentation |

---

**END OF DOCUMENT**

*This JavaScript API reference documents all client-side code in the current Sci-Bono AI Fluency static PWA. Use this as a reference for understanding, maintaining, and extending the codebase.*
