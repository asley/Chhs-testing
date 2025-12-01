# ⚠️ IMPORTANT: Read This First!

## Your Question: "Will database changes affect my live site?"

### SHORT ANSWER: YES, if you're not careful! ⚠️

---

## What Just Happened

We updated your **local database** to use:
- URL: `http://localhost:8888/chhs-testing`
- Path: `/Applications/MAMP/htdocs/chhs-testing`

Your **production database** still has:
- URL: `https://www.tasanz.com/chhs-tc`
- Path: `/home/admin/domains/tasanz.com/public_html/chhs-tc`

---

## The Golden Rules 🌟

### ✅ DO Deploy to Production:
1. **Code changes** (PHP, JavaScript, CSS files)
2. **New modules** (custom functionality)
3. **Templates** (Twig files)
4. **Documentation** (like this file)

**How to deploy:**
```bash
# On production server
cd /home/admin/domains/tasanz.com/public_html/chhs-tc
git pull origin main
```

### ⛔ NEVER Deploy to Production:
1. **Your local database** (has wrong URLs!)
2. **config.php** (has your MAMP database password)
3. **uploads/ folder** (user-uploaded content)
4. **vendor/ folder** (Composer dependencies)

---

## How Git Protects You

Your `.gitignore` file automatically excludes:
```
config.php          ← Database credentials (never in Git)
uploads/*           ← User uploads (never in Git)
vendor/             ← Composer packages (never in Git)
```

**This means:** Even if you try to `git add .`, these won't be included! ✅

---

## Safe Workflow Examples

### Example 1: Adding a New Module

```bash
# 1. On your local MAMP
cd /Applications/MAMP/htdocs/chhs-testing
# ... add your new module to modules/ folder ...

# 2. Test it locally
open http://localhost:8888/chhs-testing

# 3. Commit the code (NOT the database)
git add modules/MyNewModule/
git commit -m "Add new module: MyNewModule"
git push origin main

# 4. Deploy to production
ssh user@tasanz.com
cd /home/admin/domains/tasanz.com/public_html/chhs-tc
git pull origin main

# 5. Install the module in production
# - Go to System Admin > Manage Modules
# - Click "Install" on your new module
# ✅ Production database gets its own module settings!
```

### Example 2: Updating Existing Code

```bash
# 1. Make changes locally
cd /Applications/MAMP/htdocs/chhs-testing
# ... edit some PHP files ...

# 2. Test locally
open http://localhost:8888/chhs-testing

# 3. Commit changes
git add .
git commit -m "Fix bug in attendance module"
git push origin main

# 4. Deploy to production
ssh user@tasanz.com
cd /home/admin/domains/tasanz.com/public_html/chhs-tc
git pull origin main

# ✅ Your code updates, database stays the same!
```

### Example 3: Refreshing Local Database from Production

```bash
# 1. Export production database
# (Use phpMyAdmin or command line on production)
mysqldump -u produser -p proddb > gibbon_production.sql

# 2. Download to local machine
scp user@tasanz.com:gibbon_production.sql ~/Downloads/

# 3. Import to local MAMP
mysql -u root -proot chhs-testing < ~/Downloads/gibbon_production.sql

# 4. FIX THE URLS! (This is critical!)
cd /Applications/MAMP/htdocs/chhs-testing
php switch_environment.php local

# ✅ Now your local has fresh data with correct URLs!
```

---

## The Environment Switcher Script

We created `switch_environment.php` to save you from URL disasters:

### When to Use It:

**After importing production database:**
```bash
php switch_environment.php local
```

**Before deploying database to production (rare!):**
```bash
php switch_environment.php production
```

### What It Does:

```
BEFORE:  absoluteURL → https://www.tasanz.com/chhs-tc
AFTER:   absoluteURL → http://localhost:8888/chhs-testing
```

---

## Real-World Scenario: What Could Go Wrong

### ❌ BAD: Deploying Database

```bash
# On local machine
mysqldump -u root -proot chhs-testing > local_backup.sql

# On production server
mysql -u produser -p proddb < local_backup.sql

# 💥 DISASTER! Production site now shows:
# - All links point to http://localhost:8888/chhs-testing
# - Users can't access the site
# - Everything is broken!
```

### ✅ GOOD: Deploying Code Only

```bash
# On local machine
git add modules/MyModule/
git commit -m "Add new feature"
git push origin main

# On production server
git pull origin main

# ✅ SUCCESS! Code updates, URLs stay correct!
```

---

## Quick Troubleshooting

### Problem: "Live site shows localhost URLs after deployment"

**Cause:** You accidentally deployed your local database

**Fix:**
```bash
# On production server
php switch_environment.php production
# Or restore production database from backup
```

### Problem: "Local site shows production URLs"

**Cause:** You imported production database but forgot to switch

**Fix:**
```bash
# On local MAMP
php switch_environment.php local
```

### Problem: "Changes not showing on production"

**Fix:**
```bash
# Clear cache
rm -rf uploads/cache/*

# Hard refresh browser
# Mac: Cmd + Shift + R
# Windows: Ctrl + F5
```

---

## Your Current Setup

### Local Development (MAMP)
```
URL:      http://localhost:8888/chhs-testing
Path:     /Applications/MAMP/htdocs/chhs-testing
Database: chhs-testing (MySQL port 8889)
User:     root
Password: root
```

### Production Server
```
URL:      https://www.tasanz.com/chhs-tc
Path:     /home/admin/domains/tasanz.com/public_html/chhs-tc
Database: [Your production database]
User:     [Your production DB user]
Password: [Your production DB password]
```

---

## Key Files in Your Project

```
chhs-testing/
│
├── IMPORTANT_READ_ME.md        ← This file!
├── DEPLOYMENT_GUIDE.md         ← Detailed deployment guide
├── DEVELOPMENT.md              ← Development environment docs
├── switch_environment.php      ← URL switching utility
│
├── config.php                  ← NOT in Git (environment-specific)
├── .gitignore                  ← Protects sensitive files
│
├── modules/                    ← Your custom modules (IN Git)
├── uploads/                    ← User content (NOT in Git)
└── vendor/                     ← Dependencies (NOT in Git)
```

---

## Remember These 3 Rules

1. **CODE goes in Git** → Deploy with `git pull`
2. **DATABASE stays separate** → Never import between environments without switching URLs
3. **When in doubt** → Read DEPLOYMENT_GUIDE.md

---

## Need Help?

1. **Deployment questions** → See DEPLOYMENT_GUIDE.md
2. **Development setup** → See DEVELOPMENT.md
3. **Environment issues** → Use `switch_environment.php`
4. **Emergency** → Restore from backup!

---

## One More Time: The Answer to Your Question

**Q: Will database changes affect my live site if I pull from GitHub to production?**

**A: NO, Git doesn't include your database!** ✅

Git only includes:
- ✅ Code files (PHP, JS, CSS)
- ✅ Modules
- ✅ Documentation

Git NEVER includes:
- ⛔ Database
- ⛔ config.php
- ⛔ uploads/
- ⛔ vendor/

**Your production database is completely separate and won't be affected by `git pull`!**

The only way to mess up production database is if you **manually import** your local database to production. Git won't do that automatically.

---

**Stay Safe, Deploy Confidently!** 🚀

*Last Updated: 2025-11-30*
