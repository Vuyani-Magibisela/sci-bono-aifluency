# Content Migration Guide - Sci-Bono AI Fluency LMS

**Document Version:** 1.0
**Last Updated:** 2025-10-28
**Author:** Development Team
**Status:** Migration Planning

---

## Table of Contents

1. [Introduction](#introduction)
2. [Migration Overview](#migration-overview)
3. [Content Inventory](#content-inventory)
4. [HTML Structure Analysis](#html-structure-analysis)
5. [Database Schema Mapping](#database-schema-mapping)
6. [Migration Strategy](#migration-strategy)
7. [Extraction Scripts](#extraction-scripts)
8. [Data Validation](#data-validation)
9. [Manual Review Checklist](#manual-review-checklist)
10. [Rollback Procedures](#rollback-procedures)
11. [Post-Migration Testing](#post-migration-testing)
12. [Related Documents](#related-documents)

---

## Introduction

### Purpose

This document provides a comprehensive guide for migrating all educational content from the current static HTML files into the MySQL database structure for the Sci-Bono AI Fluency LMS. The migration ensures that all course content, modules, chapters, quizzes, and projects are properly structured in the database while maintaining content integrity.

### Scope

**In Scope:**
- Migration of all 69 HTML files to database records
- Extraction of course/module/lesson content
- Quiz data transformation (JavaScript → database)
- Project descriptions and requirements
- Content metadata (titles, subtitles, navigation structure)
- SVG graphics and embedded media references
- Inter-chapter navigation relationships

**Out of Scope:**
- User-generated content (doesn't exist yet)
- Analytics data (will be generated post-migration)
- Certificate templates (created separately)
- Media file uploads (images already in `/images/`)

### Migration Goals

1. **Zero Data Loss**: All content must be preserved accurately
2. **Structure Preservation**: Maintain hierarchical relationships (course → module → lesson)
3. **Content Integrity**: HTML formatting, SVG graphics, and styling preserved
4. **Reversibility**: Ability to rollback if issues arise
5. **Automation**: Minimize manual effort through scripted extraction
6. **Validation**: Comprehensive checking of migrated data

---

## Migration Overview

### Current State: Static HTML Files

**Total Files:** 69 HTML files
- 1 Landing page (`index.html`)
- 6 Module overview pages (`module1.html` - `module6.html`)
- 6 Quiz pages (`module1Quiz.html` - `module6Quiz.html`)
- 44 Chapter/lesson pages (various naming patterns)
- 7 Dashboard/authentication pages (for future backend)
- 5 Utility pages (offline, projects, etc.)

### Target State: Database Records

**Database Tables:**
- `courses` - AI Fluency Course (1 record)
- `modules` - 6 module records
- `lessons` - ~44 lesson records with full HTML content
- `quizzes` - 6 quiz records (one per module)
- `quiz_questions` - ~60-120 question records
- `projects` - Project-based assignments

### Migration Phases

**Phase 1: Content Extraction** (Week 2, Days 1-2)
- Inventory all HTML files
- Parse HTML structure using PHP DOM parser
- Extract metadata (titles, module badges, subtitles)
- Generate JSON intermediary files

**Phase 2: Data Transformation** (Week 2, Day 3)
- Transform extracted data to database schema format
- Handle quiz JavaScript → structured data
- Resolve navigation relationships (previous/next)
- Validate data integrity

**Phase 3: Database Import** (Week 3, Day 4)
- Execute SQL insertion scripts
- Verify foreign key relationships
- Check data completeness
- Generate migration report

**Phase 4: Validation & Testing** (Week 3, Day 5)
- Compare original HTML vs database rendering
- Test all navigation paths
- Verify quiz functionality
- Manual spot-checking of content

---

## Content Inventory

### HTML File Categorization

#### 1. Course Landing Page (1 file)
```
index.html - Main landing page with course overview
```

#### 2. Module Overview Pages (6 files)
```
module1.html - Module 1: AI Foundations (11 chapters)
module2.html - Module 2: Generative AI
module3.html - Module 3: Advanced Search
module4.html - Module 4: Responsible AI
module5.html - Module 5: Microsoft Copilot
module6.html - Module 6: AI Impact
```

#### 3. Chapter/Lesson Pages (44 files)

**Module 1: AI Foundations** (11 chapters)
```
chapter1.html      - Chapter 1.00: AI History
chapter1_11.html   - Chapter 1.01: [Title from file]
chapter1_17.html   - Chapter 1.02: [Title from file]
chapter1_24.html   - Chapter 1.03: [Title from file]
chapter1_28.html   - Chapter 1.04: [Title from file]
chapter1_40.html   - Chapter 1.05: [Title from file]
chapter2.html      - Chapter 1.06: [AI Concepts]
chapter2_12.html   - Chapter 1.07: [Title from file]
chapter2_18.html   - Chapter 1.08: [Title from file]
chapter2_25.html   - Chapter 1.09: [Title from file]
chapter2_29.html   - Chapter 1.10: [Title from file]
chapter2_41.html   - Chapter 1.11: [Title from file]
```

**Module 2: Generative AI**
```
chapter3.html      - Chapter 2.01: Introduction to Generative AI
chapter3_13.html   - Chapter 2.02: [Title from file]
chapter3_19.html   - Chapter 2.03: [Title from file]
chapter3_26.html   - Chapter 2.04: [Title from file]
chapter3_30.html   - Chapter 2.05: [Title from file]
chapter3_42.html   - Chapter 2.06: [Title from file]
```

**Module 3: Advanced Search**
```
chapter4.html      - Chapter 3.01: [Title from file]
chapter4_14.html   - Chapter 3.02: [Title from file]
chapter4_20.html   - Chapter 3.03: [Title from file]
chapter4_27.html   - Chapter 3.04: [Title from file]
chapter4_31.html   - Chapter 3.05: [Title from file]
chapter4_43.html   - Chapter 3.06: [Title from file]
```

**Module 4: Responsible AI**
```
chapter5.html      - Chapter 4.01: [Title from file]
chapter5_15.html   - Chapter 4.02: [Title from file]
chapter5_21.html   - Chapter 4.03: [Title from file]
chapter5_32.html   - Chapter 4.04: [Title from file]
```

**Module 5: Microsoft Copilot**
```
chapter6.html      - Chapter 5.01: [Title from file]
chapter6_16.html   - Chapter 5.02: [Title from file]
chapter6_22.html   - Chapter 5.03: [Title from file]
chapter6_33.html   - Chapter 5.04: [Title from file]
```

**Module 6: AI Impact**
```
chapter7.html      - Chapter 6.01: [Title from file]
chapter7_23.html   - Chapter 6.02: [Title from file]
chapter7_34.html   - Chapter 6.03: [Title from file]
chapter8.html      - Chapter 6.04: [Title from file]
chapter8_35.html   - Chapter 6.05: [Title from file]
chapter9.html      - Chapter 6.06: [Title from file]
chapter9_36.html   - Chapter 6.07: [Title from file]
chapter10.html     - Chapter 6.08: [Title from file]
chapter10_37.html  - Chapter 6.09: [Title from file]
chapter11.html     - Chapter 6.10: [Title from file]
chapter11_38.html  - Chapter 6.11: [Title from file]
chapter12_39.html  - Chapter 6.12: [Title from file]
```

#### 4. Quiz Pages (6 files)
```
module1Quiz.html - Module 1 Quiz (~10-20 questions)
module2Quiz.html - Module 2 Quiz
module3Quiz.html - Module 3 Quiz
module4Quiz.html - Module 4 Quiz
module5Quiz.html - Module 5 Quiz
module6Quiz.html - Module 6 Quiz
```

#### 5. Project Pages (2 files)
```
projects.html                    - Projects overview
project-school-data-detective.html - Specific project page
```

#### 6. Authentication & Dashboard Pages (7 files)
```
login.html                - Login page (already connected to future backend)
signup.html               - Registration page
forgot-password.html      - Password reset page
student-dashboard.html    - Student interface (placeholder)
instructor-dashboard.html - Instructor interface (placeholder)
admin-dashboard.html      - Admin interface (placeholder)
courses.html              - Course selection page
```

#### 7. Utility Pages (3 files)
```
offline.html         - PWA offline fallback
present.html         - Presentation mode (?)
aifluencystart.html  - Alternative entry point
```

**Total Content Files to Migrate: 57 files**
(Excluding dashboard/auth pages which are frontend-only)

---

## HTML Structure Analysis

### Chapter Page Structure

Each chapter page follows this consistent pattern:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags, PWA setup, analytics -->
    <title>Chapter X: [Title] - AI Fluency</title>
    <!-- CSS and Font Awesome -->
</head>
<body>
    <header>
        <div class="logo">
            <svg><!-- Logo graphic --></svg>
            <h1>AI Fluency</h1>
        </div>
        <div class="header-controls">
            <button id="downloadPdf">Download PDF</button>
        </div>
    </header>

    <main>
        <div class="chapter-container">
            <!-- 1. CHAPTER HEADER -->
            <div class="chapter-header">
                <div class="module-badge">Module X: [Module Name]</div>
                <h1>Chapter X.XX: [Chapter Title]</h1>
                <p class="subtitle">[Chapter Subtitle]</p>
            </div>

            <!-- 2. CHAPTER NAVIGATION (Section Tabs) -->
            <div class="chapter-nav">
                <div class="nav-buttons-container">
                    <a href="#introduction" class="nav-tab active">
                        <i class="fas fa-info-circle"></i> Introduction
                    </a>
                    <a href="#timeline" class="nav-tab">
                        <i class="fas fa-history"></i> Timeline
                    </a>
                    <!-- More section tabs... -->
                </div>
            </div>

            <!-- 3. CHAPTER CONTENT (Multiple Sections) -->
            <div class="chapter-content">
                <section class="content-section" id="introduction">
                    <h2>[Section Title]</h2>
                    <p>[Paragraphs of content]</p>

                    <!-- SVG Graphics -->
                    <div class="image-container">
                        <svg width="400" height="200">
                            <!-- Inline SVG artwork -->
                        </svg>
                        <p class="caption">[Image caption]</p>
                    </div>

                    <!-- Lists, tables, etc. -->
                    <ul>
                        <li><strong>Term:</strong> Definition</li>
                    </ul>
                </section>

                <section class="content-section" id="timeline">
                    <!-- More content sections... -->
                </section>
            </div>

            <!-- 4. BOTTOM NAVIGATION (Previous/Next) -->
            <div class="nav-buttons">
                <a href="[previous-chapter].html" class="nav-button previous">
                    <i class="fas fa-arrow-left"></i> Previous
                </a>
                <a href="[next-chapter].html" class="nav-button next">
                    Next <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2024 Sci-Bono Centre. All rights reserved.</p>
        <!-- Footer links -->
    </footer>

    <script src="js/script.js"></script>
</body>
</html>
```

### Key Data Points to Extract from Chapters

| Data Point | HTML Location | Database Field | Extraction Method |
|------------|---------------|----------------|-------------------|
| **Module Name** | `.module-badge` | `modules.name` | Text content |
| **Module Number** | `.module-badge` | `lessons.module_id` | Regex: "Module (\d+)" |
| **Chapter Title** | `.chapter-header h1` | `lessons.title` | Text content |
| **Chapter Number** | `.chapter-header h1` | `lessons.order` | Regex: "Chapter (\d+\.\d+)" |
| **Subtitle** | `.chapter-header .subtitle` | `lessons.subtitle` | Text content |
| **Section Tabs** | `.nav-tab` elements | `lessons.sections` (JSON) | Array of {id, title, icon} |
| **Content Sections** | `.content-section` | `lessons.content` (HTML) | innerHTML of entire `.chapter-content` |
| **Previous Chapter** | `.nav-button.previous` href | Navigation relationship | Link extraction |
| **Next Chapter** | `.nav-button.next` href | Navigation relationship | Link extraction |
| **Filename** | File path | `lessons.slug` | Basename without extension |

### Module Overview Page Structure

```html
<main>
    <div class="module-container">
        <div class="module-header">
            <h1>Module X: [Module Name]</h1>
            <p class="subtitle">[Module Description]</p>
        </div>

        <div class="module-intro">
            <div class="module-intro-content">
                <p>[Introduction text - description of module]</p>
            </div>
            <div class="module-intro-image">
                <svg><!-- Module illustration --></svg>
            </div>
        </div>

        <div class="chapters-grid">
            <div class="chapter-card">
                <div class="chapter-card-icon">
                    <i class="fas fa-[icon]"></i>
                </div>
                <div class="chapter-card-content">
                    <h3>Chapter X.XX</h3>
                    <h4>[Chapter Title]</h4>
                    <p>[Chapter description]</p>
                    <a href="[chapter-file].html" class="chapter-link">
                        Start Chapter <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <!-- More chapter cards... -->
        </div>
    </div>
</main>
```

### Quiz Page Structure

```html
<main>
    <div class="quiz-container">
        <div class="quiz-header">
            <h1>Module X Quiz</h1>
            <p>Test your knowledge</p>
        </div>

        <div class="quiz-content" id="quizContent">
            <!-- Dynamically generated by JavaScript -->
        </div>

        <div class="results" id="results" style="display: none;">
            <!-- Results shown after submission -->
        </div>
    </div>
</main>

<script>
    // Quiz data structure
    const quizData = [
        {
            id: 1,
            question: "Question text here?",
            options: [
                "Option A",
                "Option B",
                "Option C",
                "Option D"
            ],
            correctAnswer: 1, // 0-based index
            explanation: "Explanation of the correct answer."
        },
        // More questions...
    ];

    // Quiz logic (rendering, scoring, feedback)
</script>
```

### Key Data Points to Extract from Quizzes

| Data Point | Source | Database Field | Extraction Method |
|------------|--------|----------------|-------------------|
| **Module ID** | Filename: `moduleXQuiz.html` | `quizzes.module_id` | Regex |
| **Quiz Title** | `.quiz-header h1` | `quizzes.title` | Text content |
| **Question ID** | `quizData[].id` | `quiz_questions.id` | JSON parse |
| **Question Text** | `quizData[].question` | `quiz_questions.question_text` | JSON parse |
| **Options** | `quizData[].options` | `quiz_questions.options` (JSON) | JSON array |
| **Correct Answer** | `quizData[].correctAnswer` | `quiz_questions.correct_option` | Integer (0-3) |
| **Explanation** | `quizData[].explanation` | `quiz_questions.explanation` | JSON parse |

---

## Database Schema Mapping

### Course Structure in Database

The database schema (defined in `/Documentation/01-Technical/03-Database/schema-design.md`) uses the following tables for content:

#### 1. `courses` Table

```sql
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced'),
    duration_hours INT,
    thumbnail_url VARCHAR(255),
    is_published BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Single Record for AI Fluency:**
```json
{
    "id": 1,
    "title": "AI Fluency",
    "description": "Master artificial intelligence concepts from foundations to advanced applications",
    "difficulty_level": "intermediate",
    "duration_hours": 40,
    "thumbnail_url": "/images/course-thumbnail.png",
    "is_published": true
}
```

#### 2. `modules` Table

```sql
CREATE TABLE modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    order_index INT NOT NULL,
    thumbnail_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
```

**6 Module Records:**

| id | course_id | title | description | order_index |
|----|-----------|-------|-------------|-------------|
| 1 | 1 | AI Foundations | Explore the history and fundamental concepts of AI | 1 |
| 2 | 1 | Generative AI | Understanding generative AI models and applications | 2 |
| 3 | 1 | Advanced Search | Master advanced search techniques with AI | 3 |
| 4 | 1 | Responsible AI | Learn ethical AI practices and responsible development | 4 |
| 5 | 1 | Microsoft Copilot | Leverage Microsoft Copilot for productivity | 5 |
| 6 | 1 | AI Impact | Understand AI's impact on society and industries | 6 |

**Source:** Extract from `module1.html` - `module6.html` (`.module-header h1` and `.module-intro-content p`)

#### 3. `lessons` Table

```sql
CREATE TABLE lessons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255),
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT,
    order_index INT NOT NULL,
    duration_minutes INT,
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);
```

**~44 Lesson Records:**

**Example Lesson Mapping:**
```json
{
    "id": 1,
    "module_id": 1,
    "title": "Chapter 1.00: AI History",
    "subtitle": "A brief history of Artificial Intelligence",
    "slug": "chapter1",
    "content": "<div class=\"chapter-content\">...</div>",
    "order_index": 1,
    "duration_minutes": 15,
    "is_published": true
}
```

**Content Field Format:**
The `content` field stores the full HTML from `.chapter-content` div, including:
- All `<section class="content-section">` elements
- Embedded SVG graphics
- Lists, tables, code blocks
- Image containers with captions

**Navigation Handled Separately:**
- Previous/next relationships computed dynamically using `order_index`
- Section tabs stored in separate JSON or derived from content

#### 4. `quizzes` Table

```sql
CREATE TABLE quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    passing_score INT DEFAULT 70,
    time_limit_minutes INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);
```

**6 Quiz Records (one per module):**

| id | module_id | title | passing_score | time_limit_minutes |
|----|-----------|-------|---------------|--------------------|
| 1 | 1 | Module 1 Quiz | 70 | 30 |
| 2 | 2 | Module 2 Quiz | 70 | 30 |
| 3 | 3 | Module 3 Quiz | 70 | 30 |
| 4 | 4 | Module 4 Quiz | 70 | 30 |
| 5 | 5 | Module 5 Quiz | 70 | 30 |
| 6 | 6 | Module 6 Quiz | 70 | 30 |

#### 5. `quiz_questions` Table

```sql
CREATE TABLE quiz_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    options JSON NOT NULL,
    correct_option INT NOT NULL,
    explanation TEXT,
    points INT DEFAULT 1,
    order_index INT NOT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);
```

**Example Question Record (from module1Quiz.html):**
```json
{
    "id": 1,
    "quiz_id": 1,
    "question_text": "What is artificial intelligence (AI)?",
    "options": [
        "A computer program that can only perform predetermined tasks",
        "The ability of a computer system to learn from past data and errors, enabling it to make increasingly accurate predictions",
        "A robot with human-like characteristics",
        "Software that allows computers to connect to the internet"
    ],
    "correct_option": 1,
    "explanation": "AI is the ability of a computer system to learn from past data and errors, enabling it to make increasingly accurate predictions for future behavior.",
    "points": 1,
    "order_index": 1
}
```

**Source:** Extract JavaScript `quizData` arrays from `module1Quiz.html` - `module6Quiz.html`

---

## Migration Strategy

### Approach: Automated Extraction with Manual Validation

1. **Automated Scripts** (Primary Method)
   - PHP scripts using DOMDocument for HTML parsing
   - Regex for pattern matching (chapter numbers, module IDs)
   - JSON intermediary format for data review before DB import

2. **Manual Review** (Quality Assurance)
   - Spot-check 10-20% of extracted records
   - Verify complex HTML (nested SVG, tables) preserved correctly
   - Confirm navigation relationships accurate
   - Validate quiz answer indices match correctly

3. **Incremental Migration** (Risk Mitigation)
   - Migrate modules one at a time
   - Test each module before proceeding
   - Maintain rollback capability

### Three-Stage Process

#### Stage 1: Extract to JSON (Intermediary Format)

**Purpose:** Generate human-readable JSON files for review before database insertion

**Output Files:**
```
/scripts/migration/output/
├── courses.json          # Course metadata (1 record)
├── modules.json          # 6 module records
├── lessons.json          # ~44 lesson records with full HTML
├── quizzes.json          # 6 quiz records
└── quiz_questions.json   # ~60-120 question records
```

**Benefits:**
- Easy to review in text editor
- Git-trackable changes
- Can edit manually if needed before import
- Serves as backup of extracted data

#### Stage 2: Validate JSON Data

**Validation Checks:**
- All required fields present
- Foreign key references valid (module_id, quiz_id)
- HTML content well-formed (no broken tags)
- Quiz answer indices within range (0-3)
- No duplicate slugs/IDs
- Order indices sequential with no gaps

**Validation Script:** `/scripts/migration/validate-content.php`

#### Stage 3: Import to Database

**Import Process:**
1. Begin transaction
2. Insert courses (1 record)
3. Insert modules (6 records)
4. Insert lessons (44 records) - verify module_id foreign keys
5. Insert quizzes (6 records) - verify module_id foreign keys
6. Insert quiz_questions (60-120 records) - verify quiz_id foreign keys
7. Commit transaction (or rollback on error)

**Import Script:** `/scripts/migration/import-to-db.php`

---

## Extraction Scripts

### Script 1: `extract-chapters.php`

**Purpose:** Parse all chapter HTML files and extract lesson data

**Input:** Chapter HTML files (`chapter*.html`)
**Output:** `lessons.json`

**Algorithm:**
```php
<?php
// Pseudocode for extract-chapters.php

$lessons = [];
$files = glob('chapter*.html');

foreach ($files as $file) {
    $html = file_get_contents($file);
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);

    // Extract module badge to determine module_id
    $moduleBadge = $xpath->query("//div[@class='module-badge']")->item(0);
    $moduleName = $moduleBadge ? $moduleBadge->textContent : '';
    preg_match('/Module (\d+)/', $moduleName, $matches);
    $moduleId = $matches[1] ?? null;

    // Extract title
    $titleNode = $xpath->query("//div[@class='chapter-header']/h1")->item(0);
    $title = $titleNode ? $titleNode->textContent : '';

    // Extract chapter number for order_index
    preg_match('/Chapter (\d+)\.(\d+)/', $title, $chapterMatches);
    $orderIndex = ($chapterMatches[1] * 100) + $chapterMatches[2];

    // Extract subtitle
    $subtitleNode = $xpath->query("//div[@class='chapter-header']/p[@class='subtitle']")->item(0);
    $subtitle = $subtitleNode ? $subtitleNode->textContent : '';

    // Extract full content
    $contentNode = $xpath->query("//div[@class='chapter-content']")->item(0);
    $content = $contentNode ? $dom->saveHTML($contentNode) : '';

    // Extract previous/next navigation
    $prevNode = $xpath->query("//a[@class='nav-button previous']")->item(0);
    $nextNode = $xpath->query("//a[@class='nav-button next']")->item(0);
    $prevLink = $prevNode ? $prevNode->getAttribute('href') : null;
    $nextLink = $nextNode ? $nextNode->getAttribute('href') : null;

    // Create slug from filename
    $slug = pathinfo($file, PATHINFO_FILENAME);

    $lessons[] = [
        'module_id' => $moduleId,
        'title' => trim($title),
        'subtitle' => trim($subtitle),
        'slug' => $slug,
        'content' => $content,
        'order_index' => $orderIndex,
        'duration_minutes' => 15, // Default estimate
        'is_published' => true,
        'previous_slug' => $prevLink ? pathinfo($prevLink, PATHINFO_FILENAME) : null,
        'next_slug' => $nextLink ? pathinfo($nextLink, PATHINFO_FILENAME) : null
    ];
}

// Sort by order_index
usort($lessons, function($a, $b) {
    return $a['order_index'] <=> $b['order_index'];
});

// Save to JSON
file_put_contents('output/lessons.json', json_encode($lessons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Extracted " . count($lessons) . " lessons\n";
?>
```

**Key Features:**
- Uses PHP DOMDocument for robust HTML parsing
- Handles malformed HTML gracefully with error suppression
- Extracts module_id from badge text
- Computes order_index from chapter numbering (e.g., Chapter 1.05 → 105)
- Preserves full HTML content including SVG
- Captures navigation relationships

### Script 2: `extract-quizzes.php`

**Purpose:** Extract quiz data from JavaScript arrays in quiz HTML files

**Input:** Quiz HTML files (`module*Quiz.html`)
**Output:** `quizzes.json` and `quiz_questions.json`

**Algorithm:**
```php
<?php
// Pseudocode for extract-quizzes.php

$quizzes = [];
$questions = [];
$files = glob('module*Quiz.html');

foreach ($files as $file) {
    $html = file_get_contents($file);

    // Extract module number from filename
    preg_match('/module(\d+)Quiz/', $file, $matches);
    $moduleId = $matches[1] ?? null;

    // Parse HTML for quiz title
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $titleNode = $xpath->query("//div[@class='quiz-header']/h1")->item(0);
    $quizTitle = $titleNode ? $titleNode->textContent : "Module $moduleId Quiz";

    // Extract JavaScript quizData array
    preg_match('/const quizData = (\[[\s\S]*?\]);/m', $html, $jsMatches);

    if (isset($jsMatches[1])) {
        // Convert JavaScript array to JSON
        $jsArray = $jsMatches[1];

        // Clean up JavaScript to make it valid JSON
        $jsonString = preg_replace('/(\w+):/','"\1":', $jsArray);  // Quote keys
        $jsonString = preg_replace('/,\s*\]/',']', $jsonString);   // Remove trailing commas
        $jsonString = preg_replace('/,\s*\}/','}', $jsonString);   // Remove trailing commas

        $quizData = json_decode($jsonString, true);

        if ($quizData) {
            // Create quiz record
            $quizId = $moduleId; // Use module ID as quiz ID for simplicity
            $quizzes[] = [
                'id' => $quizId,
                'module_id' => $moduleId,
                'title' => trim($quizTitle),
                'description' => "Test your knowledge of module $moduleId concepts",
                'passing_score' => 70,
                'time_limit_minutes' => 30
            ];

            // Create question records
            foreach ($quizData as $index => $q) {
                $questions[] = [
                    'quiz_id' => $quizId,
                    'question_text' => $q['question'],
                    'options' => json_encode($q['options']),
                    'correct_option' => $q['correctAnswer'],
                    'explanation' => $q['explanation'] ?? '',
                    'points' => 1,
                    'order_index' => $index + 1
                ];
            }
        }
    }
}

// Save to JSON files
file_put_contents('output/quizzes.json', json_encode($quizzes, JSON_PRETTY_PRINT));
file_put_contents('output/quiz_questions.json', json_encode($questions, JSON_PRETTY_PRINT));

echo "Extracted " . count($quizzes) . " quizzes with " . count($questions) . " questions\n";
?>
```

**Challenges & Solutions:**
- **Challenge:** JavaScript arrays not valid JSON
  - **Solution:** Regex transformations to convert JS → JSON
- **Challenge:** Trailing commas in arrays
  - **Solution:** Strip trailing commas before parsing
- **Challenge:** Unquoted object keys
  - **Solution:** Add quotes around keys with regex

### Script 3: `validate-content.php`

**Purpose:** Validate extracted JSON data before database import

**Input:** All JSON files in `/output/`
**Output:** Validation report (console + `validation-report.txt`)

**Checks:**
1. **Required Fields Present**
   - All mandatory database fields have values
   - No null values where NOT NULL constraint exists

2. **Foreign Key Integrity**
   - All `module_id` references exist in `modules.json`
   - All `quiz_id` references exist in `quizzes.json`

3. **Data Type Validation**
   - Integers are numeric
   - JSON fields are valid JSON
   - Dates in correct format

4. **Content Quality**
   - HTML content well-formed (balanced tags)
   - No excessively long fields (> database limits)
   - Quiz answer indices within range (0 to options.length-1)

5. **Uniqueness Constraints**
   - No duplicate lesson slugs
   - No duplicate IDs

6. **Order Index Continuity**
   - No gaps in order sequences
   - No duplicate order indices within same module

**Output Example:**
```
VALIDATION REPORT
=================
Generated: 2025-10-28 14:32:00

COURSES (1 records)
✓ All required fields present
✓ No duplicate IDs

MODULES (6 records)
✓ All required fields present
✓ Valid course_id references
✓ Order indices sequential (1-6)

LESSONS (44 records)
✓ All required fields present
✓ Valid module_id references
⚠ Warning: Lesson 23 has very long content (125KB)
✓ All slugs unique
✓ HTML content well-formed

QUIZZES (6 records)
✓ All required fields present
✓ Valid module_id references

QUIZ_QUESTIONS (87 records)
✓ All required fields present
✓ Valid quiz_id references
✓ Answer indices within range
✓ Options JSON valid
⚠ Warning: Question 42 has no explanation

SUMMARY
-------
Total Records: 144
Errors: 0
Warnings: 2

✓ VALIDATION PASSED - Safe to import to database
```

### Script 4: `import-to-db.php`

**Purpose:** Import validated JSON data into MySQL database

**Input:** All validated JSON files
**Output:** Database records + import log

**Process:**
```php
<?php
// Pseudocode for import-to-db.php

require_once 'config/database.php'; // PDO connection

try {
    $pdo->beginTransaction();

    // 1. Import course
    $courses = json_decode(file_get_contents('output/courses.json'), true);
    $stmt = $pdo->prepare("INSERT INTO courses (title, description, difficulty_level, duration_hours, is_published) VALUES (?, ?, ?, ?, ?)");
    foreach ($courses as $course) {
        $stmt->execute([
            $course['title'],
            $course['description'],
            $course['difficulty_level'],
            $course['duration_hours'],
            $course['is_published']
        ]);
        $courseId = $pdo->lastInsertId();
    }

    // 2. Import modules
    $modules = json_decode(file_get_contents('output/modules.json'), true);
    $stmt = $pdo->prepare("INSERT INTO modules (course_id, title, description, order_index) VALUES (?, ?, ?, ?)");
    foreach ($modules as $module) {
        $stmt->execute([
            $courseId,
            $module['title'],
            $module['description'],
            $module['order_index']
        ]);
    }

    // 3. Import lessons
    $lessons = json_decode(file_get_contents('output/lessons.json'), true);
    $stmt = $pdo->prepare("INSERT INTO lessons (module_id, title, subtitle, slug, content, order_index, duration_minutes, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($lessons as $lesson) {
        $stmt->execute([
            $lesson['module_id'],
            $lesson['title'],
            $lesson['subtitle'],
            $lesson['slug'],
            $lesson['content'],
            $lesson['order_index'],
            $lesson['duration_minutes'],
            $lesson['is_published']
        ]);
    }

    // 4. Import quizzes
    $quizzes = json_decode(file_get_contents('output/quizzes.json'), true);
    $stmt = $pdo->prepare("INSERT INTO quizzes (id, module_id, title, description, passing_score, time_limit_minutes) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($quizzes as $quiz) {
        $stmt->execute([
            $quiz['id'],
            $quiz['module_id'],
            $quiz['title'],
            $quiz['description'],
            $quiz['passing_score'],
            $quiz['time_limit_minutes']
        ]);
    }

    // 5. Import quiz questions
    $questions = json_decode(file_get_contents('output/quiz_questions.json'), true);
    $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, options, correct_option, explanation, points, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($questions as $question) {
        $stmt->execute([
            $question['quiz_id'],
            $question['question_text'],
            $question['options'],
            $question['correct_option'],
            $question['explanation'],
            $question['points'],
            $question['order_index']
        ]);
    }

    $pdo->commit();

    echo "\n✓ Migration completed successfully!\n";
    echo "  - 1 course\n";
    echo "  - " . count($modules) . " modules\n";
    echo "  - " . count($lessons) . " lessons\n";
    echo "  - " . count($quizzes) . " quizzes\n";
    echo "  - " . count($questions) . " quiz questions\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "  All changes rolled back.\n";
}
?>
```

**Safety Features:**
- Transaction-based (all-or-nothing)
- Automatic rollback on error
- Foreign key constraint checking enabled
- Detailed logging of import progress

---

## Data Validation

### Pre-Import Validation Checklist

Before importing to database, verify:

- [ ] **Module Extraction**
  - [ ] All 6 modules extracted from HTML
  - [ ] Module titles match HTML exactly
  - [ ] Module descriptions present
  - [ ] Order indices 1-6 assigned correctly

- [ ] **Lesson Extraction**
  - [ ] All 44 chapter files processed
  - [ ] No lessons missing from extraction
  - [ ] Module assignments correct (cross-reference with HTML badges)
  - [ ] Chapter titles preserved exactly
  - [ ] Subtitles extracted (or null if missing)
  - [ ] Full HTML content captured in `content` field
  - [ ] SVG graphics present in content HTML
  - [ ] Unique slugs for all lessons
  - [ ] Order indices computed correctly from chapter numbers
  - [ ] Previous/next navigation relationships captured

- [ ] **Quiz Extraction**
  - [ ] All 6 quiz files processed
  - [ ] Quiz-to-module mapping correct (1:1 relationship)
  - [ ] Quiz titles extracted from HTML
  - [ ] All questions extracted from JavaScript arrays
  - [ ] Question count matches original (compare JS array length)

- [ ] **Quiz Question Extraction**
  - [ ] All question text extracted completely
  - [ ] All 4 options present for each question
  - [ ] Correct answer index valid (0-3 range)
  - [ ] Explanations extracted (or empty string if missing)
  - [ ] Order indices match original question order
  - [ ] No truncated text (check for encoding issues)

- [ ] **HTML Content Quality**
  - [ ] No broken HTML tags (validate with validator)
  - [ ] SVG graphics render correctly when HTML displayed
  - [ ] CSS classes preserved for styling
  - [ ] Special characters encoded properly (UTF-8)
  - [ ] Image references intact (src attributes)

- [ ] **JSON File Quality**
  - [ ] All JSON files valid (parse without errors)
  - [ ] No syntax errors in generated JSON
  - [ ] Consistent field naming across records
  - [ ] No null values for required fields

### Post-Import Validation Checklist

After importing to database, verify:

- [ ] **Record Counts**
  - [ ] 1 course record in `courses` table
  - [ ] 6 module records in `modules` table
  - [ ] 44 lesson records in `lessons` table
  - [ ] 6 quiz records in `quizzes` table
  - [ ] 60-120 question records in `quiz_questions` table

- [ ] **Foreign Key Integrity**
  - [ ] All modules have valid `course_id` (all = 1)
  - [ ] All lessons have valid `module_id` (1-6)
  - [ ] All quizzes have valid `module_id` (1-6)
  - [ ] All questions have valid `quiz_id` (1-6)

- [ ] **Content Rendering Test**
  - [ ] Sample lesson content renders correctly in browser
  - [ ] SVG graphics display properly
  - [ ] No broken HTML when retrieved from database
  - [ ] Special characters display correctly (no encoding issues)

- [ ] **Navigation Test**
  - [ ] Lessons ordered correctly by `order_index`
  - [ ] Previous/next relationships work (computed from order)
  - [ ] No orphaned lessons (all belong to valid modules)

- [ ] **Quiz Functionality Test**
  - [ ] Quiz questions associated with correct quiz
  - [ ] Answer options parse correctly from JSON
  - [ ] Correct answer index valid for each question
  - [ ] Explanations display properly

---

## Manual Review Checklist

### Spot-Check Sample (20% of Content)

Randomly select and manually review:

**Modules (Review All 6):**
- [ ] Module 1: AI Foundations
- [ ] Module 2: Generative AI
- [ ] Module 3: Advanced Search
- [ ] Module 4: Responsible AI
- [ ] Module 5: Microsoft Copilot
- [ ] Module 6: AI Impact

**Lessons (Review 9 randomly selected):**
- [ ] Chapter ___: _________________
- [ ] Chapter ___: _________________
- [ ] Chapter ___: _________________
- [ ] Chapter ___: _________________
- [ ] Chapter ___: _________________
- [ ] Chapter ___: _________________
- [ ] Chapter ___: _________________
- [ ] Chapter ___: _________________
- [ ] Chapter ___: _________________

For each lesson, verify:
- [ ] Title matches original HTML exactly
- [ ] Subtitle matches (if present)
- [ ] Full content extracted (compare line count vs. original)
- [ ] SVG graphics present and correct
- [ ] Lists, tables, code blocks formatted correctly
- [ ] No truncation or data loss
- [ ] Proper module assignment

**Quizzes (Review 2 quizzes in detail):**
- [ ] Module ___ Quiz: All questions present
- [ ] Module ___ Quiz: All questions present

For each quiz, verify:
- [ ] Question count matches original
- [ ] Sample 5 questions: text identical to original
- [ ] Sample 5 questions: all 4 options present
- [ ] Sample 5 questions: correct answer index correct (test by hand)
- [ ] Explanations present and accurate

### Complex Content Verification

Manually inspect these complex content types:

- [ ] **Timeline Sections** (e.g., AI History chapter)
  - Verify `.timeline-item` structure preserved
  - Check dates, titles, descriptions all present

- [ ] **Large SVG Graphics**
  - Confirm no SVG tags broken
  - Verify SVG renders visually correct
  - Check SVG attributes intact

- [ ] **Tables**
  - Verify table structure (rows/columns) preserved
  - Check cell content not truncated

- [ ] **Code Blocks** (if any)
  - Confirm syntax highlighting markup intact
  - Verify code samples complete

- [ ] **Special Characters**
  - Check apostrophes, quotes render correctly
  - Verify non-ASCII characters (e.g., é, ñ) display properly

---

## Rollback Procedures

### Before Migration: Create Backups

**1. Backup Database (Empty State)**
```bash
mysqldump -u username -p ai_fluency_lms > backup_pre_migration_$(date +%Y%m%d_%H%M%S).sql
```

**2. Backup HTML Files**
```bash
tar -czf html_backup_$(date +%Y%m%d).tar.gz *.html module*.html chapter*.html
```

**3. Backup Extraction Scripts**
```bash
tar -czf scripts_backup_$(date +%Y%m%d).tar.gz scripts/migration/
```

### Rollback Scenario 1: JSON Extraction Issues

**If extraction scripts produce incorrect data:**

1. Fix the script bug
2. Delete generated JSON files
3. Re-run extraction scripts
4. Re-validate with `validate-content.php`
5. No database impact (haven't imported yet)

### Rollback Scenario 2: Database Import Fails

**If import-to-db.php fails mid-transaction:**

1. **Automatic Rollback**: Transaction rolls back automatically
2. Verify database tables empty:
   ```sql
   SELECT COUNT(*) FROM courses;  -- Should be 0
   SELECT COUNT(*) FROM modules;  -- Should be 0
   ```
3. Fix issue in JSON data or script
4. Re-run import

### Rollback Scenario 3: Post-Import Data Issues Discovered

**If problems found after successful import:**

1. **Option A: Restore from backup**
   ```bash
   mysql -u username -p ai_fluency_lms < backup_pre_migration_YYYYMMDD_HHMMSS.sql
   ```

2. **Option B: Delete and re-import**
   ```sql
   -- Disable foreign key checks temporarily
   SET FOREIGN_KEY_CHECKS = 0;

   TRUNCATE TABLE quiz_questions;
   TRUNCATE TABLE quizzes;
   TRUNCATE TABLE lessons;
   TRUNCATE TABLE modules;
   TRUNCATE TABLE courses;

   SET FOREIGN_KEY_CHECKS = 1;
   ```
   Then re-run import script

3. **Option C: Selective fixes**
   - Identify specific incorrect records
   - Delete/update individual records
   - Re-import only affected data

### Emergency Rollback: Revert to Static Site

**If entire migration needs to be abandoned:**

1. Keep database tables (for future retry)
2. Frontend continues serving static HTML files
3. Users experience no disruption (static site still works)
4. Revisit migration strategy and retry later

**No user impact because:**
- Static HTML files never deleted
- Migration happens in parallel
- Frontend switchover only after validation complete

---

## Post-Migration Testing

### Automated Testing

**1. Database Query Tests**

```php
<?php
// Test script: verify-migration.php

// Test 1: Count verification
$coursesCount = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$modulesCount = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$lessonsCount = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();

assert($coursesCount == 1, "Expected 1 course");
assert($modulesCount == 6, "Expected 6 modules");
assert($lessonsCount >= 40 && $lessonsCount <= 50, "Expected ~44 lessons");

// Test 2: Foreign key integrity
$orphanedLessons = $pdo->query("
    SELECT COUNT(*) FROM lessons
    WHERE module_id NOT IN (SELECT id FROM modules)
")->fetchColumn();
assert($orphanedLessons == 0, "Found orphaned lessons");

// Test 3: Content not null
$emptyContent = $pdo->query("
    SELECT COUNT(*) FROM lessons WHERE content IS NULL OR content = ''
")->fetchColumn();
assert($emptyContent == 0, "Found lessons with empty content");

// Test 4: Quiz answer validity
$invalidAnswers = $pdo->query("
    SELECT COUNT(*) FROM quiz_questions
    WHERE correct_option < 0 OR correct_option > 3
")->fetchColumn();
assert($invalidAnswers == 0, "Found invalid quiz answers");

echo "All automated tests passed!\n";
?>
```

**2. Content Comparison Test**

Compare random sample of HTML files vs. database content:
```php
<?php
// Compare chapter1.html vs database record

$html = file_get_contents('chapter1.html');
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Get title from HTML
$htmlTitle = $xpath->query("//div[@class='chapter-header']/h1")->item(0)->textContent;

// Get title from database
$stmt = $pdo->prepare("SELECT title FROM lessons WHERE slug = ?");
$stmt->execute(['chapter1']);
$dbTitle = $stmt->fetchColumn();

assert(trim($htmlTitle) == trim($dbTitle), "Title mismatch for chapter1");

echo "Content comparison test passed!\n";
?>
```

### Manual Testing Checklist

- [ ] **Module Display Test**
  - [ ] Query all modules, verify titles/descriptions correct
  - [ ] Verify order_index produces correct sequence
  - [ ] Check thumbnail URLs (if added)

- [ ] **Lesson Rendering Test**
  - [ ] Select 3 random lessons from database
  - [ ] Render HTML content in browser
  - [ ] Verify layout matches original
  - [ ] Check SVG graphics display
  - [ ] Confirm no broken images/links

- [ ] **Navigation Test**
  - [ ] Query lessons ordered by module_id, order_index
  - [ ] Verify sequence matches original course flow
  - [ ] Compute previous/next based on order
  - [ ] Test navigation logic works

- [ ] **Quiz Functionality Test**
  - [ ] Select module 1 quiz from database
  - [ ] Retrieve all questions with options
  - [ ] Parse options JSON field
  - [ ] Verify correct answer indices valid
  - [ ] Display quiz in test interface
  - [ ] Submit answers and verify scoring logic

- [ ] **Search/Filter Test**
  - [ ] Search lessons by title (partial match)
  - [ ] Filter lessons by module_id
  - [ ] Test full-text search on content field (if enabled)

- [ ] **Performance Test**
  - [ ] Query all lessons (measure time < 500ms)
  - [ ] Fetch single lesson with content (time < 100ms)
  - [ ] Join query: modules + lessons (time < 200ms)
  - [ ] Complex query: course > modules > lessons > quizzes (time < 500ms)

### User Acceptance Testing (UAT)

Simulate real usage:

1. **Student Learning Flow**
   - [ ] View course overview (from `courses` table)
   - [ ] Browse modules (from `modules` table)
   - [ ] Select and view lesson (from `lessons` table)
   - [ ] Read lesson content (render HTML from database)
   - [ ] Take quiz (fetch questions, submit answers)
   - [ ] View quiz results

2. **Instructor View**
   - [ ] View all course content
   - [ ] Browse lessons by module
   - [ ] Preview quiz questions

3. **Admin Content Review**
   - [ ] List all modules with lesson counts
   - [ ] View unpublished content (if any)
   - [ ] Search lessons by keyword

**Success Criteria:**
- All content displays identically to static HTML version
- No broken links or missing content
- Quizzes function correctly
- Performance acceptable (< 2s page loads)

---

## Related Documents

- **[Current Architecture](../01-Technical/01-Architecture/current-architecture.md)** - Static PWA structure
- **[Future Architecture](../01-Technical/01-Architecture/future-architecture.md)** - Full LMS design
- **[Database Schema Design](../01-Technical/03-Database/schema-design.md)** - Complete database structure
- **[Migration Roadmap](../01-Technical/01-Architecture/migration-roadmap.md)** - 20-week migration plan
- **[HTML Structure Reference](../01-Technical/02-Code-Reference/html-structure.md)** - HTML patterns and conventions

---

## Appendix A: Module-to-Lesson Mapping

### Module 1: AI Foundations (11 chapters)

| Order | Slug | Title (from HTML) | Filename |
|-------|------|-------------------|----------|
| 100 | chapter1 | Chapter 1.00: AI History | chapter1.html |
| 101 | chapter1_11 | Chapter 1.01: [Extract from file] | chapter1_11.html |
| 102 | chapter1_17 | Chapter 1.02: [Extract from file] | chapter1_17.html |
| 103 | chapter1_24 | Chapter 1.03: [Extract from file] | chapter1_24.html |
| 104 | chapter1_28 | Chapter 1.04: [Extract from file] | chapter1_28.html |
| 105 | chapter1_40 | Chapter 1.05: [Extract from file] | chapter1_40.html |
| 106 | chapter2 | Chapter 1.06: [Extract from file] | chapter2.html |
| 107 | chapter2_12 | Chapter 1.07: [Extract from file] | chapter2_12.html |
| 108 | chapter2_18 | Chapter 1.08: [Extract from file] | chapter2_18.html |
| 109 | chapter2_25 | Chapter 1.09: [Extract from file] | chapter2_25.html |
| 110 | chapter2_29 | Chapter 1.10: [Extract from file] | chapter2_29.html |

*Note: Actual titles to be extracted during migration*

### Module 2: Generative AI (6 chapters)

| Order | Slug | Title | Filename |
|-------|------|-------|----------|
| 201 | chapter3 | Chapter 2.01: [Extract] | chapter3.html |
| 202 | chapter3_13 | Chapter 2.02: [Extract] | chapter3_13.html |
| 203 | chapter3_19 | Chapter 2.03: [Extract] | chapter3_19.html |
| 204 | chapter3_26 | Chapter 2.04: [Extract] | chapter3_26.html |
| 205 | chapter3_30 | Chapter 2.05: [Extract] | chapter3_30.html |
| 206 | chapter3_42 | Chapter 2.06: [Extract] | chapter3_42.html |

### Module 3: Advanced Search (6 chapters)

| Order | Slug | Title | Filename |
|-------|------|-------|----------|
| 301 | chapter4 | Chapter 3.01: [Extract] | chapter4.html |
| 302 | chapter4_14 | Chapter 3.02: [Extract] | chapter4_14.html |
| 303 | chapter4_20 | Chapter 3.03: [Extract] | chapter4_20.html |
| 304 | chapter4_27 | Chapter 3.04: [Extract] | chapter4_27.html |
| 305 | chapter4_31 | Chapter 3.05: [Extract] | chapter4_31.html |
| 306 | chapter4_43 | Chapter 3.06: [Extract] | chapter4_43.html |

### Module 4: Responsible AI (4 chapters)

| Order | Slug | Title | Filename |
|-------|------|-------|----------|
| 401 | chapter5 | Chapter 4.01: [Extract] | chapter5.html |
| 402 | chapter5_15 | Chapter 4.02: [Extract] | chapter5_15.html |
| 403 | chapter5_21 | Chapter 4.03: [Extract] | chapter5_21.html |
| 404 | chapter5_32 | Chapter 4.04: [Extract] | chapter5_32.html |

### Module 5: Microsoft Copilot (4 chapters)

| Order | Slug | Title | Filename |
|-------|------|-------|----------|
| 501 | chapter6 | Chapter 5.01: [Extract] | chapter6.html |
| 502 | chapter6_16 | Chapter 5.02: [Extract] | chapter6_16.html |
| 503 | chapter6_22 | Chapter 5.03: [Extract] | chapter6_22.html |
| 504 | chapter6_33 | Chapter 5.04: [Extract] | chapter6_33.html |

### Module 6: AI Impact (13 chapters)

| Order | Slug | Title | Filename |
|-------|------|-------|----------|
| 601 | chapter7 | Chapter 6.01: [Extract] | chapter7.html |
| 602 | chapter7_23 | Chapter 6.02: [Extract] | chapter7_23.html |
| 603 | chapter7_34 | Chapter 6.03: [Extract] | chapter7_34.html |
| 604 | chapter8 | Chapter 6.04: [Extract] | chapter8.html |
| 605 | chapter8_35 | Chapter 6.05: [Extract] | chapter8_35.html |
| 606 | chapter9 | Chapter 6.06: [Extract] | chapter9.html |
| 607 | chapter9_36 | Chapter 6.07: [Extract] | chapter9_36.html |
| 608 | chapter10 | Chapter 6.08: [Extract] | chapter10.html |
| 609 | chapter10_37 | Chapter 6.09: [Extract] | chapter10_37.html |
| 610 | chapter11 | Chapter 6.10: [Extract] | chapter11.html |
| 611 | chapter11_38 | Chapter 6.11: [Extract] | chapter11_38.html |
| 612 | chapter12_39 | Chapter 6.12: [Extract] | chapter12_39.html |

**Total: 44 chapters across 6 modules**

---

## Appendix B: Sample Quiz Data Transformation

### Original JavaScript Format (module1Quiz.html)

```javascript
const quizData = [
    {
        id: 1,
        question: "What is artificial intelligence (AI)?",
        options: [
            "A computer program that can only perform predetermined tasks",
            "The ability of a computer system to learn from past data and errors, enabling it to make increasingly accurate predictions",
            "A robot with human-like characteristics",
            "Software that allows computers to connect to the internet"
        ],
        correctAnswer: 1,
        explanation: "AI is the ability of a computer system to learn from past data and errors, enabling it to make increasingly accurate predictions for future behavior."
    }
];
```

### Transformed Database Records

**Quiz Record:**
```sql
INSERT INTO quizzes (id, module_id, title, description, passing_score, time_limit_minutes)
VALUES (1, 1, 'Module 1 Quiz', 'Test your knowledge of AI Foundations', 70, 30);
```

**Quiz Question Record:**
```sql
INSERT INTO quiz_questions (
    quiz_id,
    question_text,
    options,
    correct_option,
    explanation,
    points,
    order_index
) VALUES (
    1,
    'What is artificial intelligence (AI)?',
    '["A computer program that can only perform predetermined tasks","The ability of a computer system to learn from past data and errors, enabling it to make increasingly accurate predictions","A robot with human-like characteristics","Software that allows computers to connect to the internet"]',
    1,
    'AI is the ability of a computer system to learn from past data and errors, enabling it to make increasingly accurate predictions for future behavior.',
    1,
    1
);
```

**Key Transformations:**
1. `quizData` array → individual database records
2. `options` array → JSON string in database
3. `correctAnswer` index preserved (0-based)
4. `explanation` field mapped directly
5. Added `quiz_id` foreign key
6. Added `points` (default 1)
7. Added `order_index` for question sequencing

---

**Document End**

---

**Version History:**
- v1.0 (2025-10-28): Initial comprehensive migration guide created

**Maintained By:** Development Team
**Review Schedule:** Update after migration completion with actual results
**Next Steps:** Execute extraction scripts and validate output
