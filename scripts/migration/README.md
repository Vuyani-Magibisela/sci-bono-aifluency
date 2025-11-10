# Content Migration Scripts

This directory contains scripts to migrate content from static HTML files to the MySQL database.

## Overview

**Purpose**: Extract educational content from 69 HTML files and import into database for the LMS.

**Process**:
1. Extract chapter content → `lessons.json`
2. Extract quiz data → `quizzes.json` + `quiz_questions.json`
3. Validate all extracted data
4. Import validated data to MySQL database

---

## Prerequisites

Before running these scripts:

1. **PHP 7.4+** installed with extensions:
   - `php-dom`
   - `php-json`
   - `php-pdo`
   - `php-mysql`

2. **MySQL 8.0+** database created:
   ```sql
   CREATE DATABASE ai_fluency_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Database tables created**: Run SQL migration scripts in `/api/migrations/`
   ```bash
   cd ../../api/migrations
   ./run-all-migrations.sh
   ```

4. **Database config**: Create `/api/config/database.php` with PDO connection

---

## Usage

### Step 1: Extract Chapter Content

```bash
php extract-chapters.php
```

**What it does:**
- Scans for all `chapter*.html` files in project root
- Parses HTML using DOMDocument
- Extracts: title, subtitle, module ID, content, navigation
- Outputs: `output/lessons.json`

**Expected output:**
```
=== Chapter Content Extraction ===
Found 44 chapter files

Processing: chapter1.html... ✓ OK (Module 1, Order 100)
Processing: chapter1_11.html... ✓ OK (Module 1, Order 101)
...

Total lessons extracted: 44
```

### Step 2: Extract Quiz Data

```bash
php extract-quizzes.php
```

**What it does:**
- Scans for all `module*Quiz.html` files
- Extracts JavaScript `quizData` arrays
- Converts JavaScript → JSON
- Outputs: `output/quizzes.json` + `output/quiz_questions.json`

**Expected output:**
```
=== Quiz Content Extraction ===
Found 6 quiz files

Processing: module1Quiz.html... ✓ OK (15 questions)
Processing: module2Quiz.html... ✓ OK (12 questions)
...

Total quizzes: 6
Total questions: 87
```

### Step 3: Validate Data

```bash
php validate-content.php
```

**What it does:**
- Validates all JSON files
- Checks required fields present
- Validates foreign key references
- Checks data types and constraints
- Generates validation report

**Expected output:**
```
=== Content Validation ===
Loading JSON files...
  ✓ Loaded lessons.json (44 lessons)
  ✓ Loaded quizzes.json (6 quizzes)
  ✓ Loaded quiz_questions.json (87 questions)

Validating lessons...
  ✓ All 44 lessons validated

✓ VALIDATION PASSED - Safe to import to database
```

**If validation fails**, fix errors before proceeding to import.

### Step 4: Import to Database

```bash
php import-to-db.php
```

**What it does:**
- Loads validated JSON files
- Begins database transaction
- Imports: course → modules → lessons → quizzes → questions
- Commits transaction (or rolls back on error)
- Verifies record counts

**Expected output:**
```
=== Database Import ===
This will import:
  - 44 lessons
  - 6 quizzes
  - 87 quiz questions

Continue? (yes/no): yes

Transaction started...
Importing course... ✓
Importing modules... ✓ (6 modules)
Importing lessons... ✓ (44 lessons)
Importing quizzes... ✓ (6 quizzes)
Importing questions... ✓ (87 questions)

✓ Transaction committed successfully!
✓ Content migration complete!
```

---

## Output Files

All extracted data is saved to `output/` directory:

```
output/
├── lessons.json              # 44 lesson records with full HTML content
├── quizzes.json              # 6 quiz records
├── quiz_questions.json       # 60-120 question records
└── validation-report.txt     # Validation results
```

**These files are intermediary** - review them before database import.

---

## Troubleshooting

### Error: "No chapter files found"

**Cause**: Script run from wrong directory

**Fix**:
```bash
cd /var/www/html/sci-bono-aifluency/scripts/migration
php extract-chapters.php
```

### Error: "Cannot find quizData array"

**Cause**: Quiz HTML file has different JavaScript structure

**Fix**: Check the quiz file for the `const quizData = [...]` array. If named differently, update script regex.

### Error: "JSON decode error"

**Cause**: JavaScript → JSON conversion failed

**Fix**: Manually inspect the JavaScript array in the quiz file. Look for:
- Trailing commas: `[1, 2, 3,]`
- Unquoted keys: `{key: value}`
- Single quotes: `'string'` (should be `"string"`)

### Error: "Database connection failed"

**Cause**: Database config incorrect or database not created

**Fix**:
1. Check `/api/config/database.php` exists
2. Verify database credentials
3. Ensure database created: `CREATE DATABASE ai_fluency_lms;`

### Warning: "Lesson has very large content"

**Cause**: Some lessons have extensive HTML content (normal)

**Action**: No action needed unless content > 1MB (extremely rare)

### Validation Failed

**Cause**: Extracted data has errors

**Action**:
1. Review `output/validation-report.txt`
2. Fix source HTML files if needed
3. Re-run extraction scripts
4. Re-validate

---

## Rollback

If import fails or you need to re-import:

### Option 1: Truncate Tables

```sql
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE quiz_questions;
TRUNCATE TABLE quizzes;
TRUNCATE TABLE lessons;
TRUNCATE TABLE modules;
TRUNCATE TABLE courses;
SET FOREIGN_KEY_CHECKS = 1;
```

Then re-run `import-to-db.php`.

### Option 2: Restore Database Backup

```bash
# Before first import, create backup:
mysqldump -u user -p ai_fluency_lms > backup_before_import.sql

