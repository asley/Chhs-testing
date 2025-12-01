# Gibbon Upgrade Guide: v28.0.01 → v30.0.00

**Current Version**: v28.0.01
**Target Version**: v30.0.00
**Date**: 2025-11-30

---

## ⚠️ CRITICAL PRE-UPGRADE CHECKLIST

Before starting the upgrade, you MUST:

- [ ] **Backup your database** (CRITICAL!)
- [ ] **Backup all files** (entire Gibbon directory)
- [ ] **Test on local MAMP first** (never upgrade production directly)
- [ ] **Check PHP version** (v30 requires PHP 8.1+)
- [ ] **Read Gibbon v30 release notes**
- [ ] **Notify users** (if upgrading production)

---

## System Requirements Comparison

### Current (v28)
```
PHP: 7.4.0+
MySQL: 5.7+
```

### Required (v30)
```
PHP: 8.1.0+ (MAJOR CHANGE!)
MySQL: 5.7+ or MariaDB 10.3+
```

### Your Current System
```
PHP: 8.4.6 ✅ (Compatible!)
MySQL: via MAMP ✅
```

---

## Upgrade Path

Since you're jumping from v28 → v30, you'll go through:
1. v28.0.01 (current)
2. v29.0.00 (intermediate upgrade)
3. v30.0.00 (target)

**Option 1: Direct Upgrade** (Recommended for v28 → v30)
- Download v30 and let Gibbon handle database migrations

**Option 2: Sequential Upgrade** (Safer but longer)
- Upgrade v28 → v29 → v30

---

## Step-by-Step Upgrade Process

### Phase 1: Backup Everything

#### 1.1 Backup Database

```bash
# Create backup directory
mkdir -p ~/gibbon_backups/$(date +%Y%m%d)

# Backup database via MAMP MySQL
mysqldump -u root -proot \
  --host=localhost \
  --port=8889 \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  chhs-testing > ~/gibbon_backups/$(date +%Y%m%d)/chhs-testing_v28_backup.sql

# Verify backup file exists
ls -lh ~/gibbon_backups/$(date +%Y%m%d)/
```

#### 1.2 Backup Files

```bash
# Backup entire Gibbon directory
cd /Applications/MAMP/htdocs
tar -czf ~/gibbon_backups/$(date +%Y%m%d)/chhs-testing_v28_files.tar.gz chhs-testing/

# Verify backup
ls -lh ~/gibbon_backups/$(date +%Y%m%d)/
```

#### 1.3 Backup Important Files Separately

```bash
# Backup critical files
cp /Applications/MAMP/htdocs/chhs-testing/config.php \
   ~/gibbon_backups/$(date +%Y%m%d)/config.php.backup

# Backup uploads
cp -r /Applications/MAMP/htdocs/chhs-testing/uploads \
   ~/gibbon_backups/$(date +%Y%m%d)/uploads_backup/
```

---

### Phase 2: Download Gibbon v30

#### 2.1 Download from Official Source

```bash
# Download v30 release
cd ~/Downloads
wget https://github.com/GibbonEdu/core/archive/refs/tags/v30.0.00.zip

# Or download manually from:
# https://github.com/GibbonEdu/core/releases/tag/v30.0.00
```

#### 2.2 Extract Download

```bash
# Extract the archive
unzip v30.0.00.zip

# This creates: core-30.0.00/
```

---

### Phase 3: Prepare for Upgrade

#### 3.1 Read Release Notes

**IMPORTANT**: Check for breaking changes:
- https://github.com/GibbonEdu/core/releases/tag/v30.0.00
- https://github.com/GibbonEdu/core/releases/tag/v29.0.00

#### 3.2 Check Module Compatibility

Your custom modules need to be checked:
- ChatBot module
- Badges module
- Committees module
- Bulk Report Download module
- Data Admin module
- 2aiTeacher module

**Action**: Contact module developers or test in staging environment.

---

### Phase 4: Perform Upgrade

#### 4.1 Put Site in Maintenance Mode

```php
// Create a simple maintenance page
// Create: /Applications/MAMP/htdocs/chhs-testing/maintenance.php
```

