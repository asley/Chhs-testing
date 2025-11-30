#!/bin/bash

# Gibbon Development Environment Verification Script
# This script verifies that all development tools are properly installed

echo "=========================================="
echo "Gibbon Development Environment Verification"
echo "=========================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check function
check_command() {
    if command -v $1 &> /dev/null; then
        echo -e "${GREEN}✓${NC} $2 is installed"
        $3
    else
        echo -e "${RED}✗${NC} $2 is NOT installed"
        return 1
    fi
}

# Check version function
check_version() {
    echo -e "  ${YELLOW}Version:${NC} $1"
}

echo "Checking Core Requirements..."
echo "----------------------------"
check_command "php" "PHP" "php --version | head -n 1 | awk '{print \$2}' | xargs -I {} echo '  Version: {}'"
check_command "composer" "Composer" "composer --version | head -n 1 | awk '{print \$3}' | xargs -I {} echo '  Version: {}'"
check_command "node" "Node.js" "node --version | xargs -I {} echo '  Version: {}'"
check_command "npm" "npm" "npm --version | xargs -I {} echo '  Version: {}'"
check_command "git" "Git" "git --version | awk '{print \$3}' | xargs -I {} echo '  Version: {}'"

echo ""
echo "Checking Development Tools..."
echo "----------------------------"

# Check for Composer binaries
if [ -f "vendor/bin/phpunit" ]; then
    echo -e "${GREEN}✓${NC} PHPUnit is installed"
    ./vendor/bin/phpunit --version | head -n 1 | sed 's/PHPUnit /  Version: /'
else
    echo -e "${RED}✗${NC} PHPUnit is NOT installed"
fi

if [ -f "vendor/bin/codecept" ]; then
    echo -e "${GREEN}✓${NC} Codeception is installed"
    ./vendor/bin/codecept --version | head -n 1 | awk '{print "  Version:", $2}'
else
    echo -e "${RED}✗${NC} Codeception is NOT installed"
fi

if [ -f "vendor/bin/phpstan" ]; then
    echo -e "${GREEN}✓${NC} PHPStan is installed"
    ./vendor/bin/phpstan --version | awk '{print "  Version:", $2}'
else
    echo -e "${RED}✗${NC} PHPStan is NOT installed"
fi

if [ -f "vendor/bin/phpcs" ]; then
    echo -e "${GREEN}✓${NC} PHP_CodeSniffer is installed"
    ./vendor/bin/phpcs --version | awk '{print "  Version:", $2}'
else
    echo -e "${RED}✗${NC} PHP_CodeSniffer is NOT installed"
fi

echo ""
echo "Checking PHP Configuration..."
echo "----------------------------"

# Check PHP settings
MAX_FILE_UPLOADS=$(php -r "echo ini_get('max_file_uploads');")
MAX_INPUT_VARS=$(php -r "echo ini_get('max_input_vars');")
ERROR_REPORTING=$(php -r "echo ini_get('error_reporting');")
ALLOW_URL_FOPEN=$(php -r "echo ini_get('allow_url_fopen');")

if [ "$MAX_FILE_UPLOADS" -ge 60 ]; then
    echo -e "${GREEN}✓${NC} max_file_uploads = $MAX_FILE_UPLOADS (>= 60)"
else
    echo -e "${YELLOW}!${NC} max_file_uploads = $MAX_FILE_UPLOADS (recommended: >= 60)"
fi

if [ "$MAX_INPUT_VARS" -ge 5000 ]; then
    echo -e "${GREEN}✓${NC} max_input_vars = $MAX_INPUT_VARS (>= 5000)"
else
    echo -e "${YELLOW}!${NC} max_input_vars = $MAX_INPUT_VARS (recommended: >= 5000)"
fi

echo -e "  error_reporting = $ERROR_REPORTING"

if [ "$ALLOW_URL_FOPEN" == "1" ]; then
    echo -e "${GREEN}✓${NC} allow_url_fopen = On"
else
    echo -e "${RED}✗${NC} allow_url_fopen = Off (should be On)"
fi

echo ""
echo "Checking File Permissions..."
echo "----------------------------"

# Check uploads directory permissions
if [ -d "uploads" ]; then
    UPLOADS_PERMS=$(stat -f %Lp uploads 2>/dev/null || stat -c %a uploads 2>/dev/null)
    if [ "$UPLOADS_PERMS" == "775" ] || [ "$UPLOADS_PERMS" == "777" ]; then
        echo -e "${GREEN}✓${NC} uploads/ directory has correct permissions ($UPLOADS_PERMS)"
    else
        echo -e "${YELLOW}!${NC} uploads/ directory permissions: $UPLOADS_PERMS (recommended: 775)"
    fi
else
    echo -e "${RED}✗${NC} uploads/ directory not found"
fi

echo ""
echo "Checking Git Configuration..."
echo "----------------------------"

# Check Git setup
if [ -d ".git" ]; then
    echo -e "${GREEN}✓${NC} Git repository initialized"

    REMOTE_URL=$(git remote get-url origin 2>/dev/null)
    if [ ! -z "$REMOTE_URL" ]; then
        echo -e "${GREEN}✓${NC} Remote repository configured"
        echo -e "  URL: $REMOTE_URL"

        BRANCH=$(git rev-parse --abbrev-ref HEAD)
        echo -e "  Current branch: $BRANCH"

        COMMITS=$(git rev-list --count HEAD)
        echo -e "  Total commits: $COMMITS"
    else
        echo -e "${RED}✗${NC} No remote repository configured"
    fi
else
    echo -e "${RED}✗${NC} Not a Git repository"
fi

echo ""
echo "Checking Documentation..."
echo "----------------------------"

if [ -f "DEVELOPMENT.md" ]; then
    echo -e "${GREEN}✓${NC} DEVELOPMENT.md exists"
else
    echo -e "${RED}✗${NC} DEVELOPMENT.md not found"
fi

if [ -f "SETUP_COMPLETE.md" ]; then
    echo -e "${GREEN}✓${NC} SETUP_COMPLETE.md exists"
else
    echo -e "${RED}✗${NC} SETUP_COMPLETE.md not found"
fi

if [ -f ".gitignore" ]; then
    echo -e "${GREEN}✓${NC} .gitignore configured"
else
    echo -e "${RED}✗${NC} .gitignore not found"
fi

echo ""
echo "Checking Gibbon Files..."
echo "----------------------------"

if [ -f "config.php" ]; then
    echo -e "${GREEN}✓${NC} config.php exists"

    # Check if it's excluded from Git
    if git check-ignore config.php &> /dev/null; then
        echo -e "${GREEN}✓${NC} config.php is excluded from Git"
    else
        echo -e "${YELLOW}!${NC} config.php is NOT excluded from Git (security risk!)"
    fi
else
    echo -e "${YELLOW}!${NC} config.php not found (may need to be created)"
fi

if [ -f "index.php" ]; then
    echo -e "${GREEN}✓${NC} index.php exists"
else
    echo -e "${RED}✗${NC} index.php not found"
fi

if [ -d "modules" ]; then
    MODULE_COUNT=$(ls -1 modules | wc -l | xargs)
    echo -e "${GREEN}✓${NC} modules/ directory exists ($MODULE_COUNT modules)"
else
    echo -e "${RED}✗${NC} modules/ directory not found"
fi

echo ""
echo "=========================================="
echo "Verification Complete!"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Review DEVELOPMENT.md for development guidelines"
echo "2. Review SETUP_COMPLETE.md for environment details"
echo "3. Start developing modules or contributing to core"
echo ""
echo "For help, visit: https://docs.gibbonedu.org"
echo ""
