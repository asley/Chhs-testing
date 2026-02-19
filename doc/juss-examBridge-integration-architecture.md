# juss-examBridge Integration Architecture Draft

Date: 2026-02-19  
Status: Draft v1

## 1. Objective
Build a dedicated Gibbon module, `juss-examBridge`, to integrate TCExam with Gibbon in an upgrade-safe way.

Primary goals:
- Keep Gibbon as source of truth for classes and enrollment.
- Sync roster and class membership into TCExam cohorts.
- Sync TCExam exam results back into Gibbon assessments.
- Avoid direct cross-database writes between systems.

## 2. Scope
In scope:
- New Gibbon module under `modules/juss-examBridge`.
- Service-to-service authenticated APIs between Gibbon and TCExam.
- Enrollment sync and grade write-back.
- Mapping tables for cross-system IDs.
- Observability, retry, and idempotent writes.

Out of scope (phase 1):
- Replacing teacher-facing grading UX in existing Gibbon modules.
- Real-time streaming; phase 1 uses near-real-time scheduled + event-driven sync.
- Backfill for historical exam data older than rollout date.

## 3. Assumptions
- TCExam exposes/accepts API calls for cohort and exam assignment workflows.
- TCExam API authentication uses Sanctum tokens.
- Gibbon currently writes Markbook and Internal Assessment via protected process scripts, not public APIs.
- Current analytics/reporting in this codebase already uses Internal Assessment data heavily.
- Student identity can be reliably matched with a deterministic rule (email-first with fallback mapping).

## 4. High-Level Design
Components:
- Gibbon module: `modules/juss-examBridge`
- TCExam API + background jobs
- Shared integration contract (JSON payloads + signature scheme)
- Integration mapping tables

Design decisions:
1. Start with Internal Assessment write-back as the default grade sink.
2. Add optional Markbook write-back in phase 2 for departments needing it.
3. Use API boundary on both systems; no direct DB access from the other platform.
4. Use idempotency keys for all grade upserts and membership sync operations.

## 5. Data Flow
### 5.1 Enrollment Sync (Gibbon -> TCExam)
1. Scheduler or manual trigger in TCExam requests class/roster from Gibbon API.
2. Gibbon returns class metadata + enrolled students.
3. TCExam upserts cohort, participants, and exam assignment eligibility.
4. Mapping records are created/updated (`gibbonCourseClassID <-> cohort_id`, `gibbonPersonID <-> user_id`).

### 5.2 Result Sync (TCExam -> Gibbon)
1. Exam submission finalization or grade publish event triggers TCExam job.
2. Job packages result payload (attempt, score, percentage, status, timestamp).
3. TCExam calls Gibbon `grades/upsert` endpoint with signed request and idempotency key.
4. Gibbon validates mapping, resolves target assessment, and writes/updates Internal Assessment entry.
5. Gibbon returns accepted/rejected records + reason codes.

### 5.3 Optional Markbook Sync (Phase 2)
1. If course/assessment mapping indicates Markbook target, module performs parallel write/upsert.
2. Conflicts are logged and surfaced to admin reconciliation UI.

## 6. API Contract (Draft)
### 6.1 Gibbon Endpoints (in `juss-examBridge`)
- `GET /api/juss-exambridge/v1/classes`
  - Filters: `schoolYearID`, `updatedAfter`, `classID` (optional)
  - Returns classes + participants with external identity hints
- `POST /api/juss-exambridge/v1/grades/upsert`
  - Body: batch of grade records
  - Required: `idempotencyKey`, `sourceSystem`, `examId`, `classExternalId`, `studentExternalId`
  - Score fields: `rawPoints`, `maxPoints`, `percentage`, `gradeStatus`, `gradedAt`

### 6.2 Authentication
- Request headers:
  - `X-Bridge-KeyId`
  - `X-Bridge-Timestamp` (UTC ISO-8601)
  - `X-Bridge-Nonce`
  - `X-Bridge-Signature` (HMAC-SHA256 over canonical request)
- Rejection rules:
  - Timestamp skew > 5 minutes
  - Reused nonce
  - Invalid signature

### 6.3 Error Model
- `200`: accepted; includes per-record status.
- `400`: schema/validation errors.
- `401/403`: auth/signature failures.
- `409`: mapping conflicts or duplicate business keys.
- `429`: rate limiting.
- `500`: transient server failure (caller should retry with backoff).

## 7. Data Model
Recommended tables in Gibbon module namespace:
- `gibbonJussExamBridgePersonMap`
  - `id`, `gibbonPersonID`, `externalUserId`, `externalEmail`, `status`, timestamps