#### 4.2 Copy v30 Files (Option A: Manual)

```bash
# Navigate to your Gibbon installation
cd /Applications/MAMP/htdocs/chhs-testing

# Remove old core files (but keep config.php, uploads/, and custom modules!)
# DO NOT delete these:
# - config.php
# - uploads/
# - modules/ (custom modules)

# Copy new v30 files
cp -r ~/Downloads/core-30.0.00/* /Applications/MAMP/htdocs/chhs-testing/

# Restore your config.php (if overwritten)
cp ~/gibbon_backups/$(date +%Y%m%d)/config.php.backup config.php
```

#### 4.3 Set Correct Permissions

```bash
cd /Applications/MAMP/htdocs/chhs-testing

# Set file permissions
chmod -R 755 .

# Set upload folder permissions
chmod -R 775 uploads
```

#### 4.4 Run Composer Install

```bash
cd /Applications/MAMP/htdocs/chhs-testing
composer install --no-dev
```

---

### Phase 5: Database Upgrade

#### 5.1 Access the Installer

1. Open browser: http://localhost:8888/chhs-testing
2. Gibbon will detect version mismatch
3. Follow on-screen upgrade instructions

#### 5.2 Alternative: Manual Database Upgrade

```bash
# Navigate to Gibbon directory
cd /Applications/MAMP/htdocs/chhs-testing

# Run database upgrade script
php cli/installer.php
```

---

### Phase 6: Post-Upgrade Tasks

#### 6.1 Clear Cache

```bash
# Clear all cache files
rm -rf /Applications/MAMP/htdocs/chhs-testing/uploads/cache/*
```

#### 6.2 Check System Settings

1. Log into Gibbon
2. Go to: **System Admin > System Settings**
3. Verify:
   - absoluteURL: `http://localhost:8888/chhs-testing`
   - absolutePath: `/Applications/MAMP/htdocs/chhs-testing`
   - PHP version: 8.4.6
   - All extensions loaded

#### 6.3 Test Critical Functions

- [ ] User login/logout
- [ ] Student enrollment
- [ ] Attendance tracking
- [ ] Grade entry
- [ ] Report generation
- [ ] Custom modules functionality

#### 6.4 Update Modules

1. Go to: **System Admin > Manage Modules**
2. Update any modules that have new versions
3. Reinstall or update custom modules if needed

---

## Troubleshooting Common Issues

### Issue 1: Database Upgrade Fails

**Symptoms**: Error messages during upgrade

**Solution**:
```bash
# Check database connection
php -r "new PDO('mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;dbname=chhs-testing', 'root', 'root');"

# Check CHANGEDB.php for errors
tail -f /Applications/MAMP/htdocs/chhs-testing/error_log
```

### Issue 2: White Screen After Upgrade

**Symptoms**: Blank page after upgrade

**Solution**:
```bash
# Enable PHP error display
# Edit config.php, temporarily add:
ini_set('display_errors', 1);
error_reporting(E_ALL);

# Check PHP error log
tail -f /Applications/MAMP/logs/php_error.log
```

### Issue 3: Modules Not Working

**Symptoms**: Custom modules broken

**Solution**:
1. Uninstall broken module
2. Check module compatibility with v30
3. Update module to v30-compatible version
4. Reinstall module

### Issue 4: Permission Errors

**Symptoms**: Cannot upload files or write data

**Solution**:
```bash
# Fix permissions
chmod -R 755 /Applications/MAMP/htdocs/chhs-testing
chmod -R 775 /Applications/MAMP/htdocs/chhs-testing/uploads
```

---

## Rollback Procedure (If Upgrade Fails)

### Emergency Rollback Steps

```bash
# 1. Stop MAMP

# 2. Restore database
mysql -u root -proot \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  chhs-testing < ~/gibbon_backups/YYYYMMDD/chhs-testing_v28_backup.sql

# 3. Restore files
cd /Applications/MAMP/htdocs
rm -rf chhs-testing
tar -xzf ~/gibbon_backups/YYYYMMDD/chhs-testing_v28_files.tar.gz

# 4. Restart MAMP

# 5. Verify rollback
# Visit: http://localhost:8888/chhs-testing
# Should show v28.0.01
```

