# Git-Based Live Site Upgrade: v28 → v30

**Local (Testing)**: /Applications/MAMP/htdocs/chhs-testing (v30 + custom mods)
**Live Site**: /home/admin/domains/tasanz.com/public_html/chhs-tc (v28)
**SSH Access**: root@173.225.104.67

---

## Strategy Overview

Use git to deploy your tested v30 environment (with all custom modifications) to the live site.

**Benefits**:
- ✅ All custom modifications preserved automatically
- ✅ Easy rollback with git
- ✅ Version control on live site
- ✅ No manual file copying
- ✅ Track what changed

---

## Prerequisites Check

### 1. Ensure Your Local Repo is Clean and Committed

```bash
# On your Mac (local testing environment)
cd /Applications/MAMP/htdocs/chhs-testing

# Check git status - should be clean
git status

# If you have uncommitted changes, commit them:
git add .
git commit -m "Pre-live-deployment: all v30 mods tested and working"

# Push to GitHub
git push origin main
```

### 2. Verify Git Remote

```bash
# Check your remote URL
git remote -v

# Should show something like:
# origin  https://github.com/asley/Chhs-testing.git (fetch)
# origin  https://github.com/asley/Chhs-testing.git (push)
```

---

## Live Site Upgrade Process

### STEP 1: Backup Live Site (CRITICAL!)

```bash
# SSH into live server
ssh root@173.225.104.67

# Navigate to live site
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Create backup directory
mkdir -p ~/backups/$(date +%Y%m%d)_v28_to_v30

# Backup database
mysqldump -u [DB_USER] -p [DB_NAME] > ~/backups/$(date +%Y%m%d)_v28_to_v30/database_v28.sql

# Backup entire site directory
cd /home/admin/domains/tasanz.com/public_html
tar -czf ~/backups/$(date +%Y%m%d)_v28_to_v30/chhs-tc_v28_files.tar.gz chhs-tc/

# Backup critical files separately
cd chhs-tc
cp config.php ~/backups/$(date +%Y%m%d)_v28_to_v30/config.php.backup
tar -czf ~/backups/$(date +%Y%m%d)_v28_to_v30/uploads.tar.gz uploads/

# Verify backups exist
ls -lh ~/backups/$(date +%Y%m%d)_v28_to_v30/
```

### STEP 2: Check Current Git Status on Live Site

```bash
# Navigate to live site
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Check if git is initialized
ls -la .git

# If .git exists, check status:
git status
git remote -v

# If .git doesn't exist, we'll initialize it in next step
```

### STEP 3A: If Live Site Has Git Already

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Stash any local changes (if any)
git stash save "Pre-v30-upgrade local changes"

# Fetch latest from your repo
git fetch origin

# See what commits will be applied
git log HEAD..origin/main --oneline

# Pull the v30 + custom mods
git pull origin main

# If there are conflicts, resolve them (unlikely if you haven't modified live site)
```

### STEP 3B: If Live Site Doesn't Have Git (Fresh Clone)

```bash
cd /home/admin/domains/tasanz.com/public_html

# Rename current directory
mv chhs-tc chhs-tc_v28_backup

# Clone your repo with v30 + all custom mods
git clone https://github.com/asley/Chhs-testing.git chhs-tc

# Navigate into new directory
cd chhs-tc
```

### STEP 4: Restore Critical Config Files

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Restore config.php (contains database credentials)
cp ~/backups/$(date +%Y%m%d)_v28_to_v30/config.php.backup config.php

# Restore uploads directory (user files)
rm -rf uploads/
tar -xzf ~/backups/$(date +%Y%m%d)_v28_to_v30/uploads.tar.gz

# Set correct permissions
chmod 644 config.php
chmod -R 755 uploads/
chown -R [WEB_USER]:[WEB_GROUP] uploads/
```

### STEP 5: Install Dependencies

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Install composer dependencies
composer install --no-dev --optimize-autoloader

