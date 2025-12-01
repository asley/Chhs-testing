# Gibbon Deployment Guide

## Environment Management

This guide explains how to safely manage your Gibbon installation across local development and production environments.

---

## Understanding Environment-Specific Settings

### Settings Stored in Database (gibbonSetting table)

These settings are **environment-specific** and should be different for local vs production:

1. **absoluteURL** - The full URL to access Gibbon
   - Local: `http://localhost:8888/chhs-testing`
   - Production: `https://www.tasanz.com/chhs-tc`

2. **absolutePath** - The file system path to Gibbon
   - Local: `/Applications/MAMP/htdocs/chhs-testing`
   - Production: `/home/admin/domains/tasanz.com/public_html/chhs-tc`

### Settings Stored in config.php

Database connection settings are in `config.php` (excluded from Git):

```php
$databaseServer = 'localhost';
$databaseUsername = 'root';
$databasePassword = 'root';
$databaseName = 'chhs-testing';
```

**IMPORTANT**: Never commit `config.php` to Git (it's in .gitignore)

---

## Safe Deployment Workflow

### Local Development (MAMP)

```bash
# 1. Work on your local copy
cd /Applications/MAMP/htdocs/chhs-testing

# 2. Make code changes
# (edit modules, themes, etc.)

# 3. Test locally
# Visit: http://localhost:8888/chhs-testing

# 4. Commit code changes only (not database)
git add .
git commit -m "Your changes"
git push origin main
```

### Deploying to Production

```bash
# On your live server (via SSH):

cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Pull code changes from GitHub
git pull origin main

# DO NOT import your local database!
# Production database already has correct URLs
```

---

## What to Deploy vs What NOT to Deploy

### ✅ SAFE TO DEPLOY (Code Changes)

- PHP files (modules, core files)
- JavaScript files
- CSS files
- Templates (Twig files)
- Images and assets
- Documentation files

### ⛔ NEVER DEPLOY (Environment-Specific)

- **Database** (use separate databases)
- **config.php** (already excluded in .gitignore)
- **uploads/** folder (user-uploaded content)
- **vendor/** folder (Composer dependencies)

---

## Environment Switcher Script

Use the included `switch_environment.php` script to safely switch between environments:

### Switch to Local Environment

```bash
php switch_environment.php local
```

### Switch to Production Environment

```bash
php switch_environment.php production
```

**When to use:**
- After importing a production database to local
- After pulling code from Git
- When URLs are pointing to wrong environment

---

## Database Management Best Practices

### Local Database (Development)

- **Purpose**: Testing new features, development work
- **URL Settings**: Point to localhost:8888
- **Import From Production**: Only when you need fresh data
- **After Import**: Run `php switch_environment.php local`

### Production Database (Live Site)

- **Purpose**: Live user data
- **URL Settings**: Point to www.tasanz.com
- **Import From Local**: NEVER! Only deploy code changes
- **Backup**: Regular backups before any changes

---

## Step-by-Step: Importing Production Database to Local

If you need to refresh your local database with production data:

### 1. Export Production Database

```bash
# On production server or via phpMyAdmin
mysqldump -u username -p database_name > gibbon_backup.sql
```

### 2. Import to Local

```bash
# Via MAMP phpMyAdmin or command line
mysql -u root -proot chhs-testing < gibbon_backup.sql
```

### 3. Fix URLs for Local Environment

```bash
# Run the environment switcher
cd /Applications/MAMP/htdocs/chhs-testing
php switch_environment.php local
```

### 4. Clear Cache

```bash
# Delete cache files
rm -rf uploads/cache/*
```

---

## Step-by-Step: Deploying Code Changes to Production

### 1. Test Locally First

```bash
# Make sure everything works locally
# Visit: http://localhost:8888/chhs-testing
```

### 2. Commit Changes

```bash
git status
git add .
git commit -m "Description of changes"
git push origin main
```

### 3. Deploy to Production

```bash
# SSH into your production server
ssh user@tasanz.com

# Navigate to Gibbon directory
cd /home/admin/domains/tasanz.com/public_html/chhs-tc

# Pull latest code
git pull origin main

# If you installed new Composer packages locally:
composer install --no-dev

# Clear cache
rm -rf uploads/cache/*
```

### 4. Test Production Site

Visit: https://www.tasanz.com/chhs-tc

---

## Common Issues and Solutions

### Issue 1: Production Site Shows Localhost URLs

**Cause**: Database was imported from local environment

**Solution**:
```bash
# On production server
php switch_environment.php production
```

### Issue 2: Local Site Shows Production URLs

**Cause**: Database was imported from production

**Solution**:
```bash
# On local MAMP
php switch_environment.php local
```

### Issue 3: Changes Not Showing Up

**Solution**:
```bash
# Clear browser cache
# Clear Gibbon cache
rm -rf uploads/cache/*

# Reload page with hard refresh
# Mac: Cmd + Shift + R
# Windows: Ctrl + F5
```

### Issue 4: Database Connection Error After Git Pull

**Cause**: Different database credentials

**Solution**:
- Ensure `config.php` exists (not in Git)
- Check database credentials match your environment
- Local: root/root
- Production: Your production DB credentials

---

## Emergency Recovery

### If Production Site is Broken After Deployment

```bash
# 1. Revert to previous working version
git log --oneline  # Find last working commit
git revert <commit-hash>
git push origin main

# 2. Or roll back completely
git reset --hard <last-working-commit>
git push origin main --force  # Use with caution!

# 3. Fix URL settings if needed
php switch_environment.php production
```

---

## Checklist Before Deploying

- [ ] All changes tested locally
- [ ] Code committed to Git
- [ ] Database URL settings correct for production
- [ ] No sensitive data in code
- [ ] Backup of production database taken
- [ ] Clear communication with users (if downtime expected)

---

## File Structure

```
chhs-testing/
├── config.php                    # NOT in Git (environment-specific)
├── switch_environment.php        # Environment switcher
├── DEPLOYMENT_GUIDE.md           # This file
├── modules/                      # Your modules (in Git)
├── uploads/                      # NOT in Git (user content)
├── vendor/                       # NOT in Git (Composer)
└── .gitignore                    # Defines what NOT to commit
```

---

## .gitignore Rules

Your `.gitignore` already excludes these:

```gitignore
# Sensitive configuration
config.php

# User uploads
uploads/*
!uploads/.htaccess

# Dependencies
vendor/
node_modules/

# Cache
uploads/cache/

# System files
.DS_Store
*.log
```

**Never remove these from .gitignore!**

---

## Quick Reference Commands

### Local Development
```bash
# Switch to local environment
php switch_environment.php local

# Access site
open http://localhost:8888/chhs-testing
```

### Production Deployment
```bash
# On production server
git pull origin main
php switch_environment.php production
rm -rf uploads/cache/*
```

### Database Refresh
```bash
# Import production DB to local
mysql -u root -proot chhs-testing < backup.sql
php switch_environment.php local
```

---

## Support

If you encounter issues:

1. Check this guide first
2. Verify URL settings in database
3. Check Gibbon error logs
4. Review Git commit history
5. Restore from backup if needed

---

**Last Updated**: 2025-11-30
**Gibbon Version**: v27.0.00
**Environments**: Local (MAMP) & Production (tasanz.com)
