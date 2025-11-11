#!/bin/bash

# Script to integrate header template into all HTML files
# Phase 1: Frontend-Backend Integration
# Date: November 11, 2025

echo "======================================"
echo "Header Template Integration Script"
echo "======================================"
echo ""

# Counter for processed files
COUNT=0
ERRORS=0

# Function to update a single HTML file
update_html_file() {
    local file="$1"
    local basename=$(basename "$file")

    echo "Processing: $basename"

    # Check if file already has header-placeholder
    if grep -q "header-placeholder" "$file"; then
        echo "  ⚠️  Already integrated, skipping..."
        return
    fi

    # Create backup
    cp "$file" "$file.backup"

    # Step 1: Replace <body> tag and add header placeholder, comment out static header
    # This is complex, so we'll use a Python script for more reliable replacement
    python3 << 'PYTHON_SCRIPT'
import sys
import re

file_path = sys.argv[1]

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Pattern to find </head><body> and the header section
# Replace opening body and add placeholder, then comment out header
pattern = r'(</head>\s*<body[^>]*>)\s*(<header>.*?</header>)'

def replacer(match):
    head_close_body_open = match.group(1)
    header_content = match.group(2)

    return f'''{head_close_body_open}
    <!-- Dynamic Header (Phase 1 Integration) -->
    <div id="header-placeholder"></div>

    <!-- Static Header (Disabled - Keep for rollback)
    {header_content}
    -->'''

# Use DOTALL flag to match across newlines
content_new = re.sub(pattern, replacer, content, count=1, flags=re.DOTALL)

# Step 2: Add auth scripts before </body> if not already there
if '/js/storage.js' not in content_new:
    # Find </body> and add scripts before it
    body_close_pattern = r'(\s*)(</body>)'

    scripts = '''
    <!-- Authentication System (Phase 1) -->
    <script src="/js/storage.js"></script>
    <script src="/js/api.js"></script>
    <script src="/js/auth.js"></script>
    <script src="/js/header-template.js"></script>

    <!-- Legacy Scripts -->'''

    # Insert scripts before </body>, maintaining indentation
    content_new = re.sub(body_close_pattern, f'{scripts}\\n\\1\\2', content_new, count=1)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content_new)

print("  ✅ Updated successfully")
PYTHON_SCRIPT
$file

    if [ $? -eq 0 ]; then
        ((COUNT++))
    else
        echo "  ❌ Error processing file"
        ((ERRORS++))
        # Restore backup on error
        mv "$file.backup" "$file"
    fi

    echo ""
}

# Get list of HTML files to process
FILES=(
    "/var/www/html/sci-bono-aifluency/chapter2.html"
    "/var/www/html/sci-bono-aifluency/chapter3.html"
    "/var/www/html/sci-bono-aifluency/chapter4.html"
    "/var/www/html/sci-bono-aifluency/chapter5.html"
    "/var/www/html/sci-bono-aifluency/chapter6.html"
    "/var/www/html/sci-bono-aifluency/chapter7.html"
    "/var/www/html/sci-bono-aifluency/chapter8.html"
    "/var/www/html/sci-bono-aifluency/chapter9.html"
    "/var/www/html/sci-bono-aifluency/chapter10.html"
    "/var/www/html/sci-bono-aifluency/chapter11.html"
    "/var/www/html/sci-bono-aifluency/chapter1_11.html"
    "/var/www/html/sci-bono-aifluency/chapter1_17.html"
    "/var/www/html/sci-bono-aifluency/chapter1_24.html"
    "/var/www/html/sci-bono-aifluency/chapter1_28.html"
    "/var/www/html/sci-bono-aifluency/chapter1_40.html"
    "/var/www/html/sci-bono-aifluency/chapter2_12.html"
    "/var/www/html/sci-bono-aifluency/chapter2_18.html"
    "/var/www/html/sci-bono-aifluency/chapter2_25.html"
    "/var/www/html/sci-bono-aifluency/chapter2_29.html"
    "/var/www/html/sci-bono-aifluency/chapter2_41.html"
    "/var/www/html/sci-bono-aifluency/chapter3_13.html"
    "/var/www/html/sci-bono-aifluency/chapter3_19.html"
    "/var/www/html/sci-bono-aifluency/chapter3_26.html"
    "/var/www/html/sci-bono-aifluency/chapter3_30.html"
    "/var/www/html/sci-bono-aifluency/chapter3_42.html"
    "/var/www/html/sci-bono-aifluency/chapter4_14.html"
    "/var/www/html/sci-bono-aifluency/chapter4_20.html"
    "/var/www/html/sci-bono-aifluency/chapter4_27.html"
    "/var/www/html/sci-bono-aifluency/chapter4_31.html"
    "/var/www/html/sci-bono-aifluency/chapter4_43.html"
    "/var/www/html/sci-bono-aifluency/chapter5_15.html"
    "/var/www/html/sci-bono-aifluency/chapter5_21.html"
    "/var/www/html/sci-bono-aifluency/chapter5_32.html"
    "/var/www/html/sci-bono-aifluency/chapter6_16.html"
    "/var/www/html/sci-bono-aifluency/chapter6_22.html"
    "/var/www/html/sci-bono-aifluency/chapter6_33.html"
    "/var/www/html/sci-bono-aifluency/chapter7_23.html"
    "/var/www/html/sci-bono-aifluency/chapter7_34.html"
    "/var/www/html/sci-bono-aifluency/chapter8_35.html"
    "/var/www/html/sci-bono-aifluency/chapter9_36.html"
    "/var/www/html/sci-bono-aifluency/chapter10_37.html"
    "/var/www/html/sci-bono-aifluency/chapter11_38.html"
    "/var/www/html/sci-bono-aifluency/chapter12_39.html"
    "/var/www/html/sci-bono-aifluency/module2.html"
    "/var/www/html/sci-bono-aifluency/module3.html"
    "/var/www/html/sci-bono-aifluency/module4.html"
    "/var/www/html/sci-bono-aifluency/module5.html"
    "/var/www/html/sci-bono-aifluency/module6.html"
    "/var/www/html/sci-bono-aifluency/module1Quiz.html"
    "/var/www/html/sci-bono-aifluency/module2Quiz.html"
    "/var/www/html/sci-bono-aifluency/module3Quiz.html"
    "/var/www/html/sci-bono-aifluency/module4Quiz.html"
    "/var/www/html/sci-bono-aifluency/module5Quiz.html"
    "/var/www/html/sci-bono-aifluency/module6Quiz.html"
    "/var/www/html/sci-bono-aifluency/offline.html"
    "/var/www/html/sci-bono-aifluency/courses.html"
    "/var/www/html/sci-bono-aifluency/projects.html"
    "/var/www/html/sci-bono-aifluency/project-school-data-detective.html"
    "/var/www/html/sci-bono-aifluency/aifluencystart.html"
    "/var/www/html/sci-bono-aifluency/present.html"
)

# Process each file
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        update_html_file "$file"
    else
        echo "File not found: $file"
        ((ERRORS++))
    fi
done

echo "======================================"
echo "Summary:"
echo "  ✅ Successfully processed: $COUNT files"
echo "  ❌ Errors: $ERRORS files"
echo "======================================"

if [ $ERRORS -eq 0 ]; then
    echo "✅ All files integrated successfully!"
    echo ""
    echo "Backups created with .backup extension"
    echo "To rollback: for f in *.backup; do mv \"\$f\" \"\${f%.backup}\"; done"
else
    echo "⚠️  Some files had errors. Check output above."
fi
