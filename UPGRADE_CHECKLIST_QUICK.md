# Quick Upgrade Checklist - Live Site v28 → v30

**Date**: Tomorrow (Prepare today!)
**Live Site**: Currently v28
**Production (Testing)**: Currently v30 with custom mods

---

## 🚨 CRITICAL: Files That MUST Be Re-applied After Upgrade

### 1. functions.php
**What**: Contains backward-compatible `getMaxUpload()` function
**Why**: Without this, User Admin and 5+ other modules will break
**How to re-apply**:
```bash
# Backup your modified version NOW:
scp user@liveserver:/path/to/functions.php ~/functions.php.backup

# After upgrade, add lines 795-813 back to functions.php
# See CORE_FILE_MODIFICATIONS.md for exact code
```

### 2. src/Services/BackgroundProcessor.php
**What**: Fixed exec() errors for shared hosting
**Why**: Without this, batch email reports will fail
**How to re-apply**:
```bash
# Backup NOW:
scp user@liveserver:/path/to/src/Services/BackgroundProcessor.php ~/BackgroundProcessor.php.backup

# After upgrade, copy from your testing environment:
scp /Applications/MAMP/htdocs/chhs-testing/src/Services/BackgroundProcessor.php user@liveserver:/path/to/src/Services/
```

### 3. modules/User Admin/user_manage_edit.php
**What**: Fixed missing $pdo variable + modern file upload
**Why**: Without this, editing users will fail
**How to re-apply**: Check if v30 includes the fix first. If not, re-apply from testing environment

### 4. modules/User Admin/user_manage_add.php
**What**: Same fixes as user_manage_edit.php
**Why**: Without this, adding users will fail
**How to re-apply**: Check if v30 includes the fix first. If not, re-apply from testing environment

### 5. modules/Reports/src/Sources/Student.php (Optional)
**What**: Added admission date field to reports
**Why**: Nice to have, not critical
**How to re-apply**: Copy from testing environment if needed

---

## 📋 Step-by-Step Tomorrow

### Before You Start
```bash
# 1. Backup ENTIRE live site database
mysqldump -u user -p database > live_v28_backup_$(date +%Y%m%d).sql

# 2. Backup ENTIRE live site files
tar -czf live_v28_files_$(date +%Y%m%d).tar.gz /path/to/live/site/

# 3. Backup MODIFIED FILES specifically
cd /path/to/live/site
cp functions.php ~/custom_mods_backup/
cp src/Services/BackgroundProcessor.php ~/custom_mods_backup/
cp modules/User\ Admin/user_manage_edit.php ~/custom_mods_backup/
cp modules/User\ Admin/user_manage_add.php ~/custom_mods_backup/
cp modules/Reports/src/Sources/Student.php ~/custom_mods_backup/
```

### During Upgrade
1. Put site in maintenance mode
2. Download Gibbon v30
3. Copy v30 files (WILL OVERWRITE YOUR MODS!)
4. Keep config.php, uploads/, custom modules
5. Run database upgrade

### After Upgrade - RE-APPLY MODS (CRITICAL!)
```bash
# Go to your backup of testing environment
cd /Applications/MAMP/htdocs/chhs-testing

# Copy modified files to live site
scp functions.php user@liveserver:/path/to/live/site/
scp src/Services/BackgroundProcessor.php user@liveserver:/path/to/live/site/src/Services/
scp modules/User\ Admin/user_manage_edit.php user@liveserver:/path/to/live/site/modules/User\ Admin/
scp modules/User\ Admin/user_manage_add.php user@liveserver:/path/to/live/site/modules/User\ Admin/
scp modules/Reports/src/Sources/Student.php user@liveserver:/path/to/live/site/modules/Reports/src/Sources/
```

### Test Everything
- [ ] Login works
- [ ] Add new user (tests User Admin module)
- [ ] Edit existing user (tests User Admin module)
- [ ] Send batch email report (tests BackgroundProcessor)
- [ ] Check PHP error logs (should be empty)
- [ ] Test custom modules (Badges, GradeAnalytics, etc.)

---

## 🔧 Alternative: Apply from Git Patches

If you have git on the live server:

```bash
# Create patches from testing environment
cd /Applications/MAMP/htdocs/chhs-testing
git format-patch 5e55a345..HEAD --stdout > ~/upgrade_patches.patch

# Copy to live server
scp ~/upgrade_patches.patch user@liveserver:/tmp/

# On live server, after v30 upgrade:
cd /path/to/live/site
git apply /tmp/upgrade_patches.patch
```

---

## 📞 Rollback Plan

If upgrade fails:

```bash
# 1. Restore database
mysql -u user -p database < live_v28_backup_YYYYMMDD.sql

# 2. Restore files
cd /path/to
rm -rf live/site
tar -xzf live_v28_files_YYYYMMDD.tar.gz

# 3. Restart web server
# 4. Test site works
```

---

## ⚡ Quick Reference: Modified Files

Copy this list to your upgrade notes:

```
CRITICAL TO RE-APPLY:
✅ functions.php (lines 795-813)
✅ src/Services/BackgroundProcessor.php (5 changes)
✅ modules/User Admin/user_manage_edit.php (2 fixes)
✅ modules/User Admin/user_manage_add.php (2 fixes)

OPTIONAL:
⚠️ modules/Reports/src/Sources/Student.php (dateStart field)
```

---

## 💾 Files to Backup RIGHT NOW

Before you do anything tomorrow, make sure you have backups of:

1. **From Live Site (v28)**:
   - Full database dump
   - Full file backup
   - config.php
   - uploads/ directory

2. **From Testing Environment (v30 + mods)**:
   - functions.php ← **CRITICAL**
   - src/Services/BackgroundProcessor.php ← **CRITICAL**
   - modules/User Admin/*.php
   - modules/Reports/src/Sources/Student.php
   - All custom modules

---

## 📖 Full Documentation

For detailed information about each modification, see:
- `CORE_FILE_MODIFICATIONS.md` - Complete list of all changes
- `UPGRADE_TO_V30_GUIDE.md` - Full upgrade process
- `USER_ADMIN_FIX_DOCUMENTATION.md` - User Admin fix details

---

**Last Updated**: 2025-12-16
**Prepared By**: System Administrator

🤖 Generated with [Claude Code](https://claude.com/claude-code)