---

## Upgrade Checklist

### Pre-Upgrade
- [ ] Database backed up
- [ ] Files backed up
- [ ] config.php backed up
- [ ] uploads/ backed up
- [ ] Release notes read
- [ ] System requirements checked
- [ ] Users notified (if production)

### During Upgrade
- [ ] v30 downloaded
- [ ] Files extracted
- [ ] Old files replaced with v30 files
- [ ] config.php restored
- [ ] Permissions set correctly
- [ ] Composer install run
- [ ] Database upgrade completed

### Post-Upgrade
- [ ] Cache cleared
- [ ] System settings verified
- [ ] Login tested
- [ ] Core functions tested
- [ ] Modules tested
- [ ] Custom modules updated
- [ ] Users notified of completion

---

## Alternative: Clean Install Approach

If upgrade proves difficult, consider:

### Option: Fresh v30 Install + Data Migration

1. Install fresh Gibbon v30
2. Export data from v28:
   - Students
   - Staff
   - Courses
   - Grades
3. Import data to v30
4. Reconfigure settings

**Pros**: Clean, no upgrade issues
**Cons**: More work, may lose some data

---

## Production Deployment After Testing

### Once Local Upgrade Succeeds

**DO NOT** directly upgrade production!

1. **Test thoroughly on local MAMP**
2. **Document any issues found**
3. **Fix custom modules if needed**
4. **Create production backup plan**
5. **Schedule maintenance window**
6. **Repeat upgrade steps on production**
7. **Have rollback plan ready**

### Production Upgrade Commands

```bash
# On production server
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Backup
mysqldump -u produser -p proddb > backup_v28_$(date +%Y%m%d).sql
tar -czf backup_v28_files_$(date +%Y%m%d).tar.gz .

# Download v30
wget https://github.com/GibbonEdu/core/archive/refs/tags/v30.0.00.zip
unzip v30.0.00.zip

# Copy files (preserve config.php, uploads/, custom modules)
# ... (same as local upgrade)

# Run database upgrade
# Visit: https://www.tasanz.com/chhs-tc
# Follow upgrade wizard

# Test thoroughly!
```

---

## Important Notes

### About Your Custom Modules

Your installation has several custom modules:
- **ChatBot** - Needs v30 compatibility check
- **Badges** - Needs v30 compatibility check
- **Committees** - Needs v30 compatibility check
- **Bulk Report Download** - Needs v30 compatibility check
- **Data Admin** - Needs v30 compatibility check

**Action Required**: Test each module after upgrade!

### About Database

- Database structure will change during upgrade
- CHANGEDB.php handles all database migrations
- Always backup before upgrading!

### About PHP Version

- v30 requires PHP 8.1+
- You have PHP 8.4.6 ✅
- No PHP upgrade needed!

---

## Support Resources

- **Official Docs**: https://docs.gibbonedu.org
- **Upgrade Guide**: https://docs.gibbonedu.org/administrators/getting-started/updating-gibbon/
- **Forums**: https://ask.gibbonedu.org
- **GitHub Issues**: https://github.com/GibbonEdu/core/issues
- **Support Email**: support@gibbonedu.org

---

## Estimated Timeline

- **Backup**: 15-30 minutes
- **Download v30**: 5-10 minutes
- **File replacement**: 15-30 minutes
- **Database upgrade**: 10-30 minutes (depends on data size)
- **Testing**: 1-2 hours
- **Module updates**: 30-60 minutes

**Total**: 3-5 hours (local testing)

---

## Next Steps

1. **Read this entire guide**
2. **Backup everything** (cannot stress this enough!)
3. **Download Gibbon v30**
4. **Follow step-by-step upgrade process**
5. **Test thoroughly**
6. **Document any issues**
7. **Only then consider production upgrade**

---

**Created**: 2025-11-30
**Current Version**: v28.0.01
**Target Version**: v30.0.00
**Environment**: MAMP (Local Development)

**⚠️ REMEMBER: ALWAYS TEST ON LOCAL FIRST! NEVER UPGRADE PRODUCTION DIRECTLY!**
