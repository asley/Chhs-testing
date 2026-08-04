# TCExam Results Pull Implementation Plan

This plan covers the manual "Sync from TCExam" feature. The Gibbon-side implementation follows the contract in `TCEXAM_PULL_CONTRACT.md`.

## Current State
The original bridge was push-only for grades:
- TCExam calls `POST /modules/juss-examBridge/api/v1/grades/upsert.php`.
- Gibbon verifies the signed request.
- Gibbon validates mappings and writes `gibbonInternalAssessmentEntry`.

The setting `tcexamBaseUrl` exists, but no code currently uses it for outbound requests.

Pull v1 writes to Internal Assessment only. Markbook write-back remains out of scope.

## Target Flow
1. Admin opens Gibbon's Internal Assessment write screen for a class and column.
2. Gibbon detects that the selected column has a `gibbonJussExamBridgeAssessmentMap` row.
3. Gibbon shows a manual `Sync from TCExam` action when bridge settings and mappings are complete.
4. Admin submits the action with CSRF protection.
5. Gibbon resolves `examId`, `classExternalId`, and `gibbonInternalAssessmentColumnID`.
6. Gibbon signs and sends a `GET` request to TCExam's results endpoint.
7. Gibbon normalizes TCExam results into the existing grade-record shape.
8. Gibbon applies the records through the shared grade-write service.
9. Gibbon logs the pull in `gibbonJussExamBridgeSyncLog`.
10. Gibbon redirects back with accepted/rejected counts.

## Recommended Implementation Order
1. Extract shared grade writer.
2. Add tests proving the existing push path still behaves the same.
3. Add TCExam results client interface and signed-request builder.
4. Add pull orchestration service.
5. Add manual process action.
6. Add Internal Assessment UI button.
7. Add manual QA checklist and smoke evidence.

## Shared Grade Writer
Extract the mapping and write logic from `modules/juss-examBridge/api/v1/grades/service.php`.

Recommended responsibility:
- Accept normalized records.
- Validate required fields.
- Resolve assessment, class, person, and enrollment mappings.
- Compute `attainmentValue`.
- Respect `dryRunEnabled`.
- Insert/update `gibbonInternalAssessmentEntry`.
- Return the existing per-record result shape.
- Reject Markbook writes.

Keep these responsibilities outside the shared writer:
- HTTP request parsing.
- HMAC verification/signing.
- TCExam HTTP calls.
- Top-level idempotency handling.
- UI redirects.
- Markbook writes.

## Pull Client
Create a small client wrapper rather than putting HTTP code in a process script.

Recommended responsibilities:
- Read `tcexamBaseUrl`, `bridgeKeyId`, and bridge secret.
- Reject unsafe or missing configuration.
- Build the signed outbound request.
- Apply timeout and result-size limits.
- Decode TCExam JSON.
- Return either a typed success payload or a typed error.

Testing should mock this client; tests must not call TCExam or the network.

## Manual Process Action
Use a POST process script in `modules/juss-examBridge`.

Required checks:
- User can access the relevant Internal Assessment write action.
- User can access the juss-examBridge sync action.
- `gradeSyncEnabled` is `Y`.
- Required mappings exist.
- Assessment mapping `syncMode` is `internal_assessment`.
- `tcexamBaseUrl`, `bridgeKeyId`, and bridge secret are configured.
- CSRF protection follows the local Gibbon process-file pattern.

The process action should not accept `examId` or `classExternalId` directly from the browser when they can be resolved from Gibbon mapping tables.

## UI Button
Add the button to the Internal Assessment write screen only when:
- The current column is mapped in `gibbonJussExamBridgeAssessmentMap`.
- The current class is mapped in `gibbonJussExamBridgeClassMap`.
- The assessment mapping `syncMode` is `internal_assessment`.
- Grade sync is enabled.

The UI should show dry-run state clearly using existing Gibbon messaging patterns. It should not expose the bridge secret or raw TCExam payload data.

## Idempotency and Logging
Use `gibbonJussExamBridgeSyncLog`.

Recommended values:
- `direction`: `outbound`
- `operationType`: `grades_pull`
- `idempotencyKey`: `pull:{targetHash}:{resultBatchHash}`

Decision:
- TCExam will provide a stable `resultBatchId`, so pull retries can use a stable sync-log idempotency key.
- Gibbon hashes the pull target and `resultBatchId` in the idempotency key to avoid storing raw exam/class identifiers in that field.

## Failure Handling
Admin-facing outcomes should distinguish:
- Bridge not configured.
- TCExam authentication/signature failure.
- TCExam timeout or unavailable.
- TCExam contract error.
- Missing Gibbon mapping.
- Student not enrolled.
- Write failure.

Do not log raw result payloads. Log only counts, status, idempotency key, and safe error codes.

## First Test Targets
- Existing push service still returns the same results after writer extraction.
- Pull service normalizes a valid TCExam response and writes via dry-run mode.
- Pull service rejects TCExam results over 200 records.
- Pull service handles TCExam `401`, `404`, `429`, and timeout distinctly.
- Manual action refuses missing mapping/configuration before calling TCExam.

Phase 3 decisions:
- Gibbon signs `GET /api/bridge/v1/results`.
- TCExam selects the latest final attempt.
- TCExam provides stable `resultBatchId`.