- `gibbonJussExamBridgeClassMap`
  - `id`, `gibbonCourseClassID`, `externalCohortId`, `externalClassCode`, timestamps
- `gibbonJussExamBridgeAssessmentMap`
  - `id`, `externalExamId`, `gibbonInternalAssessmentColumnID` (or equivalent target), optional `gibbonMarkbookColumnID`, `syncMode`
- `gibbonJussExamBridgeSyncLog`
  - `id`, `direction`, `operationType`, `idempotencyKey`, `payloadHash`, `status`, `errorCode`, `errorDetail`, timestamps

Write targets:
- Phase 1: `gibbonInternalAssessmentEntry`
- Phase 2 (optional): `gibbonMarkbookEntry`

## 8. Identity and Mapping Strategy
Matching priority:
1. Existing explicit mapping in `PersonMap`.
2. Exact email match (normalized lowercase).
3. Deterministic fallback key if available (institution student number).
4. Otherwise reject with `UNMAPPED_STUDENT`.

Policy:
- Never auto-create core person records from TCExam.
- Unmapped users are queued for admin review in module UI.

## 9. Reliability and Idempotency
- All writes require `idempotencyKey`.
- Gibbon stores key + payload hash in sync log.
- Duplicate key with same hash => no-op success.
- Duplicate key with different hash => reject `IDEMPOTENCY_CONFLICT`.
- Retries: exponential backoff with jitter in TCExam jobs.
- Dead-letter queue for repeated failures.

## 10. Security Controls
- HMAC request signing + nonce replay protection.
- Optional IP allowlist between services.
- TLS required end-to-end.
- Least-privilege service accounts.
- Audit logs for every sync operation and manual remap action.
- No PII beyond required identity fields in logs.

## 11. Deployment and Rollout
Phase plan:
1. Ship module scaffolding + mapping tables + read-only health endpoints.
2. Enable enrollment sync in dry-run mode (no writes).
3. Enable grade sync for pilot class set (Internal Assessment only).
4. Expand to full cohort after error-rate threshold is stable.
5. Optional Markbook sync by configuration flag per class/subject.

Feature flags:
- `bridge.enrollment.enabled`
- `bridge.grades.enabled`
- `bridge.markbook.enabled`
- `bridge.dryRun`

Rollback:
- Disable feature flags (no code rollback required).
- Preserve logs and mappings for replay after fix.

## 12. Operational Metrics
Track:
- Sync batch success rate
- P95 and P99 sync latency
- Mapping miss rate
- Retry rate and dead-letter volume
- Duplicate idempotency collisions
- Grade write rejection reasons by code

Alerting:
- High error rate for `grades/upsert`
- Spike in `UNMAPPED_STUDENT` or `UNMAPPED_ASSESSMENT`
- Queue lag over threshold

## 13. Test Strategy
Required tests:
- Unit tests for signature validation and idempotency logic.
- Integration tests for enrollment and grade sync happy path.
- Failure-path tests for auth failure, mapping miss, duplicate keys, partial batch errors.
- Deterministic fixture-based tests for score conversion rules.
- Contract tests for payload schema compatibility.

Non-functional:
- Load test with representative exam publish burst.
- Security test for replay/tamper attempts.

## 14. Risks and Tradeoffs
Risks:
- Identity mismatches causing grade rejects.
- Differences in grade semantics between TCExam and Gibbon columns.
- Operational drift if mapping governance is weak.

Tradeoffs:
- Internal Assessment first gives fastest value and lower coupling.
- Markbook parity is deferred to reduce initial complexity and regression risk.
- API boundary adds implementation effort but improves maintainability and upgrade safety.

## 15. Open Questions
1. Should final grade sync trigger at attempt submission or exam publish only?
2. Is email sufficient for identity in all departments, or must student number be mandatory?
3. Which score should be canonical for write-back: raw, percentage, or normalized grade band?
4. What is the acceptable sync freshness SLA (for example, <5 minutes)?
5. Should teachers be able to manually override mapped exam-to-assessment targets in UI?

## 16. Next Build Steps
1. Scaffold `modules/juss-examBridge` with manifest, routes, permission controls, and config page.
2. Implement HMAC middleware and nonce store.
3. Add mapping table migrations and admin mapping UI.
4. Implement classes roster endpoint and grades upsert endpoint.
5. Build TCExam job pipeline for enrollment pull and grade push with retries.
6. Pilot with one course cohort and validate end-to-end reconciliation.
