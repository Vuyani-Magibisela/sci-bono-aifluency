#!/bin/bash
# Run All Database Migrations
# Usage: ./run-all-migrations.sh [database_name] [username]

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=== Database Migration Runner ==="
echo ""

# Get database credentials
DB_NAME=${1:-ai_fluency_lms}
DB_USER=${2:-root}

echo "Database: $DB_NAME"
echo "User: $DB_USER"
echo ""

# Prompt for password
read -sp "Enter MySQL password: " DB_PASS
echo ""
echo ""

# Test connection
echo "Testing database connection..."
mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Database connection failed${NC}"
    echo "Please check your credentials or create the database first:"
    echo "  CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    exit 1
fi

echo -e "${GREEN}✓ Database connection successful${NC}"
echo ""

# Confirm before proceeding
echo -e "${YELLOW}WARNING: This will create/modify database tables.${NC}"
read -p "Continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Migration cancelled."
    exit 0
fi

echo ""

# Run migrations in order
MIGRATIONS=(
    "001_create_users_table.sql"
    "002_create_courses_modules_lessons.sql"
    "003_create_quizzes_questions.sql"
    "004_create_enrollments_progress.sql"
    "005_create_certificates_submissions.sql"
)

SUCCESS_COUNT=0
FAIL_COUNT=0

for MIGRATION in "${MIGRATIONS[@]}"; do
    echo "Running migration: $MIGRATION"

    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATION" 2>&1 | grep -v "Using a password"

    if [ ${PIPESTATUS[0]} -eq 0 ]; then
        echo -e "${GREEN}✓ $MIGRATION completed${NC}"
        ((SUCCESS_COUNT++))
    else
        echo -e "${RED}✗ $MIGRATION failed${NC}"
        ((FAIL_COUNT++))
    fi

    echo ""
done

# Summary
echo "=== Migration Summary ==="
echo -e "Successful: ${GREEN}$SUCCESS_COUNT${NC}"
echo -e "Failed: ${RED}$FAIL_COUNT${NC}"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}✓ All migrations completed successfully!${NC}"
    echo ""
    echo "Next steps:"
    echo "  1. Run content extraction scripts: cd ../../scripts/migration && php extract-chapters.php"
    echo "  2. Validate extracted data: php validate-content.php"
    echo "  3. Import to database: php import-to-db.php"
    exit 0
else
    echo -e "${RED}✗ Some migrations failed. Please check the errors above.${NC}"
    exit 1
fi
