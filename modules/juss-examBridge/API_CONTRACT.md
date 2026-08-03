# juss-examBridge API Contract (v0.5.1)

This document is the integration contract for TCExam to consume and push data with the Gibbon `juss-examBridge` module.

Machine-readable spec:
- `modules/juss-examBridge/openapi.yaml`
- `modules/juss-examBridge/postman_collection.json`

## Scope
- Signed API authentication (HMAC-SHA256)
- Classes roster pull
- Enrollment pull
- Grades upsert push (idempotent)

## Base URL
- Example: `http://localhost`
- Endpoint paths are absolute and include the Gibbon module path, for example:
- `/modules/juss-examBridge/api/v1/classes.php`

## Authentication
All endpoints require signed requests.

Required headers:
- `X-Bridge-KeyId`
- `X-Bridge-Timestamp` (UTC ISO-8601, for example `2026-02-20T12:30:00Z`)
- `X-Bridge-Nonce` (unique random value per request)
- `X-Bridge-Signature` (hex HMAC-SHA256)

Canonical request format:
```text
METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(BODY)
```

Signature:
```text
HMAC_SHA256(canonical_request, bridgeSharedSecret)
```

Validation notes:
- `PATH` must exactly match the request path used by Gibbon.
- Nonce replay is rejected.
- Timestamp skew is validated against `signatureMaxSkewSeconds` (minimum effective window 30 seconds).

## Common Error Responses
Applies to all signed endpoints.

Response shape:
```json
{
  "ok": false,
  "error": "error_code"
}
```

Response header:
- `X-Bridge-Version` (module version, for example `0.5.0`)

Status and error codes:
- `401` `missing_headers`
- `503` `bridge_not_configured`
- `401` `invalid_key_id`
- `401` `invalid_timestamp`
- `401` `timestamp_skew`
- `401` `invalid_signature`
- `401` `path_mismatch`
- `409` `nonce_replay`
- `405` `method_not_allowed`

---

## 1) Auth Probe
Endpoint:
- `POST /modules/juss-examBridge/api/authProbe.php`

Purpose:
- Validate signature setup before integrating business endpoints.

Success `200`:
```json
{
  "ok": true,
  "message": "Signed request accepted.",
  "serverTime": "2026-02-20T16:00:00Z"
}
```

---

## 2) Classes API
Endpoint:
- `GET /modules/juss-examBridge/api/v1/classes.php`

Query params:
- `schoolYearID` optional integer
- `classID` optional integer
- `updatedAfter` optional date/time string
- `page` optional integer, default `1`
- `pageSize` optional integer, default `50`, max `200`

Validation errors:
- `400` `invalid_updated_after`

Server errors:
- `500` `query_failed`
- `500` `participant_query_failed`

Success `200` shape:
```json
{
  "ok": true,
  "filters": {
    "schoolYearID": 27,
    "classID": null,
    "updatedAfter": "2026-02-01"
  },
  "pagination": {
    "page": 1,
    "pageSize": 50,
    "total": 1,
    "totalPages": 1
  },
  "data": [
    {
      "classId": 5001,
      "classExternalId": "cohort-001",
      "classExternalIdSource": "mapped",
      "mappingStatus": "mapped",
      "externalClassCode": "MATH-10A",
      "schoolYearId": 27,
      "course": {
        "courseId": 7001,
        "name": "Mathematics",
        "code": "MATH-10"
      },
      "class": {
        "name": "10A",
        "code": "10A",
        "reportable": "Y",
        "attendance": "Y",
        "enrolmentMin": 0,
        "enrolmentMax": 40
      },
      "participants": []
    }
  ]
}
```

Notes:
- `classExternalId` is populated from Bridge Class Mapping `externalCohortId` when available.
- If mapping is missing, `classExternalId` falls back to stringified `classId`.
- `classExternalIdSource` indicates whether `classExternalId` is `mapped` or `fallback_classId`.
- `mappingStatus` indicates class mapping state (`mapped` or `unmapped`).
- `externalClassCode` is populated from Bridge Class Mapping `externalClassCode` when available.
- `externalClassCode` may be `null` if class mapping has not been created yet.

---

## 3) Enrollments API
Endpoint:
- `GET /modules/juss-examBridge/api/v1/enrollments.php`

Query params:
- `schoolYearID` optional integer
- `classID` optional integer
- `personID` optional integer
- `updatedAfter` optional date/time string
- `page` optional integer, default `1`
- `pageSize` optional integer, default `50`, max `200`

