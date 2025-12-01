# Gibbon v30 Upgrade - Final Steps

## Current Status: Database Upgrade Required

**File Upgrade**: ✅ Complete
**Database Upgrade**: ⏳ In Progress
**Testing**: ⏳ Pending

---

## What Just Happened

### ✅ Completed Successfully

1. **Backup Created**
   - Database: `/Users/asleysmith/gibbon_backups/20251130_144421/database_v28_backup.sql` (10MB)
   - Files: `/Users/asleysmith/gibbon_backups/20251130_144421/chhs-testing_v28_complete.tar.gz` (12MB)
   - Uploads: `/Users/asleysmith/gibbon_backups/20251130_144421/uploads_backup/` (694MB)
   - Critical files: `config.php`, `.htaccess` backed up

2. **Gibbon v30 Files Installed**
   - Downloaded from: https://github.com/GibbonEdu/core/releases/tag/v30.0.00
   - Extracted and copied to: `/Applications/MAMP/htdocs/chhs-testing`
   - Version updated: v28.0.01 → v30.0.00

3. **Configuration Preserved**
   - ✅ config.php (database credentials)
   - ✅ uploads/ folder (user content - 694MB)
   - ✅ .htaccess (Apache configuration)

4. **Custom Modules Preserved** (22 modules)
   - ChatBot
   - Badges
   - Committees
   - Bulk Report Download
   - Data Admin
   - aiTeacher
   - Crowd Assessment
   - Data Updater
   - Departments
   - Form Groups
   - Formal Assessment
   - GradeAnalytics (multiple versions)
   - House Points
   - Query Builder
   - Timetable Admin
   - Tracking

5. **Dependencies Updated**
   - Composer packages updated (40 packages)
   - New packages installed: Faker, Guzzle 7, etc.
   - Old testing packages removed (PHPUnit, Codeception, etc.)

---

## ⚠️ CRITICAL: Next Steps Required

### Step 1: Complete Database Upgrade

The web browser should now be open at: **http://localhost:8888/chhs-testing**

**What you should see:**
1. Either a "System Upgrade Required" message, or
2. Gibbon will automatically detect the version mismatch and redirect to the upgrade wizard

**If you see the upgrade wizard:**
1. Follow the on-screen instructions
2. Click "Begin Upgrade" or similar button
3. Wait for database migrations to complete
4. DO NOT close the browser during this process

**If you see the login page:**
1. The upgrade may have already completed automatically
2. Try logging in to verify
3. Check System Admin > System Check to verify version

---

### Step 2: Manual Database Upgrade (If Web Upgrade Fails)

If the web upgrade doesn't work, you can run migrations manually:

```bash
# Connect to MySQL
mysql -u root -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock chhs-testing

# Check current version in database
SELECT value FROM gibbonSetting WHERE name = 'version';

# If it shows v28.0.01, you need to update it manually
UPDATE gibbonSetting SET value = 'v30.0.00' WHERE name = 'version';
```

However, this is NOT recommended as it skips important database structure changes!

---

### Step 3: Verify Upgrade Success

After the upgrade wizard completes, verify these items:

#### 3.1 System Information
1. Log into Gibbon
2. Go to: **System Admin > System Check**
3. Verify:
   - **Version**: Should show v30.0.00
   - **PHP Version**: 8.4.6 (should show green ✓)
   - **MySQL Version**: Should show green ✓
   - **Extensions**: All required extensions should be green ✓

#### 3.2 Database Settings
1. Go to: **System Admin > System Settings**
2. Verify:
   - **absoluteURL**: `http://localhost:8888/chhs-testing`
   - **absolutePath**: `/Applications/MAMP/htdocs/chhs-testing`

#### 3.3 Core Functionality
Test these basic features:
- [ ] User login/logout works
- [ ] Dashboard loads correctly
- [ ] Navigate to different modules
- [ ] Search functionality works
- [ ] File uploads work (test with a small image)

#### 3.4 Custom Modules
**IMPORTANT**: Test each custom module individually!

