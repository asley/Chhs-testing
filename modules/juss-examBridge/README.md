# juss-examBridge

Week 1 to Week 5 scaffold for TCExam integration in Gibbon.

## Current capabilities
- Module installation metadata and permissions.
- Admin settings page for connection and feature flags.
- Landing page with masked configuration status.
- Week 2 mapping and sync support tables.
- HMAC signature verification utilities with timestamp skew + nonce replay protection.
- Signed probe endpoint: `modules/juss-examBridge/api/authProbe.php`.
- Week 3 roster endpoint: `modules/juss-examBridge/api/v1/classes.php`.
- Week 4 enrollment endpoint: `modules/juss-examBridge/api/v1/enrollments.php`.
- Week 5 grades upsert endpoint: `modules/juss-examBridge/api/v1/grades/upsert.php`.
- Mapping admin UI for person/class/assessment mapping maintenance.

## Integration docs
- API contract: `modules/juss-examBridge/API_CONTRACT.md`
- TCExam integration guide: `modules/juss-examBridge/TCEXAM_INTEGRATION_GUIDE.md`
- OpenAPI spec: `modules/juss-examBridge/openapi.yaml`
- Postman collection: `modules/juss-examBridge/postman_collection.json`
- Smoke test evidence: `modules/juss-examBridge/SMOKE_TEST_EVIDENCE.md`
- Setup checklist: `modules/juss-examBridge/SETUP_CHECKLIST.md`

## Phase boundary
Week 1 does not include:
- Queue/scheduler jobs
- Markbook write-back logic

## Settings
Scope: `juss-examBridge`

- `tcexamBaseUrl`
- `bridgeKeyId`
- `bridgeSharedSecret`
- `signatureMaxSkewSeconds`
- `bridgeServicePersonID`
- `enrollmentSyncEnabled`
- `gradeSyncEnabled`
- `dryRunEnabled`

## Signed probe test (Week 2)
Use `POST /modules/juss-examBridge/api/authProbe.php` with headers:

- `X-Bridge-KeyId`
- `X-Bridge-Timestamp` (ISO-8601 UTC)
- `X-Bridge-Nonce`
- `X-Bridge-Signature` (HMAC-SHA256 over canonical request)

Canonical request format:

`METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(BODY)`

## Classes API (Week 3)
Endpoint:

- `GET /modules/juss-examBridge/api/v1/classes.php`

Query params:

- `schoolYearID` (optional, integer)
- `classID` (optional, integer)
- `updatedAfter` (optional, date/time string)
- `page` (optional, default `1`)
- `pageSize` (optional, default `50`, max `200`)

Response:

- Class records with course/class metadata and student participants.
- Includes `classExternalId` for every class: mapped `externalCohortId` when available, otherwise stringified `classId`.
- Includes `classExternalIdSource` (`mapped` or `fallback_classId`) and `mappingStatus` (`mapped` or `unmapped`) for mapping visibility.
- Includes `externalClassCode` when class mapping exists.
- Pagination object with `page`, `pageSize`, `total`, `totalPages`.

## Enrollments API (Week 4)
Endpoint:

- `GET /modules/juss-examBridge/api/v1/enrollments.php`

Query params:

- `schoolYearID` (optional, integer)
- `classID` (optional, integer)
- `personID` (optional, integer)
- `updatedAfter` (optional, date/time string)
- `page` (optional, default `1`)
- `pageSize` (optional, default `50`, max `200`)

Response:

- Enrollment records with class, course and student identity details.
- Includes `classExternalId` for every enrollment: mapped `externalCohortId` when available, otherwise stringified `classId`.
- Includes `classExternalIdSource` (`mapped` or `fallback_classId`) and `mappingStatus` (`mapped` or `unmapped`) for mapping visibility.
- Includes `externalClassCode` when class mapping exists.
- Pagination object with `page`, `pageSize`, `total`, `totalPages`.

## Grades Upsert API (Week 5)
Endpoint:

- `POST /modules/juss-examBridge/api/v1/grades/upsert.php`

Required JSON fields:

- `idempotencyKey` (request-level, max 128 chars)
- `sourceSystem`
- `records[]` (1-200 records) with:
- `examId`
- `classExternalId`
- `studentExternalId`
- `rawPoints`
- `maxPoints`
- `percentage` (or computed from raw/max if omitted)
- `gradeStatus`
- `gradedAt`

Behavior:

- Signed request verification.
- Idempotency check against `gibbonJussExamBridgeSyncLog`.
- Mapping checks for exam/class/student.
- Internal Assessment upsert to `gibbonInternalAssessmentEntry`.
- Dry-run support via `dryRunEnabled=Y`.
- Uses `bridgeServicePersonID` (or System `organisationAdministrator`) for `gibbonPersonIDLastEdit`.
- Per-record accepted/rejected result list.

Security notes:

- The settings page never renders the stored `bridgeSharedSecret` value. Leaving the field blank preserves the existing secret; entering a value rotates it.
- For deployments that should avoid storing the HMAC secret in `gibbonSetting`, define `JUSS_EXAM_BRIDGE_SHARED_SECRET` in server configuration or as an environment variable. That value takes precedence over the database setting.

## Mapping Admin UI
- `index.php?q=/modules/juss-examBridge/mappings.php`
- `index.php?q=/modules/juss-examBridge/mapping_person.php`
- `index.php?q=/modules/juss-examBridge/mapping_class.php`
- `index.php?q=/modules/juss-examBridge/mapping_assessment.php`

### Quick Signed Test Script
Script path:

- `modules/juss-examBridge/scripts/test_signed_classes_request.sh`
- `modules/juss-examBridge/scripts/test_signed_grades_upsert_request.sh`

Run:

```bash
BASE_URL="http://localhost" \
BRIDGE_KEY_ID="replace-with-bridgeKeyId" \
BRIDGE_SHARED_SECRET="replace-with-bridgeSharedSecret" \
./modules/juss-examBridge/scripts/test_signed_classes_request.sh
```

Grades upsert smoke test (happy path):

```bash
BASE_URL="http://localhost" \
BRIDGE_KEY_ID="replace-with-bridgeKeyId" \
BRIDGE_SHARED_SECRET="replace-with-bridgeSharedSecret" \
TEST_CASE="happy_path" \
IDEMPOTENCY_KEY="grade-sync-$(date +%s)" \
EXAM_ID="exam-001" \
CLASS_EXTERNAL_ID="cohort-001" \
STUDENT_EXTERNAL_ID="student-001" \
./modules/juss-examBridge/scripts/test_signed_grades_upsert_request.sh
```

Grades upsert rejection smoke tests:

```bash
# List supported test cases
TEST_CASE="list" ./modules/juss-examBridge/scripts/test_signed_grades_upsert_request.sh

# Example rejection checks
BASE_URL="http://localhost" \
BRIDGE_KEY_ID="replace-with-bridgeKeyId" \
BRIDGE_SHARED_SECRET="replace-with-bridgeSharedSecret" \
TEST_CASE="missing_records" \
IDEMPOTENCY_KEY="grade-sync-neg-$(date +%s)" \
./modules/juss-examBridge/scripts/test_signed_grades_upsert_request.sh

BASE_URL="http://localhost" \
BRIDGE_KEY_ID="replace-with-bridgeKeyId" \
BRIDGE_SHARED_SECRET="replace-with-bridgeSharedSecret" \
TEST_CASE="invalid_percentage" \
IDEMPOTENCY_KEY="grade-sync-neg2-$(date +%s)" \
./modules/juss-examBridge/scripts/test_signed_grades_upsert_request.sh
```

Optional filters:

```bash
BASE_URL="http://localhost" \
BRIDGE_KEY_ID="replace-with-bridgeKeyId" \
BRIDGE_SHARED_SECRET="replace-with-bridgeSharedSecret" \
SCHOOL_YEAR_ID="027" \
CLASS_ID="00001234" \
UPDATED_AFTER="2026-02-01T00:00:00Z" \
PAGE="1" \
PAGE_SIZE="50" \
./modules/juss-examBridge/scripts/test_signed_classes_request.sh
```
