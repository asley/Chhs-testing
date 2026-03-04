# Perform Upgrade to v30.0.01

## Scope
This runbook upgrades this instance from **Gibbon v30.0.00** to **v30.0.01** while preserving local customizations.

Current confirmed version in this repo:
- `version.php` -> `$version = '30.0.00';`

Primary goal:
1. Upgrade core safely.
2. Re-apply local core edits (especially in Reporting).
3. Validate report generation, archive, and bulk-download behavior.

---

## Baseline and Branch Strategy
Use a clean upgrade branch from `main` (production baseline).

```bash
git checkout main
git pull origin main
git checkout -b chore/upgrade-gibbon-v30.0.01
```

Notes:
- `main` currently points to checkpoint commit `b0878bfc` (pre-juss-examBridge).
- Do not use feature branches for the initial platform upgrade unless intentionally including those features.

---

## Pre-Upgrade Checklist
1. Freeze app changes during upgrade window.
2. Backup database and files.
3. Confirm PHP/MySQL requirements in target release notes.
4. Confirm writable paths/permissions for `uploads/` and cache.
5. Record current composer and module state.

```bash
php -v
composer --version
php -m | sort
```

---

## Download and Verify v30.0.01 Package
Source:
- `https://gibbonedu.org/download/`

1. Download the official `v30.0.01` package.
2. Validate checksum against the value published on the release/download page.
3. Extract and confirm expected top-level structure before touching the live working tree.

```bash
cd /tmp

# Example filename (adjust to actual downloaded name)
FILE="Gibbon-v30.0.01.zip"

# 1) Verify archive exists
ls -lh "$FILE"

# 2) Compute checksum (compare manually with official published checksum)
shasum -a 256 "$FILE"

# 3) Extract to isolated folder
rm -rf gibbon-v30.0.01
mkdir -p gibbon-v30.0.01
unzip -q "$FILE" -d gibbon-v30.0.01

# 4) Confirm expected layout exists
ls -la gibbon-v30.0.01
find gibbon-v30.0.01 -maxdepth 2 -type d | head -n 40
```

Minimum sanity checks:
1. `version.php` exists in extracted package.
2. Core folders exist (`src`, `modules`, `resources`, `cli`, `themes`).
3. Package is clean (no unexpected local files from prior runs).

---

## Backups (Required)

```bash
STAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="$HOME/gibbon_backups/${STAMP}_before_v30.0.01"
mkdir -p "$BACKUP_DIR"

# DB backup (adjust credentials/socket as needed)
/Applications/MAMP/Library/bin/mysql80/bin/mysqldump \
  --user="root" \
  --password="root" \
  --socket="/Applications/MAMP/tmp/mysql/mysql.sock" \
  --single-transaction --routines --triggers --events \
  "Chhs-testing" > "$BACKUP_DIR/database_pre_v30.0.01.sql"

# File backup
cd /Applications/MAMP/htdocs
tar -czf "$BACKUP_DIR/chhs-testing_pre_v30.0.01.tar.gz" chhs-testing/

# Critical file snapshots
cp /Applications/MAMP/htdocs/chhs-testing/config.php "$BACKUP_DIR/config.php.backup"
cp /Applications/MAMP/htdocs/chhs-testing/.htaccess "$BACKUP_DIR/htaccess.backup" 2>/dev/null || true
```

---

## Capture Local Core Customizations Before Upgrade
Create patch files so custom edits can be reapplied predictably.

```bash
cd /Applications/MAMP/htdocs/chhs-testing
mkdir -p backups/upgrade-v30.0.01

# Reporting-focused patch set
git diff 5e55a345..b0878bfc -- \
  modules/Reports/reports_generate_single.php \
  modules/Reports/reports_generate_singleProcess.php \
  modules/Reports/src/Sources/Student.php \
  > backups/upgrade-v30.0.01/reporting-core-customizations.patch

# Other core customizations (non-reporting)
git diff 5e55a345..b0878bfc -- \
  src/Services/BackgroundProcessor.php \
  functions.php \
  modules/User\ Admin/user_manage_add.php \
  modules/User\ Admin/user_manage_edit.php \
  modules/Calendar \
  resources/templates/footer.twig.html \
  resources/templates/menu.twig.html \
  themes/Default/css/main.css \
  > backups/upgrade-v30.0.01/other-core-customizations.patch
```