# Clear any cache
rm -rf uploads/cache/*
```

### STEP 6: Run Database Upgrade

```bash
# Option 1: Via browser (recommended)
# Visit: https://www.tasanz.com/chhs-tc/
# Gibbon will detect version mismatch and prompt for upgrade

# Option 2: Via CLI
cd /home/admin/domains/tasanz.com/public_html/chhs-tc
php cli/installer.php
```

### STEP 7: Verify Deployment

```bash
# Check git log to confirm v30 commits are there
git log --oneline -10

# Should see commits like:
# 216afb0a Fix BackgroundProcessor exec() function errors
# 51df0fc0 Fix User Admin module broken Edit User page
# 5e55a345 Upgrade Gibbon from v28.0.01 to v30.0.00

# Verify custom modifications are in place
grep -n "getMaxUpload" functions.php
# Should show the function at lines 795-813

grep -n "isExecDisabled" src/Services/BackgroundProcessor.php
# Should show the method
```

### STEP 8: Test Live Site

**Critical Tests**:
1. ✅ Login with admin account
2. ✅ Navigate to User Admin > Manage Users > Edit
3. ✅ Navigate to User Admin > Manage Users > Add
4. ✅ Send a batch email report
5. ✅ Test custom modules (Badges, GradeAnalytics, etc.)
6. ✅ Check for any errors in logs

```bash
# Check error logs
tail -f /home/admin/domains/tasanz.com/logs/error_log
# Or wherever your PHP error logs are
```

---

## Alternative: Deploy Specific Commits Only

If you want more control, you can cherry-pick specific commits:

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Cherry-pick v30 upgrade commit
git cherry-pick 5e55a345

# Cherry-pick User Admin fix
git cherry-pick 51df0fc0

# Cherry-pick BackgroundProcessor fix
git cherry-pick 216afb0a

# Cherry-pick other custom modifications
git cherry-pick 67c73619  # Student dateStart field
git cherry-pick 497ffa89  # User Admin $pdo fix
```

---

## Rollback Plan

If upgrade fails:

### Quick Rollback (Git-based)

```bash
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Rollback to previous commit
git log --oneline  # Find the commit hash before upgrade
git reset --hard [COMMIT_HASH]

# Restore database
mysql -u [DB_USER] -p [DB_NAME] < ~/backups/YYYYMMDD_v28_to_v30/database_v28.sql
```

### Full Rollback (If git fails)

```bash
cd /home/admin/domains/tasanz.com/public_html

# Remove new directory
rm -rf chhs-tc

# Restore from backup
tar -xzf ~/backups/YYYYMMDD_v28_to_v30/chhs-tc_v28_files.tar.gz

# Restore database
mysql -u [DB_USER] -p [DB_NAME] < ~/backups/YYYYMMDD_v28_to_v30/database_v28.sql
```

---

## Important Notes

### Files Git Will NOT Track (Ignored)

Make sure these are preserved:
- `config.php` (database credentials)
- `uploads/` (user files)
- `vendor/` (composer dependencies)
- `.env` files if any

Check your `.gitignore`:
```bash
cat .gitignore
```

Should contain:
```
/config.php
/uploads/*
/vendor/
*.log
```

### Files That WILL Be Updated

All core files and custom modifications:
- ✅ `functions.php` (with getMaxUpload)
- ✅ `src/Services/BackgroundProcessor.php` (with exec fixes)
- ✅ `modules/User Admin/*.php` (with fixes)
- ✅ All v30 core files
- ✅ Custom modules

---

## Post-Deployment Checklist

After successful deployment:

```bash
# 1. Mark deployment in git
cd /home/admin/domains/tasanz.com/public_html/chhs-tc
git tag -a "v30-live-deployment-$(date +%Y%m%d)" -m "v30 deployed to production"
git push origin --tags

# 2. Document deployment
echo "$(date): Upgraded live site to v30" >> ~/deployment_log.txt

# 3. Monitor logs for 24 hours
tail -f /path/to/error_log
```

**Test Again**:
- [ ] User login/logout
- [ ] Add new user
- [ ] Edit existing user
- [ ] Send batch email report
- [ ] Test all custom modules
- [ ] Check student/staff workflows
- [ ] Verify reports generate correctly

---

## Troubleshooting

### Issue: Git pull shows conflicts

```bash
# See what files conflict
git status

# For each conflict:
git checkout --ours [FILE]  # Keep live version
git checkout --theirs [FILE]  # Use repo version

# Or abort and start over:
git merge --abort
```

### Issue: Composer install fails

```bash
# Update composer
composer self-update

# Try with verbose output
composer install --no-dev -vvv
```

### Issue: Database upgrade fails

```bash
# Check database connection
php -r "new PDO('mysql:host=localhost;dbname=DB_NAME', 'DB_USER', 'DB_PASS');"

# Manually run upgrade
php cli/installer.php
```

### Issue: Permissions errors

```bash
# Fix ownership
chown -R [WEB_USER]:[WEB_GROUP] /home/admin/domains/tasanz.com/public_html/chhs-tc

# Fix file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Make uploads writable
chmod -R 775 uploads/
```

---

## Timeline Estimate

- **Backup**: 10-15 minutes
- **Git clone/pull**: 2-5 minutes
- **Restore config/uploads**: 5-10 minutes
- **Composer install**: 5-10 minutes
- **Database upgrade**: 10-20 minutes
- **Testing**: 30-60 minutes

**Total**: 1-2 hours

---

## Quick Command Reference

```bash
# One-liner backup
cd /home/admin/domains/tasanz.com/public_html/chhs-tc && \
mysqldump -u USER -p DB > ~/backup_$(date +%Y%m%d).sql && \
tar -czf ~/backup_$(date +%Y%m%d).tar.gz .

# One-liner deploy (if git initialized)
cd /home/admin/domains/tasanz.com/public_html/chhs-tc && \
cp config.php ~/config.php.tmp && \
tar -czf ~/uploads.tmp.tar.gz uploads/ && \
git pull origin main && \
cp ~/config.php.tmp config.php && \
tar -xzf ~/uploads.tmp.tar.gz && \
composer install --no-dev

# One-liner rollback
cd /home/admin/domains/tasanz.com/public_html/chhs-tc && \
git reset --hard HEAD~5 && \
mysql -u USER -p DB < ~/backup_YYYYMMDD.sql
```

---

## Summary

**Best Approach**: Use git clone or git pull to deploy your tested v30 environment

**Why This Works**:
- Your testing environment already has v30 + all custom mods
- All changes are committed and tested
- Git preserves all modifications automatically
- Easy rollback if needed
- Professional deployment method

**Critical**: Don't forget to:
1. ✅ Backup database and files FIRST
2. ✅ Preserve config.php
3. ✅ Preserve uploads/
4. ✅ Run composer install
5. ✅ Test thoroughly after deployment

---

**Created**: 2025-12-16
**For**: Live site upgrade v28 → v30 via git

🤖 Generated with [Claude Code](https://claude.com/claude-code)
