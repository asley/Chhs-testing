# TCExam Results Pull Contract (Draft)

This document defines the outbound contract for Gibbon `juss-examBridge` to pull TCExam results and write them to Gibbon Internal Assessments.

Status: Gibbon-side manual pull implementation exists, and TCExam-Modern exposes the endpoint below with the settled response fields and pagination support. Full completion still requires environment-specific smoke testing with real mapped data.

Pull v1 target: Internal Assessment only. Markbook write-back is reserved for a later phase and requires a separate design.

## Scope
- Manual "Sync from TCExam" action in Gibbon.
- Gibbon signs outbound requests to TCExam with the existing bridge HMAC credentials.
- TCExam returns result records for one mapped exam and one mapped class/cohort.
- Gibbon normalizes returned records into the same grade-record shape used by `POST /modules/juss-examBridge/api/v1/grades/upsert.php`.
- Gibbon writes or dry-runs Internal Assessment entries using the existing mapping and validation rules.

Out of scope for the first implementation:
- Scheduled/background pulls.
- Markbook write-back.
- Creating mappings automatically.
- Persisting raw TCExam response bodies.

## Direction
Direction: Gibbon to TCExam.

The Gibbon admin action resolves:
- `examId` from `gibbonJussExamBridgeAssessmentMap.externalExamId`.
- `classExternalId` from `gibbonJussExamBridgeClassMap.externalCohortId`.
- `gibbonInternalAssessmentColumnID` from the current Internal Assessment column.

Gibbon then requests matching results from TCExam and applies them to the selected Internal Assessment column.

## TCExam Endpoint
Endpoint:
- `GET /api/bridge/v1/results`

Required query params:
- `examId` string. TCExam exam identifier mapped in Gibbon.
- `classExternalId` string. TCExam cohort/class identifier mapped in Gibbon.

Optional query params:
- `status` string, default `final`. Recommended values: `final`, `draft`, `all`.
- `updatedAfter` UTC ISO-8601 date/time. Return results updated after this timestamp.
- `page` integer, default `1`.
- `pageSize` integer, default `100`, max `200`.

Example:
```text
GET /api/bridge/v1/results?examId=exam-001&classExternalId=cohort-001&status=final&page=1&pageSize=100
```

## Authentication
Use the same HMAC-SHA256 scheme as Gibbon's inbound APIs, with Gibbon as signer and TCExam as verifier.

Required headers:
- `X-Bridge-KeyId`
- `X-Bridge-Timestamp` UTC ISO-8601, for example `2026-02-20T12:30:00Z`
- `X-Bridge-Nonce` unique random value per request
- `X-Bridge-Signature` hex HMAC-SHA256

Canonical request format:
```text
METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(BODY)
```

For `GET` requests, `BODY` is the empty string and `SHA256(BODY)` is the SHA-256 hash of an empty string.

Rules:
- `PATH` is the URL path only, excluding scheme, host, query string, and fragment.
- TCExam must reject reused nonces within the agreed timestamp skew window.
- TCExam must validate clock skew using the same `signatureMaxSkewSeconds` agreed for inbound Gibbon APIs.
- Requests should use HTTPS in non-local environments.

## Success Response
Status: `200`

```json
{
  "ok": true,
  "sourceSystem": "tcexam",
  "examId": "exam-001",
  "classExternalId": "cohort-001",
  "resultBatchId": "exam-001:cohort-001:20260220T123000Z",
  "generatedAt": "2026-02-20T12:30:00Z",
  "pagination": {
    "page": 1,
    "pageSize": 100,
    "total": 1,
    "totalPages": 1
  },
  "results": [
    {
      "resultId": "result-9001",
      "attemptId": "attempt-7001",
      "studentExternalId": "student-001",
      "rawPoints": 42,
      "maxPoints": 50,
      "percentage": 84,
      "gradeStatus": "final",
      "gradedAt": "2026-02-20T12:00:00Z",
      "updatedAt": "2026-02-20T12:05:00Z",
      "externalEmail": "optional@student.com",
      "studentNumber": "optional-student-id"
    }
  ]
}
```

Required top-level fields:
- `ok`
- `sourceSystem`
- `examId`
- `classExternalId`
- `generatedAt`
- `pagination`
- `results`

Required result fields:
- `studentExternalId`
- `rawPoints`
- `maxPoints`
- `gradeStatus`
- `gradedAt`

Strongly recommended result fields:
- `resultId`
- `attemptId`
- `percentage`
- `updatedAt`

Optional result fields:
- `externalEmail`
- `studentNumber`

## Field Semantics
- `resultBatchId`: Stable identifier for the full TCExam result snapshot, not just the current page. Gibbon uses this for sync-log idempotency across paginated pulls.
- `resultId`: Stable TCExam result row identifier.
- `attemptId`: Stable TCExam attempt identifier. Required if one student can have multiple attempts for the same exam.
- `studentExternalId`: Must match `gibbonJussExamBridgePersonMap.externalUserId`.
- `rawPoints`: Numeric score earned.
- `maxPoints`: Numeric maximum score. Must be greater than `0` when `percentage` is omitted.
- `percentage`: Numeric percentage from `0` to `100`. If omitted or null, Gibbon computes it from `rawPoints / maxPoints`.
- `gradeStatus`: Recommended values: `draft`, `final`, `void`, `superseded`.
- `gradedAt`: Date/time used in the Gibbon grade comment/audit note.
- `updatedAt`: Date/time TCExam last changed this result.

Gibbon pull v1 writes only records with `gradeStatus` equal to `final`. Other statuses should be rejected or skipped with a record-level result code.

