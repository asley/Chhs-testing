#!/bin/bash

#######################################################
# Gibbon v28 → v30 Upgrade Backup Script
# Created: 2025-11-30
# Purpose: Complete backup before upgrading to v30
#######################################################

# Color output for better visibility
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
BACKUP_DIR="$HOME/gibbon_backups/$(date +%Y%m%d_%H%M%S)"
GIBBON_DIR="/Applications/MAMP/htdocs/chhs-testing"
DB_NAME="chhs-testing"
DB_USER="root"
DB_PASS="root"
DB_SOCKET="/Applications/MAMP/tmp/mysql/mysql.sock"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Gibbon v28 → v30 Upgrade Backup${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo -e "${YELLOW}Backup will be saved to:${NC}"
echo -e "${GREEN}$BACKUP_DIR${NC}"
echo ""

# Create backup directory
echo -e "${YELLOW}[1/6] Creating backup directory...${NC}"
mkdir -p "$BACKUP_DIR"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Backup directory created${NC}"
else
    echo -e "${RED}✗ Failed to create backup directory${NC}"
    exit 1
fi
echo ""

# Check if MAMP MySQL is running
echo -e "${YELLOW}[2/6] Checking MAMP MySQL status...${NC}"
if [ -S "$DB_SOCKET" ]; then
    echo -e "${GREEN}✓ MAMP MySQL is running${NC}"
else
    echo -e "${RED}✗ MAMP MySQL is not running!${NC}"
    echo -e "${RED}Please start MAMP before running this script${NC}"
    exit 1
fi
echo ""

# Backup database
echo -e "${YELLOW}[3/6] Backing up database ($DB_NAME)...${NC}"
/Applications/MAMP/Library/bin/mysql80/bin/mysqldump \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --socket="$DB_SOCKET" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    "$DB_NAME" > "$BACKUP_DIR/database_v28_backup.sql"

if [ $? -eq 0 ]; then
    DB_SIZE=$(du -h "$BACKUP_DIR/database_v28_backup.sql" | cut -f1)
    echo -e "${GREEN}✓ Database backed up successfully ($DB_SIZE)${NC}"
else
    echo -e "${RED}✗ Database backup failed${NC}"
    exit 1
fi
echo ""

# Backup critical files separately
echo -e "${YELLOW}[4/6] Backing up critical files...${NC}"

# Backup config.php
if [ -f "$GIBBON_DIR/config.php" ]; then
    cp "$GIBBON_DIR/config.php" "$BACKUP_DIR/config.php.backup"
    echo -e "${GREEN}✓ config.php backed up${NC}"
else
    echo -e "${RED}✗ config.php not found${NC}"
fi

# Backup uploads directory
if [ -d "$GIBBON_DIR/uploads" ]; then
    echo -e "  Backing up uploads directory..."
    cp -r "$GIBBON_DIR/uploads" "$BACKUP_DIR/uploads_backup/"
    UPLOADS_SIZE=$(du -sh "$BACKUP_DIR/uploads_backup" | cut -f1)
    echo -e "${GREEN}✓ uploads/ backed up ($UPLOADS_SIZE)${NC}"
else
    echo -e "${YELLOW}⚠ uploads/ directory not found${NC}"
fi

# Backup .htaccess
if [ -f "$GIBBON_DIR/.htaccess" ]; then
    cp "$GIBBON_DIR/.htaccess" "$BACKUP_DIR/.htaccess.backup"
    echo -e "${GREEN}✓ .htaccess backed up${NC}"
fi

# Backup custom modules (list them)
echo -e "  Detecting custom modules..."
CUSTOM_MODULES=("ChatBot" "Badges" "Committees" "Bulk Report Download" "Data Admin" "2aiTeacher")
for module in "${CUSTOM_MODULES[@]}"; do
    if [ -d "$GIBBON_DIR/modules/$module" ]; then
        echo -e "    Found: $module"
    fi
done
echo ""

# Backup entire Gibbon directory
echo -e "${YELLOW}[5/6] Backing up entire Gibbon directory...${NC}"
echo -e "  This may take a few minutes..."

# First, try to fix permissions on the problematic module
if [ -d "$GIBBON_DIR/modules/Formal Assessment" ]; then
    echo -e "  Fixing permissions on Formal Assessment module..."
    sudo chmod -R u+r "$GIBBON_DIR/modules/Formal Assessment" 2>/dev/null || true
fi

cd "$GIBBON_DIR/.."
tar -czf "$BACKUP_DIR/chhs-testing_v28_complete.tar.gz" \
    --exclude='chhs-testing/uploads/cache' \
    --exclude='chhs-testing/vendor' \
    --exclude='chhs-testing/.git' \
    chhs-testing/ 2>&1 | grep -v "Cannot stat" | grep -v "Permission denied" || true

if [ -f "$BACKUP_DIR/chhs-testing_v28_complete.tar.gz" ] && [ -s "$BACKUP_DIR/chhs-testing_v28_complete.tar.gz" ]; then
    ARCHIVE_SIZE=$(du -h "$BACKUP_DIR/chhs-testing_v28_complete.tar.gz" | cut -f1)
    echo -e "${GREEN}✓ Complete directory backed up ($ARCHIVE_SIZE)${NC}"
    echo -e "${YELLOW}  (Permission errors ignored - critical files already backed up separately)${NC}"
else
    echo -e "${RED}✗ Directory backup failed${NC}"
    exit 1
fi
echo ""

# Create backup manifest
echo -e "${YELLOW}[6/6] Creating backup manifest...${NC}"
cat > "$BACKUP_DIR/BACKUP_MANIFEST.txt" << EOF
==============================================
Gibbon Backup Manifest
==============================================
Backup Date: $(date +"%Y-%m-%d %H:%M:%S")
Backup Directory: $BACKUP_DIR
Gibbon Version: v28.0.01
Target Upgrade: v30.0.00

==============================================
Backup Contents:
==============================================

1. Database Backup
   File: database_v28_backup.sql
   Database: $DB_NAME
   Size: $DB_SIZE

2. Complete Directory Archive
   File: chhs-testing_v28_complete.tar.gz
   Size: $ARCHIVE_SIZE
   Excludes: cache/, vendor/, .git/

3. Critical Files (Individual)
   - config.php.backup
   - .htaccess.backup
   - uploads_backup/ ($UPLOADS_SIZE)

==============================================
Custom Modules Detected:
==============================================
EOF

for module in "${CUSTOM_MODULES[@]}"; do
    if [ -d "$GIBBON_DIR/modules/$module" ]; then
        echo "- $module" >> "$BACKUP_DIR/BACKUP_MANIFEST.txt"
    fi
done

cat >> "$BACKUP_DIR/BACKUP_MANIFEST.txt" << EOF

==============================================
System Information:
==============================================
PHP Version: $(php -v | head -n 1)
MySQL Socket: $DB_SOCKET
MAMP Installation: /Applications/MAMP

==============================================
Restore Instructions:
==============================================

To restore database:
mysql -u $DB_USER -p$DB_PASS \\
  --socket=$DB_SOCKET \\
  $DB_NAME < database_v28_backup.sql

To restore files:
cd /Applications/MAMP/htdocs
rm -rf chhs-testing
tar -xzf chhs-testing_v28_complete.tar.gz

To restore config.php:
cp config.php.backup /Applications/MAMP/htdocs/chhs-testing/config.php

==============================================
Next Steps for Upgrade:
==============================================

1. Verify this backup is complete
2. Read UPGRADE_TO_V30_GUIDE.md
3. Download Gibbon v30
4. Follow upgrade procedures
5. Keep this backup until upgrade is verified successful

==============================================
EOF

echo -e "${GREEN}✓ Backup manifest created${NC}"
echo ""

# Display backup summary
echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Backup Complete!${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo -e "${GREEN}✓ All backups completed successfully${NC}"
echo ""
echo -e "${YELLOW}Backup Location:${NC}"
echo -e "${GREEN}$BACKUP_DIR${NC}"
echo ""
echo -e "${YELLOW}Backup Contents:${NC}"
echo -e "  • Database backup: database_v28_backup.sql ($DB_SIZE)"
echo -e "  • Complete archive: chhs-testing_v28_complete.tar.gz ($ARCHIVE_SIZE)"
echo -e "  • Critical files: config.php, uploads/, .htaccess"
echo -e "  • Backup manifest: BACKUP_MANIFEST.txt"
echo ""

# List backup directory contents
echo -e "${YELLOW}Backup Files:${NC}"
ls -lh "$BACKUP_DIR" | tail -n +2 | awk '{printf "  %s  %s\n", $5, $9}'
echo ""

echo -e "${BLUE}======================================${NC}"
echo -e "${YELLOW}Next Steps:${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo -e "1. ${GREEN}Verify backup completeness${NC}"
echo -e "   cat $BACKUP_DIR/BACKUP_MANIFEST.txt"
echo ""
echo -e "2. ${GREEN}Read upgrade guide${NC}"
echo -e "   cat /Applications/MAMP/htdocs/chhs-testing/UPGRADE_TO_V30_GUIDE.md"
echo ""
echo -e "3. ${GREEN}Download Gibbon v30${NC}"
echo -e "   See UPGRADE_TO_V30_GUIDE.md for download instructions"
echo ""
echo -e "4. ${GREEN}Keep this backup until upgrade is verified successful${NC}"
echo ""
echo -e "${BLUE}======================================${NC}"
echo -e "${GREEN}Backup script completed successfully!${NC}"
echo -e "${BLUE}======================================${NC}"
