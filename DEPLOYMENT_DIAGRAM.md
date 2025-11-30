# Gibbon Deployment Architecture

## Visual Guide: How Git & Databases Work Together

---

## Your Complete Setup

```
┌─────────────────────────────────────────────────────────────────┐
│                    YOUR DEVELOPMENT WORKFLOW                     │
└─────────────────────────────────────────────────────────────────┘

┌──────────────────────────┐                  ┌──────────────────────────┐
│   LOCAL DEVELOPMENT      │                  │   PRODUCTION SERVER      │
│   (MAMP on Mac)          │                  │   (tasanz.com)           │
└──────────────────────────┘                  └──────────────────────────┘

┌──────────────────────────┐                  ┌──────────────────────────┐
│  📁 Code Files           │                  │  📁 Code Files           │
│  /Applications/MAMP/     │   git push →     │  /home/admin/domains/    │
│  htdocs/chhs-testing/    │   git pull ←     │  tasanz.com/chhs-tc/     │
│                          │                  │                          │
│  ├── modules/            │ ───────────────→ │  ├── modules/            │
│  ├── src/                │ ───────────────→ │  ├── src/                │
│  ├── themes/             │ ───────────────→ │  ├── themes/             │
│  └── *.php files         │ ───────────────→ │  └── *.php files         │
└──────────────────────────┘                  └──────────────────────────┘
         ↓                                              ↓
         ↓                                              ↓
┌──────────────────────────┐                  ┌──────────────────────────┐
│  🗄️  Local Database      │                  │  🗄️  Production Database │
│  localhost:8889          │                  │  (Your prod DB server)   │
│                          │                  │                          │
│  Database: chhs-testing  │   NOT SYNCED!   │  Database: proddb        │
│  User: root              │   ✗✗✗✗✗✗✗✗✗    │  User: produser          │
│  Password: root          │                  │  Password: [secure]      │
│                          │                  │                          │
│  URLs:                   │                  │  URLs:                   │
│  localhost:8888/...      │                  │  tasanz.com/chhs-tc      │
└──────────────────────────┘                  └──────────────────────────┘
         ↑                                              ↑
         └──────────────────────────────────────────────┘
                    SEPARATE DATABASES!
              (Each has its own URL settings)
```

---

## What Git Manages (Green = Safe to Deploy)

```
┌────────────────────────────────────────────────────────────┐
│                     IN GIT (TRACKED)                        │
│                   ✅ Safe to Deploy                         │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  📄 Code Files                                              │
│     ├── modules/YourModule/*.php                           │
│     ├── src/*.php                                           │
│     ├── themes/*.css                                        │
│     └── All PHP, JS, CSS files                             │
│                                                             │
│  📝 Documentation                                           │
│     ├── DEVELOPMENT.md                                      │
│     ├── DEPLOYMENT_GUIDE.md                                 │
│     ├── IMPORTANT_READ_ME.md                                │
│     └── README.md                                           │
│                                                             │
│  🔧 Configuration Templates                                 │
│     ├── .htaccess                                           │
│     ├── .gitignore                                          │
│     └── switch_environment.php                              │
│                                                             │
└────────────────────────────────────────────────────────────┘
                           │
                           │ git push
                           │ git pull
                           ↓
                    ✅ Deploys safely to production
                    ✅ No database changes
                    ✅ No sensitive data
```

---

## What Git Ignores (Red = Never Deployed)

```
┌────────────────────────────────────────────────────────────┐
│                  NOT IN GIT (IGNORED)                       │
│                  ⛔ Never Deployed                          │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  🔒 Sensitive Files                                         │
│     └── config.php (database credentials)                  │
│                                                             │
│  📁 User Content                                            │
│     └── uploads/* (user-uploaded files)                    │
│         ├── student photos                                  │
│         ├── documents                                       │
│         └── attachments                                     │
│                                                             │
│  📦 Dependencies                                            │
│     ├── vendor/ (Composer packages)                        │
│     └── node_modules/ (npm packages)                       │
│                                                             │
│  💾 Cache                                                   │
│     └── uploads/cache/*                                    │
│                                                             │
│  🗄️  Database                                              │
│     └── (Not a file, separate system!)                    │
│                                                             │
└────────────────────────────────────────────────────────────┘
                           │
                           │ .gitignore prevents
                           │ accidental commits
                           ↓
                    ✅ Protected from deployment
                    ✅ Environment-specific
                    ✅ Stays separate
```

---

## Deployment Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    SAFE DEPLOYMENT PROCESS                       │
└─────────────────────────────────────────────────────────────────┘

LOCAL MACHINE (Your Mac)                    GITHUB                    PRODUCTION SERVER
─────────────────────────                   ──────                    ─────────────────

1. Make Changes
   ├── Edit modules/
   ├── Edit themes/
   └── Test locally
         ↓
2. Commit to Git                    →       Git Repository
   git add .                                (github.com/
   git commit -m "..."                      asley/Chhs-testing)
   git push origin main
                                                    ↓
                                            3. Pull Changes
                                               ssh to server
                                               cd chhs-tc/
                                               git pull
                                                    ↓
                                            4. Code Updates
                                               ✅ New files
                                               ✅ Changed files
                                               ⛔ NO database
                                               ⛔ NO config.php
                                               ⛔ NO uploads/
                                                    ↓
                                            5. Production Ready
                                               Same code, different:
                                               • Database
                                               • URL settings
                                               • Uploads folder
