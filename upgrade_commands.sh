#!/bin/bash
# Live Site Upgrade Script: v28 → v30 via Git
# Run this on your LIVE SERVER (root@173.225.104.67)

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
LIVE_PATH="/home/admin/domains/tasanz.com/public_html/chhs-tc"
BACKUP_DIR="$HOME/backups/$(date +%Y%m%d)_v28_to_v30"
GIT_REPO="https://github.com/asley/Chhs-testing.git"
GIT_BRANCH="main"

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Gibbon Upgrade: v28 → v30 (Git Deploy)${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""

# Get database credentials
read -p "Database name: " DB_NAME
read -p "Database user: " DB_USER
read -sp "Database password: " DB_PASS
echo ""
read -p "Web server user (e.g., apache, www-data, nginx): " WEB_USER
read -p "Web server group (e.g., apache, www-data, nginx): " WEB_GROUP
echo ""

# Confirmation
echo -e "${RED}WARNING: This will upgrade your live site!${NC}"
echo "Live site path: $LIVE_PATH"
echo "Backup location: $BACKUP_DIR"
echo "Git repo: $GIT_REPO"
echo ""
read -p "Continue? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

# ====================
# STEP 1: Backup
# ====================
echo -e "${GREEN}[1/8] Creating backups...${NC}"
mkdir -p "$BACKUP_DIR"

# Backup database
echo "Backing up database..."
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/database_v28.sql"
echo "✓ Database backed up"

# Backup files
echo "Backing up files..."
cd /home/admin/domains/tasanz.com/public_html
tar -czf "$BACKUP_DIR/chhs-tc_v28_files.tar.gz" chhs-tc/
echo "✓ Files backed up"

# Backup critical files
cd "$LIVE_PATH"
cp config.php "$BACKUP_DIR/config.php.backup"
tar -czf "$BACKUP_DIR/uploads.tar.gz" uploads/
echo "✓ Config and uploads backed up"

ls -lh "$BACKUP_DIR"
echo ""

# ====================
# STEP 2: Check Git Status
# ====================
echo -e "${GREEN}[2/8] Checking git status...${NC}"
cd "$LIVE_PATH"

if [ -d ".git" ]; then
    echo "✓ Git repository exists"
    git status

    # Stash any local changes
    if ! git diff-index --quiet HEAD --; then
        echo "Stashing local changes..."
        git stash save "Pre-v30-upgrade-$(date +%Y%m%d)"
    fi

    USE_PULL=true
else
    echo "⚠ Git repository not found - will use fresh clone"
    USE_PULL=false
fi
echo ""

# ====================
# STEP 3: Deploy v30
# ====================
echo -e "${GREEN}[3/8] Deploying v30 code...${NC}"

if [ "$USE_PULL" = true ]; then
    # Git pull method
    echo "Pulling latest code..."
    git fetch origin
    git pull origin "$GIT_BRANCH"
else
    # Fresh clone method
    echo "Creating fresh clone..."
    cd /home/admin/domains/tasanz.com/public_html

    # Rename old directory
    mv chhs-tc chhs-tc_v28_backup_$(date +%Y%m%d)

    # Clone repo
    git clone "$GIT_REPO" chhs-tc
    cd chhs-tc
    git checkout "$GIT_BRANCH"
fi

echo "✓ Code deployed"
echo ""

# ====================
# STEP 4: Restore Config
# ====================
echo -e "${GREEN}[4/8] Restoring config and uploads...${NC}"
cd "$LIVE_PATH"

# Restore config.php
cp "$BACKUP_DIR/config.php.backup" config.php
echo "✓ config.php restored"

# Restore uploads
rm -rf uploads/
tar -xzf "$BACKUP_DIR/uploads.tar.gz"
echo "✓ uploads/ restored"
echo ""

# ====================
# STEP 5: Set Permissions
# ====================
echo -e "${GREEN}[5/8] Setting permissions...${NC}"
cd "$LIVE_PATH"

chmod 644 config.php
chmod -R 755 .
chmod -R 775 uploads/
chown -R "$WEB_USER":"$WEB_GROUP" .

echo "✓ Permissions set"
echo ""

# ====================
# STEP 6: Install Dependencies
# ====================
echo -e "${GREEN}[6/8] Installing dependencies...${NC}"
cd "$LIVE_PATH"

if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader
    echo "✓ Composer dependencies installed"
else
    echo -e "${YELLOW}⚠ Composer not found - skip if already installed${NC}"
fi

# Clear cache
rm -rf uploads/cache/*
echo "✓ Cache cleared"
echo ""

# ====================
# STEP 7: Verify Custom Mods
# ====================
echo -e "${GREEN}[7/8] Verifying custom modifications...${NC}"
cd "$LIVE_PATH"

# Check functions.php
if grep -q "function getMaxUpload" functions.php; then
    echo "✓ functions.php: getMaxUpload() found"
else
    echo -e "${RED}✗ functions.php: getMaxUpload() NOT FOUND${NC}"
fi

# Check BackgroundProcessor.php
if grep -q "isExecDisabled" src/Services/BackgroundProcessor.php; then
    echo "✓ BackgroundProcessor.php: isExecDisabled() found"
else
    echo -e "${RED}✗ BackgroundProcessor.php: isExecDisabled() NOT FOUND${NC}"
fi

# Check exec() namespace fixes
if grep -q '\\exec' src/Services/BackgroundProcessor.php; then
    echo "✓ BackgroundProcessor.php: exec() namespace fixed"
else
    echo -e "${RED}✗ BackgroundProcessor.php: exec() NOT fixed${NC}"
fi

echo ""

# ====================
# STEP 8: Database Upgrade
# ====================
echo -e "${GREEN}[8/8] Database upgrade required...${NC}"
echo ""
echo -e "${YELLOW}MANUAL STEP REQUIRED:${NC}"
echo "1. Open browser: https://www.tasanz.com/chhs-tc/"
echo "2. Gibbon will detect version change and prompt for upgrade"
echo "3. Follow the upgrade wizard"
echo ""
echo "Alternatively, run manually:"
echo "  cd $LIVE_PATH"
echo "  php cli/installer.php"
echo ""

# ====================
# Summary
# ====================
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Backups saved to: $BACKUP_DIR"
echo ""
echo -e "${YELLOW}NEXT STEPS:${NC}"
echo "1. ✓ Run database upgrade (via browser or CLI)"
echo "2. ✓ Test login"
echo "3. ✓ Test User Admin > Add/Edit users"
echo "4. ✓ Test batch email reports"
echo "5. ✓ Test custom modules"
echo "6. ✓ Check error logs"
echo ""
echo -e "${YELLOW}Error logs:${NC}"
echo "  tail -f /home/admin/domains/tasanz.com/logs/error_log"
echo ""
echo -e "${YELLOW}Rollback (if needed):${NC}"
echo "  cd $LIVE_PATH"
echo "  git reset --hard HEAD~10"
echo "  mysql -u $DB_USER -p $DB_NAME < $BACKUP_DIR/database_v28.sql"
echo ""
echo "Documentation: See GIT_UPGRADE_LIVE_SITE.md"
echo ""
