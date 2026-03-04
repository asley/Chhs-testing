# Setup Checklist (Pre-Integration)

Use this checklist before handing credentials and endpoints to the TCExam team.

## 1) Configuration
- Set `bridgeKeyId`.
- Set `bridgeSharedSecret`.
- Set `signatureMaxSkewSeconds` (agreed with TCExam clock tolerance).
- Set `bridgeServicePersonID` (or confirm `System.organisationAdministrator` fallback).
- Set `gradeSyncEnabled=Y`.
- Set `dryRunEnabled=Y` for initial pilot (switch to `N` only for verified live writes).
- Set `enrollmentSyncEnabled=Y` if TCExam will pull enrollments.

## 2) Mapping Readiness
- Person mapping exists for target students in `gibbonJussExamBridgePersonMap`.
- Class mapping exists for target cohorts in `gibbonJussExamBridgeClassMap`.
- Assessment mapping exists for target exams in `gibbonJussExamBridgeAssessmentMap`.
- Assessment map `syncMode` supports Internal Assessment writes (`internal_assessment` or `both`).
- Assessment column belongs to the mapped class.
- Target students are enrolled in mapped classes.

## 3) API Artifacts to Share with TCExam
- `modules/juss-examBridge/API_CONTRACT.md`
- `modules/juss-examBridge/openapi.yaml`
- `modules/juss-examBridge/postman_collection.json`
- `modules/juss-examBridge/TCEXAM_INTEGRATION_GUIDE.md`
- `modules/juss-examBridge/SMOKE_TEST_EVIDENCE.md`

## 4) Credentials and Endpoints (Secure Channel Only)
- Base URL (environment-specific).
- `bridgeKeyId`.
- `bridgeSharedSecret`.
- Module path prefix if hosted in a subdirectory.
- Clock sync expectation (UTC ISO-8601 timestamps).

## 5) Validation Before Handoff
- Run `authProbe` signed call and confirm `200`.
- Run classes and enrollments pulls and confirm `200`.
- Run grades happy path in dry-run and confirm accepted record(s).
- Run negative cases (`unmapped_exam`, `invalid_percentage`, `idempotency_conflict`) and confirm expected responses.
- Confirm `X-Bridge-Version` header is present.
- Confirm sync-log entries are created with expected status/error.

## 6) Production Readiness Controls
- Rotate `bridgeSharedSecret` on a defined schedule.
- Restrict credential sharing to secure channels only.
- Monitor growth of `gibbonJussExamBridgeSyncLog`; plan retention/pruning (for example, >90 days) in a follow-up release.
