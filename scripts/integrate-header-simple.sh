#!/bin/bash

# Simple Header Template Integration Script
# Uses sed for reliable text replacement

echo "======================================"
echo "Header Template Integration (Simplified)"
echo "======================================"
echo ""

COUNT=0
ERRORS=0

# List of files to update (excluding already done: index.html, login.html, signup.html, chapter1.html, module1.html)
FILES=(
    "chapter2.html" "chapter3.html" "chapter4.html" "chapter5.html"
    "chapter6.html" "chapter7.html" "chapter8.html" "chapter9.html"
    "chapter10.html" "chapter11.html"
    "chapter1_11.html" "chapter1_17.html" "chapter1_24.html" "chapter1_28.html" "chapter1_40.html"
    "chapter2_12.html" "chapter2_18.html" "chapter2_25.html" "chapter2_29.html" "chapter2_41.html"
    "chapter3_13.html" "chapter3_19.html" "chapter3_26.html" "chapter3_30.html" "chapter3_42.html"
    "chapter4_14.html" "chapter4_20.html" "chapter4_27.html" "chapter4_31.html" "chapter4_43.html"
    "chapter5_15.html" "chapter5_21.html" "chapter5_32.html"
    "chapter6_16.html" "chapter6_22.html" "chapter6_33.html"
    "chapter7_23.html" "chapter7_34.html"
    "chapter8_35.html"
    "chapter9_36.html"
    "chapter10_37.html"
    "chapter11_38.html"
    "chapter12_39.html"
    "module2.html" "module3.html" "module4.html" "module5.html" "module6.html"
    "module1Quiz.html" "module2Quiz.html" "module3Quiz.html" "module4Quiz.html" "module5Quiz.html" "module6Quiz.html"
    "offline.html" "courses.html" "projects.html" "project-school-data-detective.html"
    "aifluencystart.html" "present.html"
)

cd /var/www/html/sci-bono-aifluency

for file in "${FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "⚠️  File not found: $file"
        continue
    fi

    # Skip if already has header-placeholder
    if grep -q "header-placeholder" "$file"; then
        echo "✓ $file (already integrated)"
        continue
    fi

    echo "Processing: $file"

    # Create backup
    cp "$file" "$file.backup"

    # Step 1: Add placeholder after <body> tag (match <body> or <body ...>)
    sed -i '/<body[^>]*>/ a\    <!-- Dynamic Header (Phase 1 Integration) -->\n    <div id="header-placeholder"></div>\n\n    <!-- Static Header (Disabled - Keep for rollback)' "$file"

    # Step 2: Comment out closing header tag
    sed -i 's|</header>|</header>\n    -->|' "$file"

    # Step 3: Add auth scripts before </body> if not already there
    if ! grep -q "/js/storage.js" "$file"; then
        sed -i '/<\/body>/ i\    <!-- Authentication System (Phase 1) -->\n    <script src="/js/storage.js"></script>\n    <script src="/js/api.js"></script>\n    <script src="/js/auth.js"></script>\n    <script src="/js/header-template.js"></script>\n\n    <!-- Legacy Scripts -->' "$file"
    fi

    ((COUNT++))
    echo "  ✅ Updated"
done

echo ""
echo "======================================"
echo "✅ Successfully processed: $COUNT files"
echo "======================================"
echo ""
echo "Backups created with .backup extension"
echo "To rollback all: for f in *.backup; do mv \"\$f\" \"\${f%.backup}\"; done"