Why this matters:
- `v30.0.01` may overwrite these files.
- These patch files give a deterministic reapply/review path.

---

## Reporting System: Tracked Core Changes to Preserve
These changes were made after the initial `v28 -> v30` upgrade and should be explicitly carried forward.

1. `modules/Reports/src/Sources/Student.php`
- Commit: `67c73619`
- Change: add `gibbonPerson.dateStart` to student data source.
- Effect: Admission Date renders in transcript templates.

2. `modules/Reports/reports_generate_singleProcess.php`
- Commit: `c0897d26`
- Change: include report name in PDF filenames.
- Effect: prevents file overwrite collisions between report cycles.

3. `modules/Reports/reports_generate_singleProcess.php`
- Commit: `4e3b524a`
- Change: `ini_set('memory_limit', '512M')` during PDF generation.
- Effect: avoids mPDF memory exhaustion in large report generation.

4. `modules/Reports/reports_generate_singleProcess.php`
- Commit: `30ba11f5`
- Change: bulk download resolves archive entries by `gibbonReportID`, uses actual person names for output filenames.
- Effect: downloads the correct cycle-specific report and names files clearly.

5. `modules/Reports/reports_generate_single.php`
- Commit chain includes report-generation compatibility updates tied to the fixes above.
- Effect: aligns single-report generation flow with archive/file naming behavior.

---

## Upgrade Execution (Code + DB)
1. Extract/copy official `v30.0.01` files into project (excluding `config.php`, `uploads/`).
2. Run Composer install.
3. Run Gibbon database updater.
4. Reapply local patches.
5. Resolve merge conflicts manually where upstream changed same regions.

Suggested command flow:

```bash
cd /Applications/MAMP/htdocs/chhs-testing

# 1) After copying v30.0.01 core files into place
composer install --no-dev --optimize-autoloader

# 2) Run DB updater (web or CLI)
php cli/installer.php

# 3) Reapply reporting customizations first
git apply --3way backups/upgrade-v30.0.01/reporting-core-customizations.patch

# 4) Reapply other core customizations
git apply --3way backups/upgrade-v30.0.01/other-core-customizations.patch
```

If `git apply --3way` reports conflicts:
- Keep upstream bug/security fixes from `v30.0.01`.
- Reintroduce only the required local logic.
- Re-test before commit.

---

## Post-Upgrade Validation

### Version and System
```bash
php -r "include 'version.php'; echo \$version.PHP_EOL;"
```
Expect: `30.0.01`.

### Critical Functional Tests
1. Login/logout as Admin.
2. User Admin add/edit user pages.
3. Calendar create/edit/view events.
4. Generate single student report PDF.
5. Generate report for two different cycles and verify filenames differ.
6. Bulk download mixed students and verify:
- correct cycle files are included,
- filenames use student names,
- zip completes without missing files.
7. Validate transcript Admission Date output.

### Quick Reporting Smoke Commands
```bash
# Use existing local diagnostics where available
php check_report_settings.php
php check_reporting_cycles.php
php check_internal_assessments.php
```

---

## Rollback Plan
If validation fails:

1. Restore files from tarball backup.
2. Restore database from pre-upgrade dump.
3. Confirm site returns to v30.0.00 behavior.

```bash
# Restore DB (adjust credentials/socket)
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  --user="root" --password="root" \
  --socket="/Applications/MAMP/tmp/mysql/mysql.sock" \
  "Chhs-testing" < "$BACKUP_DIR/database_pre_v30.0.01.sql"
```

Avoid `git reset --hard HEAD~N` as rollback strategy on production-like branches.

---

## Final Deliverables for This Upgrade
Before closing the task, ensure these exist:
1. Backup folder with SQL + tarball + config snapshot.
2. `backups/upgrade-v30.0.01/reporting-core-customizations.patch`.
3. `backups/upgrade-v30.0.01/other-core-customizations.patch`.
4. Upgrade branch commit(s) with conflict resolutions documented.
5. Test evidence (screenshots/log snippets) for reporting workflows.
