# Gibbon Quarterly Upgrade Runbook

> This document is the canonical process for upgrading Gibbon to a new patch/minor release.
> Follow every section in order. Do not skip steps.

---

## Overview

This repo is a fork of [GibbonEdu/core](https://github.com/GibbonEdu/core).
Upstream releases are fetched via the `upstream` git remote and applied as targeted file checkouts
(not merges) because the two repos have unrelated git histories.

**Remotes:**
```
origin    https://github.com/asley/Chhs-testing.git       (our fork)
upstream  https://github.com/GibbonEdu/core.git            (official Gibbon)
```

**Cadence:** Once per quarter, or immediately when a security release is published.

---

## Pre-Upgrade Checklist

Before starting, confirm:

- [ ] No in-flight features or hotfixes are partially merged
- [ ] MAMP is running (MySQL accessible)
- [ ] You have ~30 min of uninterrupted time
- [ ] Site is in low-traffic or off-hours window

```bash
php -v
composer --version
php -r "include 'version.php'; echo \$version.PHP_EOL;"
```

---

## Step 1 — Fetch Upstream Tags

```bash
git fetch upstream --tags
git tag | grep "^v30\|^v31"   # adjust version prefix as needed
```

Identify the target tag (e.g., `v30.0.01`).

---

## Step 2 — Identify What Changed Upstream

```bash
PREV_TAG=v30.0.00
NEW_TAG=v30.0.01

git diff --name-only $PREV_TAG $NEW_TAG
```

This shows which files the upstream release touches.

### Check for conflicts with our customizations

```bash
git diff --name-only $PREV_TAG $NEW_TAG | while read f; do
  if git diff --name-only 5e55a345..HEAD -- "$f" | grep -q .; then
    echo "CONFLICT RISK: $f"
  else
    echo "safe: $f"
  fi
done
```

> Note: `5e55a345` is the commit where this fork diverged from upstream v30.
> Update this SHA when upgrading across a major version.

Review each `CONFLICT RISK` file manually before proceeding.

---

## Step 3 — Create Upgrade Branch

```bash
git checkout main
git pull origin main
git checkout -b chore/upgrade-gibbon-$NEW_TAG
```

---

## Step 4 — Generate Pre-Upgrade Patch Files

Capture current local customizations as patch files **before** overwriting files.

```bash
cd /Applications/MAMP/htdocs/chhs-testing
mkdir -p backups/upgrade-$NEW_TAG

# Reporting module customizations
git diff 5e55a345..HEAD -- \
  modules/Reports/reports_generate_single.php \
  modules/Reports/reports_generate_singleProcess.php \
  modules/Reports/src/Sources/Student.php \
  > backups/upgrade-$NEW_TAG/reporting-core-customizations.patch

# Other core customizations
git diff 5e55a345..HEAD -- \
  src/Services/BackgroundProcessor.php \
  functions.php \
  "modules/User Admin/user_manage_add.php" \
  "modules/User Admin/user_manage_edit.php" \
  modules/Calendar \
  resources/templates/footer.twig.html \
  resources/templates/menu.twig.html \
  themes/Default/css/main.css \
  > backups/upgrade-$NEW_TAG/other-core-customizations.patch

echo "Patch line counts:"
wc -l backups/upgrade-$NEW_TAG/*.patch
```

Commit the patch files:

```bash
git add backups/upgrade-$NEW_TAG/
git commit -m "chore: add pre-upgrade patch files for $NEW_TAG"
```

---

## Step 5 — Backup Database and Files

```bash
STAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="$HOME/gibbon_backups/${STAMP}_before_${NEW_TAG}"
mkdir -p "$BACKUP_DIR"

# Database
/Applications/MAMP/Library/bin/mysql80/bin/mysqldump \
  --user="root" \
  --password="root" \
  --socket="/Applications/MAMP/tmp/mysql/mysql.sock" \
  --single-transaction --routines --triggers --events \
  "Chhs-testing" > "$BACKUP_DIR/database_pre_${NEW_TAG}.sql"

echo "DB backup: $(du -h $BACKUP_DIR/database_pre_${NEW_TAG}.sql | cut -f1)"

# Critical file snapshots
cp /Applications/MAMP/htdocs/chhs-testing/config.php "$BACKUP_DIR/config.php.backup"
cp /Applications/MAMP/htdocs/chhs-testing/.htaccess "$BACKUP_DIR/htaccess.backup" 2>/dev/null || true
echo "Backup saved to: $BACKUP_DIR"
```

---

## Step 6 — Apply Upstream Files

Check out each changed file from the new upstream tag. **Never checkout `config.php` or `uploads/`.**

```bash
# Build the file list from the diff (exclude CHANGEDB.php — handled separately)
git diff --name-only $PREV_TAG $NEW_TAG | grep -v "^CHANGEDB.php$" | while read f; do
  git checkout $NEW_TAG -- "$f" && echo "OK: $f" || echo "FAILED: $f"
done
```

---

## Step 7 — Merge CHANGEDB.php Manually

Upstream adds new migration entries to `CHANGEDB.php`. We also have local migrations.
These must be combined — do NOT blindly overwrite.

```bash
# View what upstream changed
git diff $PREV_TAG $NEW_TAG -- CHANGEDB.php
```

1. Checkout the upstream version:
   ```bash
   git checkout $NEW_TAG -- CHANGEDB.php
   ```

2. Re-add any local custom migrations that were present in the previous version.
   Currently tracked local migrations:
   - `ALTER TABLE gibbonInternalAssessmentColumn ADD COLUMN IF NOT EXISTS locked ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER viewableParents;end`
     → Insert inside the v30.0.00 `$sql[$count][1]` block, after the last upstream entry and before the closing `";`

3. Verify both upstream and local entries are present:
   ```bash
   grep -n "locked\|v30.0.01" CHANGEDB.php | tail -10
   ```

---

## Step 8 — Run Composer Install

```bash
composer install --no-dev --optimize-autoloader
```

---

## Step 9 — Run Database Upgrade

**Option A — Web (recommended):**
1. Open `http://localhost:8888/chhs-testing`
2. Gibbon will detect the version mismatch and prompt for upgrade
3. Follow the upgrade wizard

**Option B — CLI:**
```bash
php cli/installer.php
```

---

## Step 10 — Reapply Local Patches (if needed)

If Step 6 overwrote any locally-customized files, reapply patches:

```bash
git apply --3way backups/upgrade-$NEW_TAG/reporting-core-customizations.patch
git apply --3way backups/upgrade-$NEW_TAG/other-core-customizations.patch
```

If conflicts occur:
- Keep upstream security/bug fixes
- Manually re-insert local logic
- Re-test before committing

---

## Step 11 — Commit Upgrade

Stage only upgrade-relevant files. **Do not stage** `uploads/`, untracked binaries, or dev artifacts.

```bash
# Stage changed core files
git add \
  CHANGEDB.php CHANGELOG.txt composer.lock gibbon.sql version.php \
  modules/ src/ resources/ themes/

# Review what's staged
git status --short

git commit -m "chore(upgrade): apply Gibbon $NEW_TAG upstream changes"
```

---

## Step 12 — Verify Version

```bash
php -r "include 'version.php'; echo \$version.PHP_EOL;"
# Expected: 30.0.01  (or the new target version)
```

---

## Step 13 — Functional Validation

Run through these manually in the browser at `http://localhost:8888/chhs-testing`:

| # | Test | Expected |
|---|------|----------|
| 1 | Login/logout as Admin | Success |
| 2 | User Admin — add/edit user | No errors |
| 3 | Calendar — create/view event | Event saves and displays |
| 4 | Formal Assessment — lock/unlock column | Lock toggle works; teachers cannot see locked columns |
| 5 | Generate single student report PDF | PDF downloads, filename includes report name |
| 6 | Generate reports for two different cycles | Filenames differ (no overwrite collision) |
| 7 | Bulk download — mixed students | Correct cycle files; filenames use student names; zip completes |
| 8 | Transcript — Admission Date | Displays correctly |
| 9 | Data Admin — orphaned records | Delete buttons functional |
| 10 | System Admin — clear cache | Cache clears without fatal error |

---

## Step 14 — Merge to Main

Once all validation passes:

```bash
git checkout main
git merge --no-ff chore/upgrade-gibbon-$NEW_TAG
git push origin main
```

Then deploy to live site per `GIT_UPGRADE_LIVE_SITE.md`.

---

## Rollback Procedure

If validation fails at any point:

```bash
# Restore database
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  --user="root" --password="root" \
  --socket="/Applications/MAMP/tmp/mysql/mysql.sock" \
  "Chhs-testing" < "$BACKUP_DIR/database_pre_${NEW_TAG}.sql"

# Restore config (if overwritten)
cp "$BACKUP_DIR/config.php.backup" /Applications/MAMP/htdocs/chhs-testing/config.php

# Return to main branch
git checkout main
git branch -D chore/upgrade-gibbon-$NEW_TAG
```

---

## Local Customizations to Always Re-Apply

These are CHHS-specific changes that live outside of the module system.
Always verify these survive each upgrade:

| File | Change | Commit |
|------|--------|--------|
| `CHANGEDB.php` | `locked` column migration for `gibbonInternalAssessmentColumn` | manual merge |
| `modules/Reports/src/Sources/Student.php` | `gibbonPerson.dateStart` added to student data source | `67c73619` |
| `modules/Reports/reports_generate_singleProcess.php` | report name in PDF filenames | `c0897d26` |
| `modules/Reports/reports_generate_singleProcess.php` | `memory_limit 512M` for mPDF | `4e3b524a` |
| `modules/Reports/reports_generate_singleProcess.php` | bulk download by gibbonReportID + student names | `30ba11f5` |
| `src/Services/BackgroundProcessor.php` | `exec()` namespace fix + `isExecDisabled()` fallback | `216afb0a9` |
| `functions.php` | `getMaxUpload()` helper | — |
| `modules/User Admin/user_manage_add.php` | `$pdo` variable fix | `497ffa890` |
| `modules/User Admin/user_manage_edit.php` | `$pdo` variable fix | `497ffa890` |

> See `CLAUDE.md` for full descriptions of the Assessment Locking, Orphaned Records, and Cache Manager features.

---

## History of Upgrades Performed

| Date | From | To | Branch | Notes |
|------|------|----|--------|-------|
| 2026-04-08 | v30.0.00 | v30.0.01 | `chore/upgrade-gibbon-v30.0.01` | 16 upstream files changed; CHANGEDB.php merged manually; no patch conflicts; DB upgrade ran successfully |
