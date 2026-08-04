# TCExam Integration Guide

This guide explains how TCExam should integrate with Gibbon `juss-examBridge` in production-safe steps.

Current grade flows are:
- TCExam can push grades to Gibbon's grades upsert endpoint.
- Gibbon can manually pull TCExam results from the Write Internal Assessments screen.

The manual pull v1 target is Internal Assessment only. Markbook write-back is not part of the first pull implementation.

## 1) Prerequisites
Before TCExam starts jobs, ensure in Gibbon:
- `bridgeKeyId` is set.
- `bridgeSharedSecret` is set.
- `signatureMaxSkewSeconds` is agreed with TCExam clock behavior.
- `enrollmentSyncEnabled` is `Y` for enrollment pull use cases.
- `gradeSyncEnabled` is `Y` for grade pushes.
- `gradeSyncEnabled` is `Y` for manual TCExam results pulls.
- `dryRunEnabled` is set intentionally (`Y` for pilot, `N` for live writes).
- `bridgeServicePersonID` is configured to a service/admin account.

Mapping prerequisites for grades:
- Populate Person mappings.
- Populate Class mappings.
- Populate Assessment mappings.
- For manual pulls, the Assessment mapping must point to a Gibbon Internal Assessment column with `syncMode` of `internal_assessment` or `both`.

Admin pages:
- `index.php?q=/modules/juss-examBridge/mappings.php`
- `index.php?q=/modules/juss-examBridge/mapping_person.php`
- `index.php?q=/modules/juss-examBridge/mapping_class.php`
- `index.php?q=/modules/juss-examBridge/mapping_assessment.php`

## 2) Signing Requests
For every request:
1. Build request body string exactly as sent.
2. Compute `bodyHash = SHA256(body)`.
3. Build canonical string:
```text
METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + bodyHash
```
4. Compute `signature = HMAC_SHA256(canonical, bridgeSharedSecret)`.
5. Send required headers:
- `X-Bridge-KeyId`
- `X-Bridge-Timestamp`
- `X-Bridge-Nonce`
- `X-Bridge-Signature`

Critical details:
- Use UTC ISO-8601 timestamp.
- `PATH` must be the URL path only.
- Nonce must be unique per request.
- Do not retry with same nonce.

## 3) Endpoint Usage Pattern
Recommended schedule:
- Enrollment pull job: every 5 to 15 minutes.
- Grade push job: near real-time queue consumer with retries.
- Manual TCExam result pull: user-triggered only from the mapped Internal Assessment write screen.

Suggested flow:
1. Validate signature setup using `authProbe`.
2. Pull classes and enrollments from Gibbon.
3. Resolve and maintain TCExam internal mapping references.
4. Push grades with idempotent keys, or pull final TCExam results manually for a mapped Internal Assessment column.

## 4) Idempotency Key Strategy
Use deterministic unique keys per logical batch write.

Recommended format:
```text
grade-sync:{examId}:{classExternalId}:{studentExternalId}:{attemptOrVersion}
```

Rules:
- Retries for the same logical payload must reuse the same key and body.
- If payload changes materially, use a new key.
- Treat `409 idempotency_conflict` as a key reuse bug or payload drift.

## 5) Error Handling Contract
Common signed-request errors (`401`, `409`, `503`):
- Do not blind-retry until root cause is fixed.
- Typical causes: wrong secret, clock drift, path mismatch, nonce reuse.

Grades upsert handling:
1. `200` with accepted records: mark accepted records as successful and route rejected records by `results[].code`.
2. `200` with `idempotentReplay: true`: treat as success/no-op.
3. `409 idempotency_conflict`: stop retry loop for that key and escalate.
4. `409` with mapping conflict payload: send mapping remediation alert to admins.

Record-level operational actions:
1. `unmapped_exam`, `unmapped_class`, `unmapped_student`: create mapping task for Gibbon admin.
2. `student_not_enrolled`: validate enrollment timing and class membership.
3. `invalid_*` validation errors: fix TCExam payload generation.
4. `write_failed`: retry with backoff and alert if repeated.

## 6) Retry Policy
Recommended retry policy for transient failures (`5xx`, network):
- Retry 3 to 5 times.
- Exponential backoff (for example 5s, 20s, 60s, 180s).
- Jitter enabled.

Do not auto-retry indefinitely for:
- `400` contract errors
- `401` signature/auth errors
- `409` idempotency conflicts

## 7) Final Pre-Handoff Smoke Checklist
1. Create one mapping in each map table via UI.
2. Run signed classes script `modules/juss-examBridge/scripts/test_signed_classes_request.sh`.
3. Run signed grades script with `TEST_CASE=happy_path`.
4. Run signed grades script with `TEST_CASE=missing_records`, `TEST_CASE=invalid_percentage`, and `TEST_CASE=unmapped_student`.
5. Confirm response codes match the contract, grade writes (or dry-run behavior) are correct, sync log entries are created with correct statuses, and audit attribution uses the service account.

## 8) Hand-off Package for TCExam Team
Provide these files:
- `modules/juss-examBridge/API_CONTRACT.md`
- `modules/juss-examBridge/TCEXAM_PULL_CONTRACT.md`
- `modules/juss-examBridge/PULL_IMPLEMENTATION_PLAN.md`
- `modules/juss-examBridge/PULL_PHASES.md`
- `modules/juss-examBridge/openapi.yaml`
- `modules/juss-examBridge/postman_collection.json`
- `modules/juss-examBridge/TCEXAM_INTEGRATION_GUIDE.md`
- `modules/juss-examBridge/scripts/test_signed_classes_request.sh`
- `modules/juss-examBridge/scripts/test_signed_grades_upsert_request.sh`

Also provide environment-specific values out-of-band:
- Base URL
- `bridgeKeyId`
- `bridgeSharedSecret`
- expected module path prefix if hosted in subdirectory
