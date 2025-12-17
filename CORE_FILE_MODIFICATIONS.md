# Gibbon Core File Modifications

**Project**: CHHS Testing (Gibbon v30.0.00)
**Base Version**: Gibbon v30.0.00
**Last Updated**: 2025-12-16

This document tracks all modifications made to Gibbon core files (files in `src/`, `functions.php`, `index.php`, etc.) that differ from the official Gibbon v30.0.00 release.

---

## Critical: Files Modified After v30 Upgrade

### 1. `functions.php`
**Commit**: `51df0fc0` (2025-12-04)
**Status**: ⚠️ **CUSTOM MODIFICATION**
**Location**: Lines 795-813

**Changes Made**:
- Added `getMaxUpload()` function for backward compatibility with v25-deprecated function
- This function was removed in Gibbon v30 but still used by 5+ modules

**Code Added**:
```php
/**
 * Get the maximum upload size based on PHP configuration.
 * Returns the smaller of post_max_size and upload_max_filesize.
 *
 * @param bool $asString If true, returns formatted string. If false, returns numeric value.
 * @return string|int Formatted string like "Uploads: 8MB max" or numeric value
 */
function getMaxUpload($asString = false)
{
    $post = substr(ini_get('post_max_size'), 0, (strlen(ini_get('post_max_size')) - 1));
    $file = substr(ini_get('upload_max_filesize'), 0, (strlen(ini_get('upload_max_filesize')) - 1));
    $label = ($post < $file) ? $post : $file;

    if ($asString) {
        return sprintf(__('Uploads: %sMB max'), $label);
    }

    return $label;
}
```

**Why This Was Needed**:
- Gibbon v30 removed this deprecated function
- User Admin module still called it, causing fatal errors
- Multiple other modules (Markbook, Planner, Students, Staff) also use it
- Acts as compatibility bridge until all modules are refactored

**Impact on Upgrade**:
- ⚠️ **WILL BE OVERWRITTEN** during upgrade to v30 if you replace functions.php
- **MUST** re-apply this modification after upgrade
- **ALTERNATIVE**: Update all modules to use modern `FileUpload` class instead

**See Also**: `USER_ADMIN_FIX_DOCUMENTATION.md`

---

### 2. `src/Services/BackgroundProcessor.php`
**Commits**:
- `5e55a345` (2025-11-30) - Upgraded to v30
- `216afb0a` (2025-12-16) - Fixed exec() errors

**Status**: ⚠️ **CUSTOM MODIFICATION**
**Lines Modified**: 78-80, 107-111, 124-128, 123, 128, 278, 306, 326-332

**Changes Made**:

#### Change 1: Path Handling (Lines 78-80)
```php
// BEFORE (v30 default):
$phpFile = $this->session->get('absolutePath').'/cli/system_backgroundProcessor.php';
$phpOutput = '/dev/null';

if (!file_exists($phpFile)) {
    throw new \RuntimeException('File not found: '.$phpFile);
}

// AFTER (custom):
// Prefer the configured absolutePath, but fall back to project root if it's unavailable.
$basePath = $this->session->get('absolutePath') ?: realpath(__DIR__.'/../..');
$phpFile = $basePath.'/cli/system_backgroundProcessor.php';
$phpOutput = '/dev/null';
```

#### Change 2: File Readability Check (Lines 107-111)
```php
// ADDED:
// If the background worker script is missing/unreadable, run synchronously.
if (!is_readable($phpFile)) {
    $this->runProcess($processID, $processData['key']);
    return $processID;
}
```

#### Change 3: exec() Availability Check (Lines 124-128)
```php
// ADDED:
// If exec is unavailable (often disabled in shared hosting), run synchronously.
if (!function_exists('exec') || $this->isExecDisabled()) {
    $this->runProcess($processID, $processData['key']);
    return $processID;
}
```

#### Change 4: Global exec() Namespace Fix (Lines 123, 128, 278, 306)
```php
// BEFORE (v30 default):
exec(sprintf('%s > NUL &', $command));
$pID = exec(sprintf("%s > %s 2>&1 & echo $!", $command, $phpOutput));
exec('kill -9 '.$processData['pID']);
$checkProcess = exec('ps '.$processData['pID']);

// AFTER (custom - added backslash prefix):
\exec(sprintf('%s > NUL &', $command));
$pID = \exec(sprintf("%s > %s 2>&1 & echo $!", $command, $phpOutput));
\exec('kill -9 '.$processData['pID']);
$checkProcess = \exec('ps '.$processData['pID']);
```