# To restore:
mysql -u user -p ai_fluency_lms < backup_before_import.sql
```

---

## Script Details

### extract-chapters.php

**Input**: `chapter*.html` files (44 files)

**Output**: `output/lessons.json`

**Key Functions**:
- DOMDocument HTML parsing
- XPath queries for structured data extraction
- Module ID extraction from badge text
- Order index computation from chapter numbering
- Full HTML content preservation
- Navigation relationship capture

**Extracted Fields**:
- `module_id` - Extracted from "Module X" badge
- `title` - From `.chapter-header h1`
- `subtitle` - From `.chapter-header .subtitle`
- `slug` - Filename without extension
- `content` - Full `.chapter-content` div HTML
- `sections` - Nav tab structure
- `order_index` - Computed from chapter number
- `previous_slug` / `next_slug` - Navigation links

### extract-quizzes.php

**Input**: `module*Quiz.html` files (6 files)

**Output**: `output/quizzes.json` + `output/quiz_questions.json`

**Key Functions**:
- Regex extraction of JavaScript arrays
- JavaScript → JSON conversion
- Option validation (4 choices per question)
- Answer index validation (0-3 range)

**Extracted Fields**:
- **Quizzes**: `id`, `module_id`, `title`, `passing_score`
- **Questions**: `quiz_id`, `question_text`, `options` (JSON), `correct_option`, `explanation`

### validate-content.php

**Validation Checks**:
1. Required fields present
2. Foreign key references valid
3. Data types correct
4. HTML well-formed
5. JSON valid
6. Unique constraints (slugs, IDs)
7. Answer indices in range

**Exit Codes**:
- `0` - Validation passed
- `1` - Validation failed (errors found)

### import-to-db.php

**Transaction-based**: All-or-nothing import (rollback on error)

**Import Order**:
1. Course (1 record)
2. Modules (6 records)
3. Lessons (44 records)
4. Quizzes (6 records)
5. Quiz Questions (60-120 records)

**Safety Features**:
- Confirmation prompt before import
- Transaction rollback on error
- Record count verification
- Sample data preview

---

## Database Schema Reference

### lessons table

```sql
CREATE TABLE lessons (
    id INT PRIMARY KEY,
    module_id INT,
    title VARCHAR(255),
    subtitle VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    content LONGTEXT,
    order_index INT,
    duration_minutes INT,
    is_published BOOLEAN
);
```

### quizzes table

```sql
CREATE TABLE quizzes (
    id INT PRIMARY KEY,
    module_id INT,
    title VARCHAR(255),
    description TEXT,
    passing_score INT,
    time_limit_minutes INT
);
```

### quiz_questions table

```sql
CREATE TABLE quiz_questions (
    id INT PRIMARY KEY,
    quiz_id INT,
    question_text TEXT,
    options JSON,  -- ["Option A", "Option B", "Option C", "Option D"]
    correct_option INT,  -- 0-based index
    explanation TEXT,
    points INT,
    order_index INT
);
```

---

## Testing

After successful import, verify:

1. **Record counts**:
   ```sql
   SELECT COUNT(*) FROM lessons;    -- Should be 44
   SELECT COUNT(*) FROM quizzes;    -- Should be 6
   SELECT COUNT(*) FROM quiz_questions;  -- Should be 60-120
   ```

2. **Sample content**:
   ```sql
   SELECT title, module_id FROM lessons ORDER BY order_index LIMIT 5;
   SELECT title FROM quizzes;
   ```

3. **Content rendering**:
   - Query a lesson's content HTML
   - Display in browser
   - Verify SVG graphics, formatting intact

---

## Related Documentation

- **Content Migration Guide**: `/Documentation/03-Content-Management/content-migration-guide.md`
- **Database Schema**: `/Documentation/01-Technical/03-Database/schema-design.md`
- **Testing Procedures**: `/Documentation/01-Technical/04-Development/testing-procedures.md`

---

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review validation report: `output/validation-report.txt`
3. Consult full migration guide: `/Documentation/03-Content-Management/content-migration-guide.md`

---

**Last Updated**: 2025-10-28
**Version**: 1.0
