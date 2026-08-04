# TCExam Results Pull Phase Tracker

This tracker records the implementation phases for manual TCExam results pull into Gibbon Internal Assessments.

Last updated: 2026-08-04

## Scope Decision
Pull v1 writes to Internal Assessment only.

Markbook write-back is reserved for a later phase and requires a separate design for Markbook column selection/creation, weighting, raw-score behavior, completion state, visibility, and grade calculation side effects.

## Phase Status

| Phase | Status | Summary |
| --- | --- | --- |
| 0 | Complete | Contract and implementation planning docs created. Pull v1 scope confirmed as Internal Assessment only. |
| 1 | Complete | Refactored existing grades upsert into a shared Internal Assessment grade writer. |
| 2 | Complete | Ran regression tests for the existing inbound push path after refactor. |
| 3 | Complete | Added TCExam outbound results client and signed-request builder. |
| 4 | Complete | Added pull orchestration service for normalization, sync logging, and writer invocation. |
| 5 | Complete | Added manual POST process action with permissions, CSRF, mapping checks, and duplicate-submit protection. |
| 6 | Complete | Added "Sync from TCExam" button to mapped Internal Assessment write screens. |
| 7 | Pending | Add QA checklist, smoke evidence, and final documentation updates. |

## Phase 0: Contract and Planning

Status: Complete

Completed:
- Created `TCEXAM_PULL_CONTRACT.md`.
- Created `PULL_IMPLEMENTATION_PLAN.md`.
- Cross-linked pull docs from `README.md`, `API_CONTRACT.md`, and `TCEXAM_INTEGRATION_GUIDE.md`.
- Confirmed pull v1 writes only to Internal Assessment.
- Confirmed current bridge remains push-only until implementation phases begin.

Decisions:
- Manual trigger only for v1.
- No scheduler/background pulls in v1.
- No auto-mapping creation in v1.
- No raw TCExam payload logging.
- Accepted TCExam grade status is hardcoded to `final` for v1.
- Multiple-attempt selection rule is latest final attempt.

Idempotency decision:
- TCExam-Modern provides a stable content-fingerprint `resultBatchId`, allowing stable pull idempotency keys without timestamp fallback.

## Phase 1: Shared Internal Assessment Writer

Status: Complete

Goal:
- Extract reusable Internal Assessment write logic from `modules/juss-examBridge/api/v1/grades/service.php`.

Requirements:
- Preserve existing inbound `grades/upsert.php` behavior.
- Keep top-level idempotency outside the writer.
- Return the existing accepted/rejected per-record result shape.
- Support dry-run mode.
- Support only Internal Assessment writes.

Completion criteria:
- Existing inbound push path calls the shared writer. Complete: `processJussExamBridgeGradesUpsert()` now calls `applyJussExamBridgeInternalAssessmentGradeRecords()`.
- No behavior change in accepted/rejected response shapes. Complete: focused regression tests pass.
- PHP syntax checks pass. Complete: `php -l modules/juss-examBridge/api/v1/grades/service.php`.
- Focused tests run or the project-level test-runner blocker is documented. Complete: `vendor/bin/phpunit tests/unit/Modules/JussExamBridge/GradesUpsertServiceTest.php`.

## Phase 2: Push Regression Tests

Status: Complete

Goal:
- Prove the shared-writer extraction does not break the existing push behavior.

Coverage targets:
- Happy-path dry run.
- Happy-path write.
- Idempotent replay.
- Idempotency conflict.
- Unmapped exam/class/student.
- Student not enrolled.
- Oversized record batch.

Completion criteria:
- Relevant unit tests are added or updated. Complete: existing `GradesUpsertServiceTest` coverage exercises the refactored path.
- Tests execute successfully, or existing vendor test-runner issues are documented with exact failure. Complete: `9 tests, 26 assertions`.

## Phase 3: TCExam Results Client

Status: Complete

Goal:
- Add a mockable outbound client for TCExam results.

Endpoint path:
- Adopted `GET /api/bridge/v1/results`.

Requirements:
- Use `tcexamBaseUrl`.
- Sign outbound requests with existing bridge HMAC values.
- Reject unsafe non-HTTPS URLs except local development hosts.
- Distinguish TCExam auth, contract, not-found, rate-limit, timeout, and server errors.
- Do not perform network calls in tests.