Validation errors:
- `400` `invalid_updated_after`

Server errors:
- `500` `query_failed`

Success `200` shape:
```json
{
  "ok": true,
  "filters": {
    "schoolYearID": 27,
    "classID": 5001,
    "personID": null,
    "updatedAfter": "2026-02-01"
  },
  "pagination": {
    "page": 1,
    "pageSize": 50,
    "total": 1,
    "totalPages": 1
  },
  "data": [
    {
      "classId": 5001,
      "classExternalId": "cohort-001",
      "classExternalIdSource": "mapped",
      "mappingStatus": "mapped",
      "externalClassCode": "MATH-10A",
      "schoolYearId": 27,
      "course": {
        "courseId": 7001,
        "name": "Mathematics",
        "code": "MATH-10"
      },
      "class": {
        "name": "10A",
        "code": "10A"
      },
      "person": {
        "personId": 3001,
        "status": "Full",
        "name": {
          "firstName": "Student",
          "surname": "One",
          "preferredName": "Stu"
        },
        "identity": {
          "studentId": "S-3001",
          "email": "student@example.com",
          "username": "student1",
          "canLogin": "Y"
        }
      },
      "enrollment": {
        "role": "Student",
        "reportable": "Y",
        "dateEnrolled": "2026-01-01",
        "dateUnenrolled": null
      }
    }
  ]
}
```

Notes:
- `classExternalId` is populated from Bridge Class Mapping `externalCohortId` when available.
- If mapping is missing, `classExternalId` falls back to stringified `classId`.
- `classExternalIdSource` indicates whether `classExternalId` is `mapped` or `fallback_classId`.
- `mappingStatus` indicates class mapping state (`mapped` or `unmapped`).
- `externalClassCode` is populated from Bridge Class Mapping `externalClassCode` when available.
- `externalClassCode` may be `null` if class mapping has not been created yet.

---

## 4) Grades Upsert API
Endpoint:
- `POST /modules/juss-examBridge/api/v1/grades/upsert.php`

Request body:
```json
{
  "idempotencyKey": "grade-sync-20260220-0001",
  "sourceSystem": "tcexam",
  "records": [
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
  ]
}
```

Top-level validation errors:
- `403` `grade_sync_disabled`
- `400` `invalid_json`
- `400` `invalid_idempotency_key`
- `400` `missing_source_system`
- `400` `missing_records`
- `400` `too_many_records` (maximum 200 records per request)
- `409` `idempotency_conflict`
- `500` `sync_log_query_failed`
- `500` `sync_log_insert_failed`
- `500` `sync_log_update_failed`

Idempotency behavior:
1. Same `idempotencyKey` with identical request body hash returns `200` with `idempotentReplay: true`.
2. Same `idempotencyKey` with different request body hash returns `409` with `error: idempotency_conflict`.
3. Oversized requests are rejected before the idempotency log is written.

Success or mixed result `200` shape:
```json
{
  "ok": true,
  "idempotencyKey": "grade-sync-20260220-0001",
  "sourceSystem": "tcexam",
  "dryRun": true,
  "summary": {
    "total": 1,
    "accepted": 1,
    "rejected": 0
  },
  "results": [
    {
      "index": 1,
      "status": "accepted",
      "mode": "dry_run",
      "gibbonPersonID": 3001,
      "gibbonInternalAssessmentColumnID": 1001,
      "attainmentValue": "84.00"
    }
  ]
}
```

All records rejected due to mapping conflicts:
- HTTP status `409`
- Payload still includes `summary` and `results`

Record-level rejection codes (`results[].code`):
- `invalid_record`
- `missing_required_fields`
- `invalid_score_fields`
- `invalid_graded_at`
- `invalid_percentage`
- `unmapped_exam`
- `unsupported_sync_mode`
- `unmapped_class`
- `assessment_class_mismatch`
- `unmapped_student`
- `student_not_enrolled`
- `write_failed`

Accepted record modes:
- `dry_run`
- `write` (`action` is `inserted` or `updated`)

## Mapping Dependencies
Grades upsert requires mappings in:
- `gibbonJussExamBridgeAssessmentMap`
- `gibbonJussExamBridgeClassMap`
- `gibbonJussExamBridgePersonMap`

If mappings are missing, records will reject with mapping-related codes.

## Audit Attribution
Writes to `gibbonInternalAssessmentEntry.gibbonPersonIDLastEdit` use:
- `bridgeServicePersonID` setting when valid
- otherwise System `organisationAdministrator`
- otherwise fallback account selection
