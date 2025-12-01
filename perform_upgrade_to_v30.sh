#!/bin/bash

#######################################################
# Gibbon v28 → v30 Upgrade Execution Script
# Created: 2025-11-30
# Purpose: Perform the actual upgrade to v30
#######################################################

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
GIBBON_DIR="/Applications/MAMP/htdocs/chhs-testing"
V30_SOURCE="$HOME/Downloads/core-30.0.00"
BACKUP_DIR="$HOME/gibbon_backups/$(date +%Y%m%d_%H%M%S)_before_v30_upgrade"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Gibbon v28 → v30 Upgrade${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

# Verify backup exists
echo -e "${YELLOW}[1/8] Verifying recent backup...${NC}"
LATEST_BACKUP=$(ls -td ~/gibbon_backups/*/ 2>/dev/null | head -1)
if [ -z "$LATEST_BACKUP" ]; then
    echo -e "${RED}✗ No backup found!${NC}"
    echo -e "${RED}Please run ./backup_before_upgrade.sh first${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Found backup: $LATEST_BACKUP${NC}"
echo ""

# Verify v30 source exists
echo -e "${YELLOW}[2/8] Verifying Gibbon v30 source...${NC}"
if [ ! -d "$V30_SOURCE" ]; then
    echo -e "${RED}✗ Gibbon v30 source not found at: $V30_SOURCE${NC}"
    echo -e "${RED}Please download and extract v30 first${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Gibbon v30 source found${NC}"
echo ""

# Create pre-upgrade snapshot
echo -e "${YELLOW}[3/8] Creating pre-upgrade snapshot...${NC}"
mkdir -p "$BACKUP_DIR"
cp "$GIBBON_DIR/config.php" "$BACKUP_DIR/config.php.snapshot"
cp "$GIBBON_DIR/.htaccess" "$BACKUP_DIR/.htaccess.snapshot" 2>/dev/null || true
echo -e "${GREEN}✓ Pre-upgrade snapshot created${NC}"
echo ""

# Save list of custom modules
echo -e "${YELLOW}[4/8] Identifying custom modules...${NC}"
CUSTOM_MODULES=()
if [ -d "$GIBBON_DIR/modules" ]; then
    for module in "$GIBBON_DIR/modules"/*; do
        if [ -d "$module" ]; then
            MODULE_NAME=$(basename "$module")
            # Skip core modules
            case $MODULE_NAME in
                "Activities"|"Admissions"|"Assessments"|"Attendance"|"Behaviour"|"Data Admin"|"Finance"|"Free Learning"|"Individual Needs"|"Library"|"Markbook"|"Messenger"|"Planner"|"Reports"|"Rubrics"|"School Admin"|"Staff"|"Students"|"System Admin"|"Timetable"|"User Admin")
                    # Core module, skip
                    ;;
                *)
                    CUSTOM_MODULES+=("$MODULE_NAME")
                    echo -e "  Found custom: $MODULE_NAME"
                    ;;
            esac
        fi
    done
fi
echo -e "${GREEN}✓ Found ${#CUSTOM_MODULES[@]} custom modules${NC}"
echo ""

# Backup custom modules
echo -e "${YELLOW}[5/8] Backing up custom modules...${NC}"
mkdir -p "$BACKUP_DIR/custom_modules"
for module in "${CUSTOM_MODULES[@]}"; do
    if [ -d "$GIBBON_DIR/modules/$module" ]; then
        cp -r "$GIBBON_DIR/modules/$module" "$BACKUP_DIR/custom_modules/"
        echo -e "  Backed up: $module"
    fi
done
echo -e "${GREEN}✓ Custom modules backed up${NC}"
echo ""

# Copy v30 files
echo -e "${YELLOW}[6/8] Copying Gibbon v30 files...${NC}"
echo -e "  This will overwrite existing files..."
echo -e "  ${YELLOW}Note: config.php and uploads/ are protected${NC}"

# Remove old core files (but keep config.php, uploads/, custom modules)
cd "$GIBBON_DIR"
find . -maxdepth 1 -type f ! -name 'config.php' ! -name '.htaccess' ! -name '*.sh' ! -name '*.md' -exec rm {} \; 2>/dev/null || true

# Remove old core directories (but keep uploads/, modules/, and a few others)
for dir in src themes resources cli i18n; do
    if [ -d "$dir" ]; then
        rm -rf "$dir"
    fi
done

# Copy new v30 files
echo -e "  Copying new files from v30..."
cp -r "$V30_SOURCE"/* "$GIBBON_DIR/"

# Restore config.php
echo -e "  Restoring config.php..."
cp "$BACKUP_DIR/config.php.snapshot" "$GIBBON_DIR/config.php"

# Restore .htaccess if it was backed up
if [ -f "$BACKUP_DIR/.htaccess.snapshot" ]; then
    echo -e "  Restoring .htaccess..."
    cp "$BACKUP_DIR/.htaccess.snapshot" "$GIBBON_DIR/.htaccess"
fi

# Restore custom modules
echo -e "  Restoring custom modules..."
for module in "${CUSTOM_MODULES[@]}"; do
    if [ -d "$BACKUP_DIR/custom_modules/$module" ]; then
        cp -r "$BACKUP_DIR/custom_modules/$module" "$GIBBON_DIR/modules/"
        echo -e "    Restored: $module"
    fi
done

echo -e "${GREEN}✓ Gibbon v30 files copied${NC}"
echo ""

# Set permissions
echo -e "${YELLOW}[7/8] Setting permissions...${NC}"
cd "$GIBBON_DIR"
chmod -R 755 .
chmod -R 775 uploads 2>/dev/null || true
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Run Composer install
echo -e "${YELLOW}[8/8] Installing dependencies...${NC}"
if command -v composer &> /dev/null; then
    cd "$GIBBON_DIR"
    composer install --no-dev --optimize-autoloader
    echo -e "${GREEN}✓ Dependencies installed${NC}"
else
    echo -e "${YELLOW}⚠ Composer not found, skipping dependency installation${NC}"
    echo -e "${YELLOW}  You may need to run: composer install --no-dev${NC}"
fi
echo ""

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}File Upgrade Complete!${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo -e "${GREEN}✓ Gibbon v30 files installed${NC}"
echo -e "${GREEN}✓ config.php preserved${NC}"
echo -e "${GREEN}✓ uploads/ preserved${NC}"
echo -e "${GREEN}✓ Custom modules preserved (${#CUSTOM_MODULES[@]} modules)${NC}"
echo ""
echo -e "${BLUE}======================================${NC}"
echo -e "${YELLOW}NEXT CRITICAL STEP:${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo -e "${RED}You MUST now run the database upgrade!${NC}"
echo ""
echo -e "Option 1: ${GREEN}Web-based upgrade (Recommended)${NC}"
echo -e "  1. Open: ${BLUE}http://localhost:8888/chhs-testing${NC}"
echo -e "  2. Gibbon will detect version mismatch"
echo -e "  3. Follow the upgrade wizard"
echo ""
echo -e "Option 2: ${GREEN}Command-line upgrade${NC}"
echo -e "  cd $GIBBON_DIR"
echo -e "  php cli/installer.php"
echo ""
echo -e "${YELLOW}Custom Modules to Test After Upgrade:${NC}"
for module in "${CUSTOM_MODULES[@]}"; do
    echo -e "  - $module"
done
echo ""
echo -e "${BLUE}======================================${NC}"
echo -e "${YELLOW}Important Reminders:${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo -e "1. ${YELLOW}DO NOT close this terminal until upgrade is verified${NC}"
echo -e "2. ${YELLOW}Backup is at: $BACKUP_DIR${NC}"
echo -e "3. ${YELLOW}If upgrade fails, you can restore from backup${NC}"
echo -e "4. ${YELLOW}Test all custom modules after database upgrade${NC}"
echo ""
echo -e "${BLUE}======================================${NC}"
echo -e "${GREEN}Ready for database upgrade!${NC}"
echo -e "${BLUE}======================================${NC}"