```

---

## Database Management Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                  DATABASE MANAGEMENT (MANUAL)                    │
└─────────────────────────────────────────────────────────────────┘

PRODUCTION DATABASE              LOCAL DATABASE
───────────────────              ──────────────

Production Data                  Local Testing Data
├── Live users                   ├── Test users
├── Real grades                  ├── Test grades
├── URL: tasanz.com             ├── URL: localhost:8888
└── Path: /home/admin/...       └── Path: /Applications/...
         ↓                                ↑
         │                                │
         │ Export (when needed)           │
         └────────────────────────────────┘
                      │
                      ↓
              After Import:
              php switch_environment.php local
                      ↓
              ✅ URLs corrected
              ✅ Paths corrected
              ✅ Ready to use


⚠️  IMPORTANT: This is MANUAL, not automatic!
    Git does NOT sync databases!
```

---

## The Truth About Git Pull on Production

```
┌─────────────────────────────────────────────────────────────────┐
│        WHAT HAPPENS WHEN YOU RUN: git pull origin main          │
└─────────────────────────────────────────────────────────────────┘

BEFORE git pull:                    AFTER git pull:
─────────────────                   ────────────────

Production Server:                  Production Server:
├── modules/                        ├── modules/          ✅ Updated
│   └── OldModule/                  │   ├── OldModule/
│                                   │   └── NewModule/    ← NEW!
├── themes/                         ├── themes/           ✅ Updated
│   └── old.css                     │   └── new.css       ← UPDATED!
│                                   │
├── config.php                      ├── config.php        ⛔ UNCHANGED
│   Database: proddb                │   Database: proddb  (not in Git)
│                                   │
├── uploads/                        ├── uploads/          ⛔ UNCHANGED
│   └── student_photos/             │   └── student_photos/ (not in Git)
│                                   │
└── PRODUCTION DATABASE             └── PRODUCTION DATABASE
    └── URL: tasanz.com                 └── URL: tasanz.com ⛔ UNCHANGED
        (not in Git!)                       (not in Git!)


Result: Code updates, everything else stays the same! ✅
```

---

## Common Workflows Illustrated

### Workflow 1: Adding a New Feature

```
LOCAL                           GIT                 PRODUCTION
─────                           ───                 ──────────

1. Code feature
   └── modules/NewFeature/
         ↓
2. Test locally
   URL: localhost:8888
         ↓
3. Commit & Push         →    GitHub    →     4. Pull on server
   git add .                  stores            git pull
   git commit                 code
   git push                   changes
                                                      ↓
                                                5. Feature live!
                                                   Same code
                                                   Different DB URLs
```

### Workflow 2: Refreshing Local Data

```
PRODUCTION              DOWNLOAD               LOCAL
──────────              ────────               ─────

1. Export DB
   mysqldump...
         ↓
2. Download        →    SCP/FTP    →      3. Import locally
   gibbon.sql                              mysql < gibbon.sql
                                                 ↓
                                           4. FIX URLs!
                                              php switch_environment.php local
                                                 ↓
                                           5. Ready to use
                                              localhost:8888 ✅
```

### Workflow 3: Emergency Rollback

```
PRODUCTION              GIT                    LOCAL
──────────              ───                    ─────

1. Bug discovered!
   Something broken
         ↓
2. Find last good     ← GitHub ←          3. Revert locally
   git log              commit               git revert <hash>
         ↓              history              git push
4. Pull fix
   git pull
         ↓
5. Back to normal ✅
   Same database
   Old working code
```

---

## Decision Tree: What to Deploy

```
                    Making Changes?
                          │
         ┌────────────────┼────────────────┐
         ↓                ↓                ↓
    Code Files?     Database Only?    User Content?
    ├── .php        ├── Settings      ├── Photos
    ├── .css        ├── Grades        ├── Documents
    ├── .js         └── Users         └── Uploads
    └── .twig
         │                │                │
         ↓                ↓                ↓
    Use Git!         DON'T use Git!   DON'T use Git!
         │                │                │
         ↓                ↓                ↓
    git add .       Manual DB          Manual file
    git commit      import/export      transfer
    git push              │                │
         │                │                │
         └────────────────┴────────────────┘
                          │
                          ↓
              Production gets what it needs
              without breaking anything! ✅
```

---

## Summary: Your Safety Net

```
┌─────────────────────────────────────────────────────────────────┐
│                    BUILT-IN PROTECTIONS                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. .gitignore                                                   │
│     └── Prevents accidental commit of sensitive files           │
│                                                                  │
│  2. Separate Databases                                           │
│     └── Local and production never auto-sync                    │
│                                                                  │
│  3. switch_environment.php                                       │
│     └── Quickly fix URLs if database imported                   │
│                                                                  │
│  4. Documentation                                                │
│     └── Clear guides on what to do                              │
│                                                                  │
│  5. Git Version Control                                          │
│     └── Can always revert mistakes                              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Quick Answer Reference

**Q: Will git pull break my production database?**
**A: NO! Database is not in Git.**

**Q: Will git pull change production URLs?**
**A: NO! URLs are in database, not in Git.**

**Q: Will git pull deploy my local config.php?**
**A: NO! config.php is in .gitignore.**

**Q: Will git pull upload my local student photos?**
**A: NO! uploads/ is in .gitignore.**

**Q: What DOES git pull deploy?**
**A: ONLY code files (PHP, JS, CSS, modules).**

**Q: Is it safe to git pull on production?**
**A: YES! That's exactly what it's designed for!**

---

*Visual Guide Created: 2025-11-30*
*For: Gibbon Deployment on MAMP → tasanz.com*