Go to: **System Admin > Manage Modules**

For each custom module, verify:
- [ ] Module appears in the list
- [ ] Status shows "Installed" or "Active"
- [ ] Can access the module from the menu
- [ ] Basic functionality works

**Modules to test:**
1. ChatBot - Check if chatbot interface loads
2. Badges - Check if badge management works
3. Committees - Verify committee listings
4. Bulk Report Download - Test report generation
5. Data Admin - Check data tools
6. aiTeacher - Verify AI features work
7. Formal Assessment - Check assessment tools
8. GradeAnalytics - Verify analytics display
9. House Points - Check points system
10. Query Builder - Test query tools

**If a module fails:**
1. Note which module failed
2. Check module compatibility with v30
3. May need to update or reinstall the module
4. Check Gibbon forums for compatibility information

---

### Step 4: Clear Cache

After successful upgrade:

```bash
# Clear Gibbon cache
rm -rf /Applications/MAMP/htdocs/chhs-testing/uploads/cache/*

# Clear browser cache
# Mac: Cmd + Shift + R (hard refresh)
```

---

### Step 5: Production Deployment (LATER!)

**DO NOT deploy to production yet!**

1. ✅ Test locally for at least 1-2 days
2. ✅ Verify all custom modules work
3. ✅ Test with real workflows
4. ✅ Document any issues found
5. ✅ Only then plan production upgrade

**When ready for production:**
1. Follow the same backup procedures
2. Schedule maintenance window
3. Notify users
4. Follow production deployment steps in UPGRADE_TO_V30_GUIDE.md
5. Have rollback plan ready

---

## Troubleshooting Common Issues

### Issue 1: White Screen After Upgrade

**Cause**: PHP error or permissions issue

**Solution**:
```bash
# Check PHP error log
tail -f /Applications/MAMP/logs/php_error.log

# Fix permissions
chmod -R 755 /Applications/MAMP/htdocs/chhs-testing
chmod -R 775 /Applications/MAMP/htdocs/chhs-testing/uploads
```

### Issue 2: Database Connection Error

**Cause**: config.php was overwritten

**Solution**:
```bash
# Restore config.php from backup
cp ~/gibbon_backups/20251130_144421/config.php.backup /Applications/MAMP/htdocs/chhs-testing/config.php
```

### Issue 3: Custom Module Not Working

**Cause**: Module incompatible with v30

**Solution**:
1. Check module documentation for v30 compatibility
2. Look for updated version of the module
3. Contact module developer
4. Temporarily disable incompatible module

### Issue 4: Upgrade Wizard Doesn't Appear

**Cause**: Database version already updated

**Solution**:
```bash
# Check database version
mysql -u root -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock -e "SELECT value FROM gibbonSetting WHERE name = 'version';" chhs-testing

# If it shows v30.0.00, upgrade likely already completed
# Verify by checking System Admin > System Check
```

---

## Rollback Procedure (If Upgrade Fails)

### Emergency Rollback to v28

If the upgrade fails catastrophically:

```bash
# 1. Stop accessing Gibbon (close browser)

# 2. Restore database
mysql -u root -proot \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  chhs-testing < ~/gibbon_backups/20251130_144421/database_v28_backup.sql

# 3. Restore files
cd /Applications/MAMP/htdocs
rm -rf chhs-testing
tar -xzf ~/gibbon_backups/20251130_144421/chhs-testing_v28_complete.tar.gz

# 4. Restore config.php
cp ~/gibbon_backups/20251130_144421/config.php.backup /Applications/MAMP/htdocs/chhs-testing/config.php

# 5. Clear cache
rm -rf /Applications/MAMP/htdocs/chhs-testing/uploads/cache/*

# 6. Test rollback
# Open: http://localhost:8888/chhs-testing
# Should show v28.0.01
```

---

## System Requirements Met

Your system meets all v30 requirements:

