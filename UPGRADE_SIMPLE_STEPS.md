# Simple Upgrade Steps - Copy & Paste

**DO THIS ON LIVE SERVER**: ssh root@173.225.104.67

---

## Step 1: Backup (5 minutes)

```bash
# Set variables (EDIT THESE!)
DB_NAME="your_database_name"
DB_USER="your_db_user"
DB_PASS="your_db_password"

# Create backup
mkdir -p ~/backups/$(date +%Y%m%d)_v30_upgrade
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Backup database
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > ~/backups/$(date +%Y%m%d)_v30_upgrade/db.sql

# Backup files
tar -czf ~/backups/$(date +%Y%m%d)_v30_upgrade/files.tar.gz .

# Backup config and uploads specifically
cp config.php ~/backups/$(date +%Y%m%d)_v30_upgrade/
tar -czf ~/backups/$(date +%Y%m%d)_v30_upgrade/uploads.tar.gz uploads/

# Verify backups
ls -lh ~/backups/$(date +%Y%m%d)_v30_upgrade/
```

---

## Step 2: Deploy v30 via Git (2 minutes)

### Option A: If Live Site Has Git Already

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Save config and uploads
cp config.php ~/config_temp.php
tar -czf ~/uploads_temp.tar.gz uploads/

# Pull v30 code
git stash
git fetch origin
git pull origin main

# Restore config and uploads
cp ~/config_temp.php config.php
rm -rf uploads/
tar -xzf ~/uploads_temp.tar.gz
```

### Option B: If No Git (Fresh Clone)

```bash
cd /home/admin/domains/tasanz.com/public_html

# Backup current directory
mv chhs-tc chhs-tc_v28_old

# Clone v30 repo
git clone https://github.com/asley/Chhs-testing.git chhs-tc
cd chhs-tc

# Restore config and uploads
cp ~/backups/$(date +%Y%m%d)_v30_upgrade/config.php .
tar -xzf ~/backups/$(date +%Y%m%d)_v30_upgrade/uploads.tar.gz
```

---

## Step 3: Set Permissions (1 minute)

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Set ownership (CHANGE www-data to your web user!)
chown -R www-data:www-data .

# Set permissions
chmod 644 config.php
chmod -R 755 .
chmod -R 775 uploads/
```

---

## Step 4: Install Dependencies (2 minutes)

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Install composer packages
composer install --no-dev --optimize-autoloader

# Clear cache
rm -rf uploads/cache/*
```

---

## Step 5: Verify Custom Modifications (30 seconds)

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Check functions.php
grep -n "function getMaxUpload" functions.php
# Should show: 795:function getMaxUpload($asString = false)

# Check BackgroundProcessor
grep -n "isExecDisabled" src/Services/BackgroundProcessor.php
# Should show the method

# Check exec fixes
grep -n '\\exec' src/Services/BackgroundProcessor.php
# Should show 4 matches
```

**Expected Output**:
- ✅ functions.php has getMaxUpload() at line 795
- ✅ BackgroundProcessor has isExecDisabled() method
- ✅ BackgroundProcessor has \exec() (with backslash) in 4 places

---

## Step 6: Database Upgrade (10 minutes)

### Via Browser (Easiest):
1. Open: https://www.tasanz.com/chhs-tc/
2. Gibbon will detect version mismatch
3. Follow upgrade wizard

### Via Command Line:
```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc
php cli/installer.php
```

---

## Step 7: Test Everything (15 minutes)

### Critical Tests:
```bash
# Check logs for errors
tail -f /home/admin/domains/tasanz.com/logs/error_log
```

### In Browser:
1. ✅ Login with admin account
2. ✅ Go to User Admin > Manage Users > Edit (a user)
3. ✅ Go to User Admin > Manage Users > Add (new user)
4. ✅ Go to Reports > Send batch email
5. ✅ Test custom modules (Badges, GradeAnalytics)

---

## Rollback (If Needed)

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Rollback git
git log --oneline  # Find commit before upgrade
git reset --hard [COMMIT_HASH]

# Restore database
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < ~/backups/YYYYMMDD_v30_upgrade/db.sql

# Or full restore:
cd /home/admin/domains/tasanz.com/public_html
rm -rf chhs-tc
tar -xzf ~/backups/YYYYMMDD_v30_upgrade/files.tar.gz
mv chhs-tc_restored chhs-tc
```

---

## Quick Reference

**What Changed**:
- ✅ Upgraded Gibbon v28 → v30
- ✅ Added getMaxUpload() to functions.php
- ✅ Fixed BackgroundProcessor exec() errors
- ✅ Fixed User Admin module bugs
- ✅ All your custom modules preserved

**Files Modified** (automatically via git):
1. functions.php (line 795-813)
2. src/Services/BackgroundProcessor.php (5 changes)
3. modules/User Admin/user_manage_edit.php
4. modules/User Admin/user_manage_add.php
5. modules/Reports/src/Sources/Student.php

**Time Estimate**: 30-45 minutes total

---

## Troubleshooting

### Git pull fails with conflicts
```bash
git merge --abort
git pull --rebase origin main
```

### Composer fails
```bash
composer self-update
composer clear-cache
composer install --no-dev -vvv
```

### Database upgrade fails
```bash
# Check database connection
php -r "new PDO('mysql:host=localhost;dbname=$DB_NAME', '$DB_USER', '$DB_PASS');"
```

### Permission errors
```bash
# Reset all permissions
cd /home/admin/domains/tasanz.com/public_html/chhs-tc
chown -R www-data:www-data .
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 uploads/
```

---

**Created**: 2025-12-16
**For**: Quick git-based v30 upgrade

🤖 Generated with [Claude Code](https://claude.com/claude-code)
