# CSS System Documentation - Sci-Bono AI Fluency Platform

**Document Version:** 1.0
**Last Updated:** 2025-10-27
**Author:** Development Team
**Status:** Complete

---

## Table of Contents

1. [Introduction](#introduction)
2. [File Structure](#file-structure)
3. [Design System](#design-system)
4. [Global Styles](#global-styles)
5. [Component Styles](#component-styles)
6. [Layout System](#layout-system)
7. [Responsive Design](#responsive-design)
8. [Animation & Transitions](#animation--transitions)
9. [Typography](#typography)
10. [Color Palette](#color-palette)
11. [Spacing System](#spacing-system)
12. [Component Library](#component-library)
13. [Utilities](#utilities)
14. [Best Practices](#best-practices)
15. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This document provides comprehensive documentation for the CSS architecture of the Sci-Bono AI Fluency platform. It covers the design system, component styles, responsive layouts, and best practices for maintaining consistent styling across the application.

### CSS Philosophy

**Design Principles:**
- **Component-based** - Modular, reusable styles
- **Mobile-first** - Optimize for mobile, enhance for desktop
- **CSS Variables** - Centralized theming system
- **BEM-like naming** - Clear, descriptive class names
- **Performance** - Minimal CSS, optimized selectors

### File Organization

**Total Files:** 2
**Total Lines:** 2,409 lines
**Structure:** Main styles + Module-specific styles

---

## File Structure

### CSS Files Overview

```
/css/
├── styles.css          (1,692 lines) - Main stylesheet
│   ├── Global styles
│   ├── Layout components
│   ├── Landing page
│   ├── Chapter pages
│   └── Common components
│
└── stylesModules.css   (717 lines) - Module-specific styles
    ├── Module pages
    ├── Chapter cards
    ├── Quiz styles
    └── Module navigation
```

---

### styles.css

**Location:** `/css/styles.css`
**Size:** 1,692 lines
**Purpose:** Main stylesheet for the entire application

**Contents:**
1. CSS Variables (Design tokens)
2. Reset & Global styles
3. Header & Navigation
4. Hero section (landing page)
5. Course cards & timeline
6. Chapter pages
7. Footer
8. Mobile responsive styles
9. PWA-specific styles

---

### stylesModules.css

**Location:** `/css/stylesModules.css`
**Size:** 717 lines
**Purpose:** Styles for module overview pages

**Contents:**
1. Module badges
2. Module headers
3. Chapter cards grid
4. Module navigation
5. Quiz integration styles
6. Module-specific responsive styles

---

## Design System

### CSS Variables (Design Tokens)

**Location:** `styles.css:24-36`

```css
:root {
    /* Primary Colors */
    --primary-color: #4B6EFB;       /* Main brand blue */
    --secondary-color: #6E4BFB;     /* Purple accent */
    --accent-color: #FB4B4B;        /* Red for CTAs */
    --accent-green: #4BFB9D;        /* Success green */

    /* Text Colors */
    --text-dark: #333333;           /* Primary text */
    --text-light: #666666;          /* Secondary text */

    /* Background Colors */
    --background-light: #F9F9FF;    /* Page background */
    --white: #FFFFFF;               /* Pure white */
    --grey-light: #EEEEEE;          /* Light grey */

    /* Effects */
    --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);  /* Standard shadow */
    --transition: all 0.3s ease;               /* Standard transition */
}
```

### Using CSS Variables

**In CSS:**
```css
.my-button {
    background-color: var(--primary-color);
    color: var(--white);
    box-shadow: var(--shadow);
    transition: var(--transition);
}
```

**In JavaScript:**
```javascript
// Get CSS variable value
const primaryColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--primary-color');

// Set CSS variable
document.documentElement.style
    .setProperty('--primary-color', '#FF0000');
```

**Benefits:**
- ✅ Centralized theme management
- ✅ Easy to change colors site-wide
- ✅ Consistent design language
- ✅ Dark mode ready (future feature)

---

## Global Styles

### CSS Reset

**Location:** `styles.css:39-43`

```css
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
```

**Purpose:**
- Removes default browser margins/padding
- Sets `box-sizing` to include padding in width calculations
- Creates consistent baseline across browsers

---

### Body & HTML

**Location:** `styles.css:45-50`

```css
html, body {
    font-family: 'Poppins', sans-serif;
    color: var(--text-dark);
    background-color: var(--background-light);
    line-height: 1.6;
}
```

**Typography:**
- **Primary Font:** Poppins (body text)
- **Heading Font:** Montserrat (headings)
- **Line Height:** 1.6 (optimal readability)
- **Base Color:** Dark grey (#333333)
- **Background:** Light blue-tinted white (#F9F9FF)

---

### Headings

**Location:** `styles.css:52-55`

```css
h1, h2, h3, h4 {
    font-family: 'Montserrat', sans-serif;
    margin-bottom: 0.5rem;
}
```

**Font Sizes (default browser scaling):**
- `h1` - 2em (32px)
- `h2` - 1.5em (24px)
- `h3` - 1.17em (18.72px)
- `h4` - 1em (16px)

**Custom Sizes:**
```css
.module-header h1 {
    font-size: 2.5rem;  /* 40px */
}

.hero h1 {
    font-size: 3rem;    /* 48px */
}
```

---

### Links

**Location:** `styles.css:57-65`

```css
a {
    color: var(--primary-color);
    text-decoration: none;
    transition: var(--transition);
}

a:hover {
    color: var(--secondary-color);
}
```

**Behavior:**
- Default: Blue (#4B6EFB)
- Hover: Purple (#6E4BFB)
- No underline by default
- Smooth color transition

---

### Buttons

**Location:** `styles.css:71-79`

```css
button {
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    padding: 0.6rem 1.2rem;
    border-radius: 30px;
    border: none;
    transition: var(--transition);
    font-weight: 500;
}
```

**Button Variants:**

**Primary Button:**
```css
.btn-primary {
    background-color: var(--primary-color);
    color: var(--white);
}

.btn-primary:hover {
    background-color: var(--secondary-color);
    transform: translateY(-2px);
}
```

**Outline Button:**
```css
.login-btn {
    color: var(--primary-color);
    background-color: transparent;
    border: 2px solid var(--primary-color);
}

.login-btn:hover {
    background-color: var(--primary-color);
    color: var(--white);
}
```

---

## Component Styles

### Header Component

**Location:** `styles.css:86-96`

```css
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background-color: var(--white);
    box-shadow: var(--shadow);
    position: sticky;
    top: 0;
    z-index: 100;
}
```

**Structure:**
```
┌─────────────────────────────────────────────────┐
│ Header (sticky)                                 │
│  ┌────────┐    ┌──────────┐    ┌─────────────┐│
│  │  Logo  │    │   Nav    │    │  Controls   ││
│  └────────┘    └──────────┘    └─────────────┘│
└─────────────────────────────────────────────────┘
```

**Key Properties:**
- **Layout:** Flexbox (justify-content: space-between)
- **Position:** Sticky (stays at top on scroll)
- **Z-index:** 100 (above content)
- **Shadow:** Subtle box-shadow for depth

---

### Logo Component

**Location:** `styles.css:116-133`

```css
.logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.logo h1 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    line-height: 1;
}

.logo img {
    display: block;
    margin: 0;
    padding: 0;
    height: 4.5rem;
}
```

**HTML Structure:**
```html
<div class="logo">
    <a href="index.html">
        <svg width="50" height="50">...</svg>
    </a>
    <h1>AI Fluency</h1>
</div>
```

---

### Navigation Component

**Location:** `styles.css:186-224`

```css
.main-nav {
    display: flex;
    align-items: center;
    flex: 1;
    justify-content: center;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 2rem;
}

.nav-link {
    color: var(--text-dark);
    font-weight: 500;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    transition: var(--transition);
}

.nav-link:hover {
    color: var(--primary-color);
    background-color: rgba(75, 110, 251, 0.1);
}

.nav-link.active {
    color: var(--white);
    background-color: var(--primary-color);
}
```

**States:**
- **Default:** Dark text, no background
- **Hover:** Blue text, light blue background (10% opacity)
- **Active:** White text, blue background

---

### Mobile Menu (Hamburger)

**Location:** `styles.css:227-249`

```css
.hamburger {
    display: none;  /* Hidden on desktop */
    flex-direction: column;
    justify-content: space-around;
    width: 30px;
    height: 25px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    z-index: 101;
}

.hamburger span {
    width: 30px;
    height: 3px;
    background-color: var(--primary-color);
    border-radius: 3px;
    transition: var(--transition);
}

.hamburger:hover span {
    background-color: var(--secondary-color);
}
```

**Structure:**
```
┌──────────┐
│ ======== │  <- span (3px height)
│ ======== │  <- span
│ ======== │  <- span
└──────────┘
```

**Behavior:**
- Hidden on desktop (`display: none`)
- Shown on mobile (`@media` query changes to `display: flex`)
- Three horizontal bars (spans)
- Color changes on hover

---

### Mobile Side Menu

**Location:** `styles.css:252-324`

```css
.mobile-menu {
    position: fixed;
    top: 0;
    right: -100%;  /* Hidden off-screen */
    width: 80%;
    max-width: 400px;
    height: 100vh;
    background-color: var(--white);
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    transition: right 0.3s ease;
    overflow-y: auto;
}

.mobile-menu.active {
    right: 0;  /* Slide in */
}
```

**Animation:**
```
Closed:  [Screen]  |menu| (right: -100%)
              ↓
Opened:  [Scre|menu| (right: 0)
```

**Features:**
- Slides in from right
- Takes up 80% of screen width (max 400px)
- Full viewport height
- Backdrop overlay when open
- Smooth transition

---

### Hero Section

**Location:** `styles.css:380-450`

```css
.hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4rem 2rem;
    max-width: 1200px;
    margin: 0 auto;
    gap: 2rem;
}

.hero-content {
    flex: 1;
}

.hero h1 {
    font-size: 3rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.hero p {
    font-size: 1.2rem;
    color: var(--text-light);
    margin-bottom: 2rem;
}

.hero-visual {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
}
```

**Layout:**
```
┌────────────────────────────────────────────┐
│  ┌──────────────┐  ┌──────────────┐       │
│  │              │  │              │       │
│  │ Hero Content │  │ Hero Visual  │       │
│  │  (Text, CTA) │  │  (Graphic)   │       │
│  │              │  │              │       │
│  └──────────────┘  └──────────────┘       │
└────────────────────────────────────────────┘
```

---

### Card Components

**Location:** `stylesModules.css:64-76`

```css
.chapter-card {
    background-color: var(--white);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: var(--transition);
    display: flex;
    height: 100%;
}

.chapter-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}
```

**Card Structure:**
```
┌─────────────────────────────┐
│ ┌────┐  Chapter Title       │
│ │Icon│  Description...      │
│ └────┘  → Start Chapter     │
└─────────────────────────────┘
```

**Hover Effect:**
- Lifts up 5px (`translateY(-5px)`)
- Shadow increases (depth effect)
- Smooth transition (0.3s)

---

### Module Badge

**Location:** `stylesModules.css:1-10`

```css
.module-badge {
    background-color: var(--primary-color);
    color: var(--white);
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 30px;
    font-weight: 500;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}
```

**Example:**
```html
<div class="module-badge">Module 1: AI Foundations</div>
```

**Visual:**
```
┌─────────────────────────────┐
│ Module 1: AI Foundations    │ <- Blue pill shape
└─────────────────────────────┘
```

---

### Footer Component

**Location:** `styles.css:1500-1550`

```css
footer {
    background-color: var(--text-dark);
    color: var(--white);
    padding: 2rem;
    text-align: center;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-links {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 1rem;
}

.footer-links a {
    color: var(--white);
    opacity: 0.8;
    transition: var(--transition);
}

.footer-links a:hover {
    opacity: 1;
    color: var(--accent-green);
}
```

---

## Layout System

### Container System

**Max Width Container:**
```css
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}
```

**Breakpoints:**
- **Small (mobile):** < 768px → 100% width, 1rem padding
- **Medium (tablet):** 768px - 1024px → max-width: 900px
- **Large (desktop):** > 1024px → max-width: 1200px

---

### Grid System

**Chapter Cards Grid:**
```css
.chapters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}
```

**Behavior:**
- Automatically creates columns
- Minimum card width: 350px
- Cards stretch to fill space (`1fr`)
- Responsive without media queries

**Visual:**
```
Desktop (1200px):
┌────┐ ┌────┐ ┌────┐
│    │ │    │ │    │  <- 3 columns
└────┘ └────┘ └────┘

Tablet (768px):
┌────┐ ┌────┐
│    │ │    │          <- 2 columns
└────┘ └────┘

Mobile (400px):
┌────┐
│    │                 <- 1 column
└────┘
```

---

### Flexbox Layouts

**Two-Column Layout:**
```css
.two-column {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.column {
    flex: 1;
}
```

**Example:** Hero section, Module intro

---

## Responsive Design

### Mobile-First Approach

**Philosophy:** Design for mobile, enhance for desktop

**Base Styles (Mobile):**
```css
.hero {
    flex-direction: column;  /* Stack vertically */
    padding: 2rem 1rem;
}
```

**Desktop Enhancement:**
```css
@media (min-width: 768px) {
    .hero {
        flex-direction: row;  /* Side by side */
        padding: 4rem 2rem;
    }
}
```

---

### Breakpoints

**Primary Breakpoint:** 768px (mobile ↔ desktop)

```css
@media (max-width: 768px) {
    /* Mobile styles */
}
```

**Common Responsive Patterns:**

**1. Hide/Show Elements:**
```css
.desktop-only {
    display: block;
}

@media (max-width: 768px) {
    .desktop-only {
        display: none;
    }
}
```

**2. Stack Columns:**
```css
.hero {
    display: flex;
    flex-direction: row;
}

@media (max-width: 768px) {
    .hero {
        flex-direction: column;
    }
}
```

**3. Adjust Typography:**
```css
.hero h1 {
    font-size: 3rem;
}

@media (max-width: 768px) {
    .hero h1 {
        font-size: 2rem;
    }
}
```

**4. Grid Adjustments:**
```css
.chapters-grid {
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
}

@media (max-width: 768px) {
    .chapters-grid {
        grid-template-columns: 1fr;  /* Single column */
    }
}
```

---

### Mobile Navigation

**Desktop:**
```
[ Logo ]  [ Home | Courses | About ]  [ Login | Sign Up ]
```

**Mobile:**
```
[ Logo ]                              [ ☰ ]

(When menu opens)
┌─────────────┐
│ ✕           │
│             │
│ Home        │
│ Courses     │
│ About       │
│ Login       │
│ Sign Up     │
└─────────────┘
```

**Implementation:**
```css
@media (max-width: 768px) {
    .main-nav {
        display: none;  /* Hide desktop nav */
    }

    .hamburger {
        display: flex;  /* Show hamburger */
    }
}
```

---

## Animation & Transitions

### Standard Transition

**Global Variable:**
```css
:root {
    --transition: all 0.3s ease;
}
```

**Usage:**
```css
.button {
    transition: var(--transition);
}
```

---

### Hover Effects

**Lift Effect:**
```css
.card:hover {
    transform: translateY(-5px);
}
```

**Scale Effect:**
```css
.graphic-element:hover {
    transform: scale(1.1);
}
```

**Color Transition:**
```css
a {
    color: var(--primary-color);
    transition: color 0.3s ease;
}

a:hover {
    color: var(--secondary-color);
}
```

---

### Mobile Menu Animation

**Slide-in from right:**
```css
.mobile-menu {
    right: -100%;
    transition: right 0.3s ease;
}

.mobile-menu.active {
    right: 0;
}
```

---

### Loading Animations

**Fade In:**
```css
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.fade-in {
    animation: fadeIn 0.5s ease-in;
}
```

**Slide Up:**
```css
@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.slide-up {
    animation: slideUp 0.5s ease-out;
}
```

---

## Typography

### Font Families

**Loaded from Google Fonts:**
```css
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap');
```

**Primary:** Poppins (body text, buttons, UI)
**Secondary:** Montserrat (headings)

**Font Weights:**
- **Light:** 300 (Poppins only)
- **Regular:** 400
- **Medium:** 500
- **Semi-Bold:** 600
- **Bold:** 700

---

### Font Sizes

**Headings:**
```css
h1 { font-size: 2.5rem - 3rem; }    /* 40-48px */
h2 { font-size: 2rem; }              /* 32px */
h3 { font-size: 1.5rem; }            /* 24px */
h4 { font-size: 1.2rem; }            /* 19.2px */
```

**Body:**
```css
body { font-size: 1rem; }            /* 16px (browser default) */
p { font-size: 1rem; }
```

**Small Text:**
```css
.small { font-size: 0.9rem; }        /* 14.4px */
.tiny { font-size: 0.8rem; }         /* 12.8px */
```

---

### Line Height

**Body Text:**
```css
body {
    line-height: 1.6;  /* Optimal for readability */
}
```

**Headings:**
```css
h1, h2, h3, h4 {
    line-height: 1.2;  /* Tighter for headings */
}
```

---

### Text Alignment

**Default:** Left-aligned

**Centered:**
```css
.text-center {
    text-align: center;
}

.module-header {
    text-align: center;
}
```

---

## Color Palette

### Primary Colors

**Main Blue:**
```css
--primary-color: #4B6EFB;
```
- RGB: (75, 110, 251)
- Usage: Primary buttons, headings, links

**Purple:**
```css
--secondary-color: #6E4BFB;
```
- RGB: (110, 75, 251)
- Usage: Hover states, secondary accents

**Red:**
```css
--accent-color: #FB4B4B;
```
- RGB: (251, 75, 75)
- Usage: CTAs, install button, important actions

**Green:**
```css
--accent-green: #4BFB9D;
```
- RGB: (75, 251, 157)
- Usage: Success states, checkmarks

---

### Neutral Colors

**Text Dark:**
```css
--text-dark: #333333;
```
- Usage: Primary text, headings, body copy

**Text Light:**
```css
--text-light: #666666;
```
- Usage: Secondary text, descriptions

**Background Light:**
```css
--background-light: #F9F9FF;
```
- Usage: Page background, subtle tint

**White:**
```css
--white: #FFFFFF;
```
- Usage: Cards, modals, pure white backgrounds

**Grey Light:**
```css
--grey-light: #EEEEEE;
```
- Usage: Borders, dividers, disabled states

---

### Color Usage Examples

**Buttons:**
```css
.btn-primary {
    background-color: var(--primary-color);  /* Blue */
    color: var(--white);
}

.btn-primary:hover {
    background-color: var(--secondary-color);  /* Purple on hover */
}
```

**Text:**
```css
h1 {
    color: var(--text-dark);  /* Dark grey */
}

p {
    color: var(--text-light);  /* Light grey */
}
```

**Backgrounds:**
```css
body {
    background-color: var(--background-light);  /* Off-white */
}

.card {
    background-color: var(--white);  /* Pure white */
}
```

---

## Spacing System

### Padding Scale

**Extra Small:** `0.25rem` (4px)
**Small:** `0.5rem` (8px)
**Medium:** `1rem` (16px)
**Large:** `2rem` (32px)
**Extra Large:** `4rem` (64px)

**Usage:**
```css
.card {
    padding: 2rem;  /* 32px all sides */
}

.button {
    padding: 0.6rem 1.2rem;  /* 9.6px top/bottom, 19.2px left/right */
}
```

---

### Margin Scale

Same as padding scale

**Usage:**
```css
h1 {
    margin-bottom: 1rem;  /* 16px */
}

.section {
    margin-bottom: 3rem;  /* 48px */
}
```

---

### Gap (Flexbox/Grid)

**Small Gap:** `1rem` (16px)
**Medium Gap:** `1.5rem` (24px)
**Large Gap:** `2rem` (32px)

**Usage:**
```css
.chapters-grid {
    gap: 1.5rem;  /* 24px between cards */
}

.hero {
    gap: 2rem;  /* 32px between columns */
}
```

---

## Component Library

### Buttons

**Primary Button:**
```html
<button class="btn-primary">Click Me</button>
```
```css
.btn-primary {
    background-color: var(--primary-color);
    color: var(--white);
    padding: 0.6rem 1.2rem;
    border-radius: 30px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.btn-primary:hover {
    background-color: var(--secondary-color);
    transform: translateY(-2px);
}
```

**Outline Button:**
```html
<button class="btn-outline">Learn More</button>
```
```css
.btn-outline {
    background-color: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
    padding: 0.6rem 1.2rem;
    border-radius: 30px;
}

.btn-outline:hover {
    background-color: var(--primary-color);
    color: var(--white);
}
```

---

### Cards

**Chapter Card:**
```html
<div class="chapter-card">
    <div class="chapter-card-icon">
        <i class="fas fa-brain"></i>
    </div>
    <div class="chapter-card-content">
        <h3>Chapter Title</h3>
        <p>Description...</p>
        <a href="#" class="card-link">Start Chapter →</a>
    </div>
</div>
```

**Course Card:**
```html
<div class="course-card">
    <div class="course-icon">
        <i class="fas fa-robot"></i>
    </div>
    <h3>AI Foundations</h3>
    <p>Learn the basics of artificial intelligence</p>
    <button>Explore Course</button>
</div>
```

---

### Badges

**Module Badge:**
```html
<div class="module-badge">Module 1: AI Foundations</div>
```

**Status Badge:**
```html
<span class="badge badge-success">Completed</span>
<span class="badge badge-warning">In Progress</span>
<span class="badge badge-info">New</span>
```

---

### Forms (Future)

```css
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--grey-light);
    border-radius: 8px;
    font-family: 'Poppins', sans-serif;
    transition: var(--transition);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(75, 110, 251, 0.1);
}
```

---

## Utilities

### Display Utilities

```css
.hide-mobile {
    display: block;
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }
}

.show-mobile {
    display: none;
}

@media (max-width: 768px) {
    .show-mobile {
        display: block;
    }
}
```

---

### Text Utilities

```css
.text-center {
    text-align: center;
}

.text-left {
    text-align: left;
}

.text-right {
    text-align: right;
}

.text-bold {
    font-weight: 700;
}

.text-light {
    color: var(--text-light);
}
```

---

### Spacing Utilities

```css
.mb-1 { margin-bottom: 1rem; }
.mb-2 { margin-bottom: 2rem; }
.mb-3 { margin-bottom: 3rem; }

.mt-1 { margin-top: 1rem; }
.mt-2 { margin-top: 2rem; }

.p-1 { padding: 1rem; }
.p-2 { padding: 2rem; }
```

---

## Best Practices

### Naming Conventions

**BEM-like approach:**
```css
/* Block */
.card { }

/* Element */
.card__title { }
.card__content { }

/* Modifier */
.card--featured { }
.card--large { }
```

**Current implementation:**
```css
.chapter-card { }
.chapter-card-icon { }
.chapter-card-content { }
```

---

### CSS Organization

**Order of Properties:**
1. **Positioning** - position, top, left, z-index
2. **Box Model** - display, width, height, margin, padding
3. **Typography** - font, color, text-align
4. **Visual** - background, border, box-shadow
5. **Misc** - cursor, transition, animation

**Example:**
```css
.button {
    /* Positioning */
    position: relative;
    z-index: 1;

    /* Box Model */
    display: inline-block;
    padding: 0.6rem 1.2rem;
    margin: 0.5rem;

    /* Typography */
    font-family: 'Poppins', sans-serif;
    font-weight: 500;
    color: var(--white);
    text-align: center;

    /* Visual */
    background-color: var(--primary-color);
    border-radius: 30px;
    box-shadow: var(--shadow);

    /* Misc */
    cursor: pointer;
    transition: var(--transition);
}
```

---

### Performance Tips

**1. Minimize Specificity:**
```css
/* Bad: Too specific */
header nav ul li a.nav-link { }

/* Good: Lower specificity */
.nav-link { }
```

**2. Avoid Universal Selectors in Loops:**
```css
/* Bad: Slow */
* * * { margin: 0; }

/* Good: Specific */
* { margin: 0; }
```

**3. Use Transform for Animations:**
```css
/* Bad: Triggers layout */
.box:hover {
    margin-top: -5px;
}

/* Good: GPU accelerated */
.box:hover {
    transform: translateY(-5px);
}
```

**4. Avoid @import (Use <link>):**
```html
<!-- Bad: Blocks rendering -->
<style>
@import url('styles.css');
</style>

<!-- Good: Parallel loading -->
<link rel="stylesheet" href="styles.css">
```

---

### Accessibility

**Focus States:**
```css
button:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}
```

**Color Contrast:**
- Text on white: AA compliant (4.5:1 minimum)
- Primary blue (#4B6EFB) on white: ✅ Pass
- Text light (#666666) on white: ✅ Pass

**Reduced Motion:**
```css
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
```

---

### Browser Compatibility

**Tested Browsers:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

**Fallbacks:**
```css
.card {
    background-color: #FFFFFF;  /* Fallback */
    background-color: var(--white);  /* CSS Variable */
}

.grid {
    display: flex;  /* Fallback */
    display: grid;  /* Grid (modern) */
}
```

---

## Related Documents

### Technical Documentation
- [JavaScript API Reference](javascript-api.md) - Frontend code
- [Service Worker Guide](service-worker.md) - PWA styles
- [HTML Structure Guide](html-structure.md) (coming soon) - Markup patterns
- [Current Architecture](../01-Architecture/current-architecture.md) - System overview

### Development Guides
- [Development Setup](../04-Development/setup-guide.md) - Environment setup
- [Coding Standards](../04-Development/coding-standards.md) (coming soon) - Code style

### External Resources
- [MDN: CSS Reference](https://developer.mozilla.org/en-US/docs/Web/CSS)
- [CSS Tricks](https://css-tricks.com/) - Tips and techniques
- [Can I Use](https://caniuse.com/) - Browser compatibility
- [Google Fonts](https://fonts.google.com/) - Typography

---

## Document Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2025-10-27 | 1.0 | Dev Team | Initial CSS system documentation |

---

**END OF DOCUMENT**

*This CSS documentation provides a complete reference for the styling system of the Sci-Bono AI Fluency platform. Use this guide to maintain consistent design and implement new features.*