## Attempt Selection Requirement
Attempt selection determines which score Gibbon writes when TCExam has multiple attempts for the same student, exam, and class.

If TCExam can return more than one result or attempt for the same `examId`, `classExternalId`, and `studentExternalId`, TCExam must either:
- return exactly one result using an agreed rule, or
- return enough metadata for Gibbon to apply an agreed rule.

Accepted v1 rule:
- latest final attempt

TCExam should return one selected result per student using this rule. Gibbon should not choose between multiple attempts in v1.

## Error Responses
Common TCExam error shape:
```json
{
  "ok": false,
  "error": "error_code",
  "message": "Human-readable diagnostic safe for admin display."
}
```

Expected errors:
- `400` `invalid_exam_id`
- `400` `invalid_class_external_id`
- `400` `invalid_status`
- `400` `invalid_updated_after`
- `400` `invalid_pagination`
- `401` `missing_headers`
- `401` `invalid_key_id`
- `401` `invalid_timestamp`
- `401` `timestamp_skew`
- `401` `invalid_signature`
- `401` `path_mismatch`
- `409` `nonce_replay`
- `404` `exam_not_found`
- `404` `class_not_found`
- `429` `rate_limited`
- `500` `query_failed`
- `503` `service_unavailable`

Gibbon handling:
- `400`: Do not retry automatically. Show admin-facing configuration/contract error.
- `401` or `409 nonce_replay`: Do not retry automatically. Show signing/clock/credential error.
- `401 path_mismatch`: Show TCExam route/proxy path mismatch. This should be diagnosed separately from `invalid_signature`.
- `404`: Show mapping mismatch or TCExam setup error.
- `429`, `500`, `503`, timeout: Retry only if an explicit retry action is implemented; otherwise show transient failure.

## Gibbon Normalized Record Shape
Gibbon converts each TCExam result into:

```json
{
  "examId": "exam-001",
  "classExternalId": "cohort-001",
  "studentExternalId": "student-001",
  "rawPoints": 42,
  "maxPoints": 50,
  "percentage": 84,
  "gradeStatus": "final",
  "gradedAt": "2026-02-20T12:00:00Z",
  "externalEmail": "optional@student.com",
  "studentNumber": "optional-student-id"
}
```

This is intentionally compatible with the existing inbound grades upsert record contract.

## Gibbon Sync Log
Manual pulls should write `gibbonJussExamBridgeSyncLog` entries.

Recommended values:
- `direction`: `outbound`
- `operationType`: `grades_pull`
- `idempotencyKey`: `pull:{targetHash}:{resultBatchHash}`
- `payloadHash`: SHA-256 hash of the normalized records payload or TCExam response metadata
- `status`: `accepted` when at least one record is accepted, otherwise `rejected`

TCExam must provide a stable `resultBatchId` for the returned result snapshot. This allows Gibbon to build a stable pull idempotency key for repeat pulls of the same TCExam result set.

`targetHash` is the first 16 hex characters of SHA-256 over `examId`, `classExternalId`, and `gibbonInternalAssessmentColumnID`. `resultBatchHash` is SHA-256 over TCExam's stable `resultBatchId`. This avoids storing raw exam/class identifiers in the idempotency key while preserving retry stability.

## Gibbon Write Result Shape
After applying normalized records, Gibbon should produce the same shape as the inbound grades upsert response:

```json
{
  "ok": true,
  "idempotencyKey": "pull:6d6220d5f8b3b937:8a8ac6c2b83e8a9f0f3a2b3fd0d9dd7705e15f1aa0e38c4da9a61f8afc1c2a11",
  "sourceSystem": "tcexam",
  "dryRun": false,
  "summary": {
    "total": 1,
    "accepted": 1,
    "rejected": 0
  },
  "results": [
    {
      "index": 1,
      "status": "accepted",
      "mode": "write",
      "action": "updated",
      "gibbonPersonID": 3001,
      "gibbonInternalAssessmentColumnID": 1001,
      "attainmentValue": "84.00"
    }
  ]
}
```

## Mapping Dependencies
Pull writes require:
- `gibbonJussExamBridgeAssessmentMap.externalExamId` for the selected Internal Assessment column.
- `gibbonJussExamBridgeAssessmentMap.syncMode` set to `internal_assessment`.
- `gibbonJussExamBridgeClassMap.externalCohortId` for the selected class.
- `gibbonJussExamBridgePersonMap.externalUserId` for each student result.
- Student enrollment in the selected Gibbon class.

Records missing required mappings should use the existing grade-write rejection codes where possible:
- `unmapped_exam`
- `unmapped_class`
- `assessment_class_mismatch`
- `unmapped_student`
- `student_not_enrolled`

## Operational Limits
Recommended first implementation limits:
- Manual trigger only.
- Maximum `pageSize` 200.
- Maximum 200 normalized records applied per request.
- HTTP timeout 10 seconds.
- Reject non-HTTPS `tcexamBaseUrl` except localhost/private development hosts.
- Do not log raw result payloads or student PII.
- Prevent duplicate manual submission for the same exam/class/column with a DB-backed debounce check against recent `direction='outbound'` and `operationType='grades_pull'` sync-log rows.

## Open Questions
These must be settled before implementation:
- Whether `attemptId` or `resultId` is guaranteed for every returned result.
- Whether TCExam returns only final results by default.
- Whether TCExam supports pagination consistently for large classes.
- Whether Gibbon should display skipped `draft`, `void`, or `superseded` results in the admin banner.
