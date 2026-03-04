# Smoke Test Evidence (2026-02-20)

Environment:
- Base URL: `http://127.0.0.1:8002` (temporary local PHP server)
- `bridgeKeyId`: `juss-exam-service-1`
- `dryRunEnabled`: restored to `Y` after test run

## 1) Auth Probe
- Command: signed `curl` request to `POST /modules/juss-examBridge/api/authProbe.php`
- Result: `200`, `ok=true`
- Header check: `X-Bridge-Version: 0.5.0`
- Status: PASS

## 2) Classes Roster
- Command: `./modules/juss-examBridge/scripts/test_signed_classes_request.sh`
- Result: `200`, `ok=true`, `pagination.total=1` (class filter `classID=667`)
- Header check: `X-Bridge-Version: 0.5.0`
- Status: PASS

## 3) Enrollments
- Command: signed `curl` request to `GET /modules/juss-examBridge/api/v1/enrollments.php?classID=667&personID=302&page=1&pageSize=10`
- Result: `200`, `ok=true`, `data[0].person.personId=302`
- Header check: `X-Bridge-Version: 0.5.0`
- Status: PASS

## 4) Grades Happy Path (Dry-Run)
- Command: `TEST_CASE=happy_path ./modules/juss-examBridge/scripts/test_signed_grades_upsert_request.sh`
- Inputs: `examId=smoke-exam-1599`, `classExternalId=smoke-cohort-667`, `studentExternalId=smoke-student-302`
- Result: `200`, `ok=true`, `summary.accepted=1`, `dryRun=true`, `results[0].mode=dry_run`
- Sync log check: `idempotencyKey=smoke-happy-20260220-1` stored with `status=accepted`
- Status: PASS

## 5) Grades Error Cases
- `unmapped_exam`: `409`, `results[0].code=unmapped_exam`
- `invalid_percentage`: `200`, `results[0].code=invalid_percentage`
- `idempotency_conflict`: first request `200`, second request with same key + different body `409`, `error=idempotency_conflict`
- Status: PASS

## 6) Audit Attribution (Write-Mode Verification)
- Temporary step: switched `dryRunEnabled` from `Y` to `N` for one request, then restored to `Y`.
- Write request result: `200`, `results[0].mode=write`, `results[0].action=updated`.
- DB check:
- `gibbonInternalAssessmentEntry` for `gibbonPersonIDStudent=302` and `gibbonInternalAssessmentColumnID=1599` has `gibbonPersonIDLastEdit=0000000001`.
- `organisationAdministrator=0000000001`.
- Conclusion: audit attribution matches configured fallback behavior.
- Status: PASS

## Overall
- Endpoint behavior matches `API_CONTRACT.md`.
- Error semantics align with documented status and codes.
- `gibbonJussExamBridgeSyncLog` captures accepted/rejected states with expected error categories.
- Module is ready for TCExam integration handoff from the Gibbon side.