Completion criteria:
- Client can build signed requests. Complete: `buildJussExamBridgeTcExamResultsRequest()`.
- Client response parsing is unit tested with mocked responses. Complete: `TcExamResultsClientTest`.
- Outbound path/signature mismatch diagnostics are documented. Complete: `path_mismatch` is preserved from TCExam error responses.

## Phase 4: Pull Orchestration Service

Status: Complete

Goal:
- Resolve mappings, call TCExam client, normalize records, invoke shared writer, and log sync results.

Requirements:
- Resolve `examId` from assessment mapping.
- Resolve `classExternalId` from class mapping.
- Normalize only `final` results for v1.
- Enforce maximum 200 normalized records.
- Write `direction='outbound'` and `operationType='grades_pull'` sync-log rows.

Completion criteria:
- Dry-run and write modes both work through the orchestration service. Complete: `GradesPullServiceTest`.
- Missing mapping/configuration errors are explicit. Complete: missing mapping is rejected before TCExam is called.
- No raw TCExam payload is logged. Complete: sync log stores idempotency key, payload hash, status, and safe error fields only.

## Phase 5: Manual Process Action

Status: Complete

Goal:
- Add a secure POST action for manual pull.

Requirements:
- Permission checks for Internal Assessment write access and bridge action access.
- CSRF protection following Gibbon process-file patterns.
- Server-side resolution of `examId` and `classExternalId`.
- DB-backed duplicate-submit/debounce check using recent outbound sync-log rows.

Completion criteria:
- Invalid or incomplete requests fail before calling TCExam. Complete: process action validates permissions, request method, IDs, class access, column ownership, mapping, and debounce before the pull service runs.
- Admin receives accepted/rejected counts after redirect. Complete: redirect includes accepted, rejected, skipped, dry-run, and error summary fields.

## Phase 6: Internal Assessment UI Button

Status: Complete

Goal:
- Add the manual "Sync from TCExam" button to the relevant Internal Assessment write screen.

Requirements:
- Show only for mapped Internal Assessment columns.
- Hide for `syncMode='markbook'`.
- Keep Markbook out of v1.
- Indicate dry-run state using existing Gibbon messaging patterns.

Completion criteria:
- Button appears only when mappings/settings support the action. Complete: it requires bridge action access, an Internal Assessment mapping, a class mapping, enabled grade sync, and configured TCExam credentials.
- Button submits by POST. Complete: `Form::createBlank()` adds Gibbon CSRF and nonce tokens, and the form posts to `internalAssessment_write_pullProcess.php`.
- UI text does not expose secrets or raw TCExam payloads. Complete: the screen shows only mapped exam ID, dry-run state, and summarized accepted/rejected/skipped counts.

## Phase 7: QA and Final Docs

Status: Pending

Goal:
- Validate and document the full manual pull flow.

Requirements:
- Manual QA on a mapped class and Internal Assessment column. Partial: completed a controlled live dry-run smoke through real TCExam HTTP and Gibbon write logic using temporary reversible mappings.
- Smoke evidence with accepted/rejected counts. Complete: `2026-08-04` dry-run smoke returned HTTP 200, `ok=true`, `total=1`, `accepted=1`, `rejected=0`, `skipped=0`, `attainmentValue=85.00`, with no Gibbon grade entry written.
- Final docs updated with actual endpoint path and settled TCExam response fields. Complete: docs now describe `GET /api/bridge/v1/results`, latest final attempt selection, stable `resultBatchId`, and hashed pull idempotency keys.
- TCExam endpoint available. Complete: TCExam-Modern now exposes signed `GET /api/bridge/v1/results` and returns latest final attempts with stable `resultBatchId`.
- Large-class pagination. Complete: TCExam-Modern honors `page` and `pageSize`, keeps `resultBatchId` stable across pages, and Gibbon aggregates pages before applying the existing 200-record write cap.
- Persistent environment configuration. Pending: Gibbon `tcexamBaseUrl` is currently empty, and TCExam `.env` still has the placeholder `BRIDGE_SHARED_SECRET`. Configure these before using the UI button without a test harness.

Completion criteria:
- `PULL_PHASES.md` marks all completed phases.
- Setup/checklist docs include pull prerequisites.
- Residual risks are documented.