#### Change 5: New Method isExecDisabled() (Lines 326-332)
```php
// ADDED:
/**
 * Detect if exec is disabled via php.ini disable_functions.
 *
 * @return bool
 */
protected function isExecDisabled() : bool
{
    $disabled = ini_get('disable_functions') ?: '';
    $functions = array_filter(array_map('trim', explode(',', $disabled)));

    return in_array('exec', $functions, true);
}
```

**Why This Was Needed**:
- Production servers often disable `exec()` for security (shared hosting)
- Namespace issue: calling `exec()` in namespace looked for `Gibbon\Services\exec()`
- Need graceful fallback to synchronous processing when exec() unavailable
- Email batch reports were failing with fatal errors

**Impact on Upgrade**:
- ⚠️ **WILL BE OVERWRITTEN** during upgrade if Gibbon releases BackgroundProcessor updates
- **MUST** re-apply these modifications after upgrade
- **BENEFIT**: Improves compatibility with shared hosting environments

---

### 3. `modules/Reports/src/Sources/Student.php`
**Commit**: `67c73619` (2025-12-05)
**Status**: ✅ **FEATURE ENHANCEMENT** (Safe)
**Line Modified**: ~75 (added field)

**Changes Made**:
- Added `dateStart` (admission date) to available Student report data fields

**Code Added**:
```php
'dateStart' => __('Admission Date'),  // Added line
```

**Why This Was Needed**:
- Allows report templates to display student admission dates
- Useful for enrollment reports and student records

**Impact on Upgrade**:
- ⚠️ **WILL BE OVERWRITTEN** during upgrade
- Safe to re-apply - non-breaking addition
- No negative impact if lost during upgrade

---

## Core Module Modifications (Post-v30)

These are modifications to **core Gibbon modules** (not custom modules) after the v30 upgrade:

### 4. `modules/User Admin/user_manage_edit.php`
**Commits**:
- `497ffa89` (2025-12-04) - Fixed missing $pdo variable
- `51df0fc0` (2025-12-04) - Modernized file upload handling

**Status**: ⚠️ **BUG FIX + MODERNIZATION**

**Changes Made**:

#### Change 1: Missing $pdo Variable (Line ~45)
```php
// ADDED:
$pdo = $container->get(Connection::class);
```

#### Change 2: FileUpload Modernization (Lines 144, 567)
```php
// BEFORE:
$row->addFileUpload('file1')
    ->accepts('.jpg,.jpeg,.gif,.png')
    ->setAttachment('attachment1', $session->get('absoluteURL'), $values['image_240'])
    ->setMaxUpload(false);  // ❌ Disables built-in max upload
// ...later...
$row->addFooter()->append('<small>'.getMaxUpload(true).'</small>');  // ❌ Uses deprecated function
$row->addSubmit();

// AFTER:
$row->addFileUpload('file1')
    ->accepts('.jpg,.jpeg,.gif,.png')
    ->setAttachment('attachment1', $session->get('absoluteURL'), $values['image_240']);
    // ✅ Uses default setMaxUpload(true) - displays max upload automatically
// ...later...
$row->addSubmit();  // ✅ Max upload displays automatically with field
```

**Why This Was Needed**:
- Page was broken ("Call to undefined function getMaxUpload()")
- Missing $pdo variable caused errors
- Modernizes to use FileUpload class capabilities

**Impact on Upgrade**:
- ⚠️ **WILL BE OVERWRITTEN** during upgrade
- **MUST** re-apply after upgrade OR ensure Gibbon fixes this in future releases
- **ALTERNATIVE**: If functions.php still has getMaxUpload(), old code will work

**See Also**: `USER_ADMIN_FIX_DOCUMENTATION.md`

---

### 5. `modules/User Admin/user_manage_add.php`
**Commits**:
- `497ffa89` (2025-12-04) - Fixed missing $pdo variable
- `51df0fc0` (2025-12-04) - Modernized file upload handling

**Status**: ⚠️ **BUG FIX + MODERNIZATION**

**Changes Made**: (Same as user_manage_edit.php above)

#### Change 1: Missing $pdo Variable
```php
// ADDED:
$pdo = $container->get(Connection::class);
```

#### Change 2: FileUpload Modernization (Lines 106, 473)
```php
// BEFORE:
->setMaxUpload(false)
// ...later...
$row->addFooter()->append('<small>'.getMaxUpload(true).'</small>');

// AFTER:
// Removed setMaxUpload(false)
// Removed footer line with getMaxUpload()
```

**Impact on Upgrade**: Same as user_manage_edit.php

---

## Files NOT Modified (But Included in v30 Upgrade)

The following were updated during the v28 → v30 upgrade but are **standard Gibbon v30 files** with no custom modifications:

### Core Framework Files
- `index.php` - Updated to v30 (no custom changes)
- `src/Auth/` - All files updated to v30
- `src/Comms/` - All files updated to v30
- `src/Database/` - All files updated to v30
- `src/Domain/` - All gateway files updated to v30
- `src/Forms/` - All form files updated to v30
- `src/Services/` - All except BackgroundProcessor.php are standard v30
- `src/Session/` - Updated to v30
- `src/Tables/` - Updated to v30
- `src/UI/` - Updated to v30

---

## Summary of Custom Modifications

| File | Type | Reason | Upgrade Risk |
|------|------|--------|--------------|
| `functions.php` | Compatibility Function | Add deprecated getMaxUpload() for module compatibility | **HIGH** - Will be overwritten |
| `src/Services/BackgroundProcessor.php` | Bug Fix + Enhancement | Fix exec() namespace + shared hosting compatibility | **HIGH** - Will be overwritten |
| `modules/Reports/src/Sources/Student.php` | Feature Addition | Add admission date to reports | **MEDIUM** - Will be overwritten |
| `modules/User Admin/user_manage_edit.php` | Bug Fix + Modernization | Fix missing $pdo + modernize file upload | **HIGH** - Will be overwritten |
| `modules/User Admin/user_manage_add.php` | Bug Fix + Modernization | Fix missing $pdo + modernize file upload | **HIGH** - Will be overwritten |

---

## Pre-Upgrade Checklist for Production

Before upgrading your live site (v28 → v30), you **MUST**:

### 1. Backup Modified Files
```bash
# Create backup directory
mkdir -p ~/gibbon_core_mods_backup

# Backup modified core files
cp functions.php ~/gibbon_core_mods_backup/
cp src/Services/BackgroundProcessor.php ~/gibbon_core_mods_backup/
cp modules/Reports/src/Sources/Student.php ~/gibbon_core_mods_backup/
cp modules/User\ Admin/user_manage_edit.php ~/gibbon_core_mods_backup/
cp modules/User\ Admin/user_manage_add.php ~/gibbon_core_mods_backup/
```

### 2. Document Current Modifications
- Print this document
- Save all git commits: `git log --patch > ~/modifications.txt`
- Save diff from v30: `git diff 5e55a345..HEAD > ~/custom_changes.diff`

### 3. After Upgrade - Re-apply Modifications

**Critical files to re-apply**:
1. ✅ `functions.php` - Add getMaxUpload() function (lines 795-813)
2. ✅ `src/Services/BackgroundProcessor.php` - All 5 changes listed above
3. ⚠️ `modules/User Admin/*` - Check if Gibbon fixed these in their update first
4. ⚠️ `modules/Reports/src/Sources/Student.php` - Nice to have, not critical

**Commands to re-apply**:
```bash
# Option 1: Manual copy from backup
cp ~/gibbon_core_mods_backup/functions.php .
cp ~/gibbon_core_mods_backup/BackgroundProcessor.php src/Services/

# Option 2: Use git cherry-pick (if upgrading via git)
git cherry-pick 51df0fc0  # functions.php fix
git cherry-pick 216afb0a  # BackgroundProcessor fix
git cherry-pick 67c73619  # Student.php dateStart
git cherry-pick 497ffa89  # User Admin $pdo fix
```

### 4. Test After Re-applying Modifications
- ✅ Test User Admin: Add/Edit users
- ✅ Test Reports: Send batch email reports
- ✅ Test File Uploads: Verify max upload display shows
- ✅ Check error logs: `tail -f /path/to/php_error.log`

---

## Recommendations for Future

### Short Term (Before Production Upgrade)
1. Test full upgrade process on local/staging first
2. Create script to automate re-application of modifications
3. Check Gibbon v31+ release notes to see if they fixed these issues

### Long Term (After Successful Upgrade)
1. **Submit Pull Requests** to Gibbon project for:
   - BackgroundProcessor exec() fixes
   - User Admin module fixes
2. **Refactor Custom Modules** to not use deprecated functions
3. **Monitor Gibbon Updates** for official fixes to these issues
4. **Consider Upstreaming** beneficial changes back to Gibbon

---

## Related Documentation

- `USER_ADMIN_FIX_DOCUMENTATION.md` - Detailed User Admin fix documentation
- `UPGRADE_TO_V30_GUIDE.md` - Complete v28 → v30 upgrade guide
- `CLAUDE.md` - Project development guidelines

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-12-16 | Initial documentation of all core file modifications |

---

**CRITICAL REMINDER**: All modifications listed here **WILL BE OVERWRITTEN** when you:
- Download and copy fresh Gibbon v30 files
- Upgrade to Gibbon v31 or later
- Run automated update scripts

Always re-apply these modifications after any Gibbon core file updates.

---

🤖 Generated with [Claude Code](https://claude.com/claude-code)
