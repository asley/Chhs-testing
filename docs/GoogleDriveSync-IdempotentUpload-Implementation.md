# Google Drive Sync Idempotency Implementation

Date: 2026-04-16

## Goal
Prevent duplicate Google Drive report files during sync and regeneration, while keeping sync behavior safe for partial/manual recovery scenarios.

## Step 1: Make uploads idempotent in the Drive service
File: `src/Services/GoogleDriveService.php`

Changes:
- Extended `uploadFile()` with optional `options`:
  - `existingFileId`: update this Drive file first.
  - `externalKey`: stable key stored in Drive `appProperties` (`gibbonReportArchiveEntryID`).
- Upload flow now follows this order:
  1. Update by `existingFileId` when provided.
  2. Lookup by `externalKey` and update if found.
  3. Fallback lookup by filename in target folder and update if found.
  4. Create only when no match exists.
- Added parent-folder move logic during updates (if report target folder changed).
- Added helper APIs:
  - `fileExists(string $fileId): bool`
  - `deleteFile(string $fileId): bool`
- Added shared Drive-query helpers and not-found detection to support safe fallbacks.

Why:
- This guarantees repeat sync runs update existing files instead of creating duplicates.

## Step 2: Make Force Re-sync safe (no archive-wide ID wipe)
File: `modules/Reports/archive_manage_googledriveSyncProcess.php`

Changes:
- Replaced mass `googleDriveFileID = NULL` update with a verification pass:
  - For each existing Drive ID (within scan window), call `fileExists()`.
  - Requeue only entries whose Drive file is actually missing.
- Added malformed-ID guardrail: malformed IDs are requeued immediately.
- Added per-run lock file (`flock`) to block concurrent sync jobs.
- Sync upload now passes stable external key:
  - `externalKey => gibbonReportArchiveEntryID`.
- Added run stats:
  - `requeued` (missing files queued again)
  - `checked` (existing Drive IDs validated)

Why:
- Prevents accidental full-archive re-uploads and prevents duplicate storms from concurrent runs.

## Step 3: Re-queue only changed reports after single-report regeneration
File: `modules/Reports/reports_generate_singleProcess.php`

Changes:
- On `Generate` for `Final` status, clear `googleDriveFileID` on the affected archive entry.
- On `Delete`, also delete linked Drive file (if available) before removing DB entry.

Why:
- A corrected single report now naturally queues only itself for next sync.
- Drive and archive deletion behavior stay consistent.

## Step 4: Reuse existing Drive files in other upload paths
Files:
- `modules/Reports/src/GenerateReportProcess.php`
- `modules/Reports/archive_manage_uploadProcess.php`

Changes:
- Batch generation now reads existing archive entry and passes:
  - `existingFileId` (when present)
  - `externalKey` (when entry ID exists)
- Manual archive upload path now also passes existing Drive context when overwriting.

Why:
- Keeps behavior consistent across all report upload routes and backfills stable-key usage over time.

## Step 5: Update admin UI messaging for new behavior
File: `modules/Reports/archive_manage_googledrive.php`

Changes:
- Added `error4` banner for concurrent sync lock.
- Updated Force Re-sync description to reflect missing-file verification behavior.
- Added post-run banner for checked/requeued results.
- Scope metrics on this page to `type = 'Single'` (matching sync process behavior).

Why:
- Aligns UI expectations with actual backend behavior and reduces operator mistakes.

## Validation run
Syntax checks executed:
- `php -l src/Services/GoogleDriveService.php`
- `php -l modules/Reports/archive_manage_googledriveSyncProcess.php`
- `php -l modules/Reports/archive_manage_googledrive.php`
- `php -l modules/Reports/reports_generate_singleProcess.php`
- `php -l modules/Reports/src/GenerateReportProcess.php`
- `php -l modules/Reports/archive_manage_uploadProcess.php`

All passed.

## Expected behavior after this implementation
1. Re-running sync no longer creates duplicate files for the same report entry.
2. Force Re-sync only requeues records whose Drive files are missing.
3. Correcting and regenerating one Final student report requeues only that student record.
4. Concurrent sync clicks are blocked safely.