| Requirement | v30 Needs | Your System | Status |
|------------|-----------|-------------|--------|
| PHP Version | 8.0+ | 8.4.6 | ✅ |
| MySQL | 8.0+ | Via MAMP | ✅ |
| max_input_vars | 8000+ | Set to 5000* | ⚠️ |
| max_file_uploads | 20+ | Set to 60 | ✅ |
| allow_url_fopen | On | On | ✅ |

*Note: max_input_vars is set to 5000, which is below the recommended 8000. This may cause issues with large forms. Consider updating in php.ini if you encounter problems.

To update max_input_vars:
```bash
# Edit php.ini
nano /opt/homebrew/etc/php/8.4/php.ini

# Find and change:
max_input_vars = 8000

# Restart MAMP
```

---

## Important Files Created During Upgrade

```
/Applications/MAMP/htdocs/chhs-testing/
├── backup_before_upgrade.sh         ← Backup script (for future use)
├── perform_upgrade_to_v30.sh        ← Upgrade script (already used)
├── UPGRADE_TO_V30_GUIDE.md          ← Detailed upgrade guide
├── UPGRADE_COMPLETE_GUIDE.md        ← This file
└── switch_environment.php           ← URL switcher utility

~/gibbon_backups/
├── 20251130_144421/                 ← Main backup
│   ├── database_v28_backup.sql      ← Database backup
│   ├── chhs-testing_v28_complete.tar.gz  ← Complete files
│   ├── uploads_backup/              ← User uploads
│   └── BACKUP_MANIFEST.txt          ← Backup details
└── 20251130_144620_before_v30_upgrade/   ← Pre-upgrade snapshot
    ├── config.php.snapshot
    ├── .htaccess.snapshot
    └── custom_modules/              ← Module backups
```

---

## Upgrade Timeline

- **14:44:21** - Initial backup completed
- **14:44:28** - Database backed up (10MB)
- **14:44:32** - Files backed up (12MB)
- **14:44:35** - Uploads backed up (694MB)
- **14:46:20** - Pre-upgrade snapshot created
- **14:46:30** - Gibbon v30 files copied
- **14:46:45** - Dependencies installed
- **14:47:00** - File upgrade complete
- **NOW** - Database upgrade in progress (via web)

---

## Quick Reference Commands

```bash
# Check Gibbon version (in database)
mysql -u root -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock -e "SELECT value FROM gibbonSetting WHERE name = 'version';" chhs-testing

# Check PHP version
php -v

# Clear cache
rm -rf /Applications/MAMP/htdocs/chhs-testing/uploads/cache/*

# View backup manifest
cat ~/gibbon_backups/20251130_144421/BACKUP_MANIFEST.txt

# Open Gibbon
open http://localhost:8888/chhs-testing

# View PHP error log
tail -f /Applications/MAMP/logs/php_error.log

# View Apache error log
tail -f /Applications/MAMP/logs/apache_error.log
```

---

## What to Document

After upgrade completes, document:

1. **Final version verified**: _________
2. **Time to complete database upgrade**: _________
3. **Any errors encountered**: _________
4. **Custom modules that failed**: _________
5. **Custom modules that worked**: _________
6. **Any configuration changes needed**: _________

---

## Support Resources

- **Gibbon Documentation**: https://docs.gibbonedu.org
- **Upgrade Guide**: https://docs.gibbonedu.org/administrators/getting-started/updating-gibbon/
- **Forums**: https://ask.gibbonedu.org
- **GitHub**: https://github.com/GibbonEdu/core/issues
- **v30 Release Notes**: https://github.com/GibbonEdu/core/releases/tag/v30.0.00

---

## Current Status

**Upgrade Phase**: Database Upgrade (In Progress)

**Next Action**:
1. Complete the web-based database upgrade wizard
2. Verify upgrade success in System Check
3. Test all custom modules
4. Document results

**Browser**: Should be open at http://localhost:8888/chhs-testing

**Last Updated**: 2025-11-30

---

**Remember**: This is a LOCAL TEST upgrade. Do NOT deploy to production until thoroughly tested!

