#!/bin/bash
# Database Setup Script for Sci-Bono AI Fluency LMS
# This script will create the database and user

set -e  # Exit on error

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "=== Sci-Bono AI Fluency LMS - Database Setup ==="
echo ""

# Database configuration
DB_NAME="ai_fluency_lms"
DB_USER="ai_fluency_user"

echo "This script will create:"
echo "  - Database: $DB_NAME"
echo "  - User: $DB_USER"
echo ""

# Prompt for MySQL root password
echo -e "${YELLOW}Please enter your MySQL root password:${NC}"
read -s MYSQL_ROOT_PASSWORD
echo ""

# Test MySQL connection
echo "Testing MySQL connection..."
if ! mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${RED}✗ MySQL connection failed. Please check your root password.${NC}"
    echo ""
    echo "If you haven't set a root password yet, run:"
    echo "  sudo mysql -e \"ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'YourNewPassword';\""
    exit 1
fi

echo -e "${GREEN}✓ MySQL connection successful${NC}"
echo ""

# Generate strong password for database user
DB_PASSWORD=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-20)

echo "Creating database and user..."

# Create database and user
mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<SQL
-- Create database
CREATE DATABASE IF NOT EXISTS $DB_NAME
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';

-- Grant privileges
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Show databases
SHOW DATABASES LIKE '${DB_NAME}';
SQL

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database and user created successfully!${NC}"
    echo ""

    # Create .env file
    ENV_FILE="../api/.env"

    echo "Creating .env file at: $ENV_FILE"

    cat > "$ENV_FILE" <<ENV
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD

# JWT Configuration
JWT_SECRET=$(openssl rand -base64 48)
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

# Application Configuration
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/sci-bono-aifluency

# Email Configuration (for future use)
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=

# Analytics
GOOGLE_ANALYTICS_ID=G-VNN90D4GDE
GOOGLE_ADS_ID=ca-pub-6423925713865339
ENV

    chmod 600 "$ENV_FILE"

    echo -e "${GREEN}✓ .env file created${NC}"
    echo ""

    echo "=== Setup Complete! ==="
    echo ""
    echo "Database Credentials:"
    echo "  Host: localhost"
    echo "  Database: $DB_NAME"
    echo "  User: $DB_USER"
    echo "  Password: $DB_PASSWORD"
    echo ""
    echo -e "${YELLOW}IMPORTANT: Credentials saved to $ENV_FILE${NC}"
    echo "Keep this file secure and never commit to Git!"
    echo ""
    echo "Next steps:"
    echo "  1. Run database migrations: cd ../api/migrations && ./run-all-migrations.sh"
    echo "  2. Run content migration: cd ../scripts/migration && php extract-chapters.php"
    echo ""
else
    echo -e "${RED}✗ Database setup failed${NC}"
    exit 1
fi
