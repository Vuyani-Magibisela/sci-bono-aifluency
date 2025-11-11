#!/bin/bash

# Comprehensive Backend Testing Suite
# Tests all controllers and generates a summary report

echo "================================================================================"
echo " SCI-BONO AI FLUENCY BACKEND API TEST SUITE"
echo "================================================================================"
echo ""

# Initialize counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Test results file
RESULTS_FILE="test_results_summary.txt"
> "$RESULTS_FILE"

echo "Test Suite Started: $(date)" >> "$RESULTS_FILE"
echo "================================================================================" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test function
run_test() {
    local test_file=$1
    local test_name=$2

    echo -e "${YELLOW}Running: ${test_name}${NC}"

    if php "$test_file" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PASSED${NC}: $test_name"
        echo "✓ PASSED: $test_name" >> "$RESULTS_FILE"
        ((PASSED_TESTS++))
    else
        echo -e "${RED}✗ FAILED${NC}: $test_name"
        echo "✗ FAILED: $test_name" >> "$RESULTS_FILE"
        ((FAILED_TESTS++))
    fi

    ((TOTAL_TESTS++))
}

# Individual controller tests
echo "--- Testing Core Functionality ---"
echo ""

# Test routing system
echo -e "${YELLOW}Testing routing system...${NC}"
php test_routes.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: Routing system"
    echo "✓ PASSED: Routing system" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: Routing system"
    echo "✗ FAILED: Routing system" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Test auth endpoints
echo -e "${YELLOW}Testing authentication endpoints...${NC}"
php test_register_admin.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: User registration"
    echo "✓ PASSED: User registration" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: User registration"
    echo "✗ FAILED: User registration" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

php test_login.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: User login"
    echo "✓ PASSED: User login" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: User login"
    echo "✗ FAILED: User login" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

php test_refresh.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: Token refresh"
    echo "✓ PASSED: Token refresh" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: Token refresh"
    echo "✗ FAILED: Token refresh" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

php test_me.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: Get current user"
    echo "✓ PASSED: Get current user" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: Get current user"
    echo "✗ FAILED: Get current user" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

php test_logout.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: User logout"
    echo "✓ PASSED: User logout" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: User logout"
    echo "✗ FAILED: User logout" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Test user endpoints
echo -e "${YELLOW}Testing user management endpoints...${NC}"
php test_user_list.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: List users"
    echo "✓ PASSED: List users" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: List users"
    echo "✗ FAILED: List users" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

php test_user_show.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: Show user"
    echo "✓ PASSED: Show user" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: Show user"
    echo "✗ FAILED: Show user" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

php test_user_update.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: Update user"
    echo "✓ PASSED: Update user" >> "$RESULTS_FILE"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ FAILED${NC}: Update user"
    echo "✗ FAILED: Update user" >> "$RESULTS_FILE"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Summary
echo ""
echo "================================================================================"
echo " TEST SUMMARY"
echo "================================================================================"
echo -e "Total Tests:  $TOTAL_TESTS"
echo -e "Passed:       ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed:       ${RED}$FAILED_TESTS${NC}"

if [ $TOTAL_TESTS -gt 0 ]; then
    SUCCESS_RATE=$(echo "scale=2; ($PASSED_TESTS / $TOTAL_TESTS) * 100" | bc)
    echo -e "Success Rate: ${SUCCESS_RATE}%"
fi
echo "================================================================================"

# Write summary to file
echo "" >> "$RESULTS_FILE"
echo "================================================================================" >> "$RESULTS_FILE"
echo "TEST SUMMARY" >> "$RESULTS_FILE"
echo "================================================================================" >> "$RESULTS_FILE"
echo "Total Tests:  $TOTAL_TESTS" >> "$RESULTS_FILE"
echo "Passed:       $PASSED_TESTS" >> "$RESULTS_FILE"
echo "Failed:       $FAILED_TESTS" >> "$RESULTS_FILE"
if [ $TOTAL_TESTS -gt 0 ]; then
    echo "Success Rate: ${SUCCESS_RATE}%" >> "$RESULTS_FILE"
fi
echo "================================================================================" >> "$RESULTS_FILE"
echo "Test Suite Completed: $(date)" >> "$RESULTS_FILE"

echo ""
echo "Detailed results saved to: $RESULTS_FILE"
