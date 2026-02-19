# ROADMAP: juss-examBridge (Gibbon <-> TCExam)

Date: 2026-02-19  
Status: Delivery roadmap (MVP + phase 2)

## Timeline
- Start: 2026-02-23
- MVP target: 2026-04-24 (9 weeks)

## Week 1 (2026-02-23 to 2026-02-27): Foundation
- [ ] Scaffold `modules/juss-examBridge` from the Gibbon starter module template.
- [ ] Replace starter placeholders (name, description, version, author, routes).
- [ ] Configure `manifest.php`, permissions, and navigation entries.
- [ ] Add module config page for:
  - [ ] TCExam API base URL
  - [ ] Key ID / shared secret
  - [ ] Feature flags
  - [ ] Dry-run toggle
- Deliverable: Installable module scaffold in Gibbon.

## Week 2 (2026-03-02 to 2026-03-06): Data Model and Security Core
- [ ] Add module tables:
  - [ ] `gibbonJussExamBridgePersonMap`
  - [ ] `gibbonJussExamBridgeClassMap`
  - [ ] `gibbonJussExamBridgeAssessmentMap`
  - [ ] `gibbonJussExamBridgeSyncLog`
- [ ] Implement HMAC request verification:
  - [ ] Timestamp skew checks
  - [ ] Nonce replay prevention
  - [ ] Canonical-signature validation
- [ ] Add migration/upgrade scripts and rollback notes.
- Deliverable: Secure request validation and migrations complete.

## Week 3 (2026-03-09 to 2026-03-13): Roster API
- [ ] Implement `GET /api/juss-exambridge/v1/classes` in Gibbon.
- [ ] Add filters: `schoolYearID`, `updatedAfter`, `classID`.
- [ ] Add pagination and response schema docs.
- [ ] Add tests for auth, filtering, and payload correctness.
- Deliverable: TCExam can pull classes and enrollment in dry-run.

## Week 4 (2026-03-16 to 2026-03-20): Enrollment Sync in TCExam
- [ ] Build scheduled TCExam job to pull roster from Gibbon.
- [ ] Upsert cohorts and participants in TCExam.
- [ ] Persist cross-system IDs in mapping tables.
- [ ] Add retry/backoff for transient failures.
- Deliverable: End-to-end enrollment sync for pilot classes.

## Week 5 (2026-03-23 to 2026-03-27): Grade Upsert API
- [ ] Implement `POST /api/juss-exambridge/v1/grades/upsert`.
- [ ] Add idempotency key handling:
  - [ ] Same key + same payload hash => no-op success
  - [ ] Same key + different hash => conflict
- [ ] Implement phase-1 write sink: `gibbonInternalAssessmentEntry`.
- [ ] Return per-record status and rejection codes.
- Deliverable: Safe grade write-back into Internal Assessment.

## Week 6 (2026-03-30 to 2026-04-03): Event + Retry Pipeline
- [ ] Trigger grade push from TCExam on exam publish/finalization.
- [ ] Add queue workers, retries, and dead-letter handling.
- [ ] Add correlation IDs across systems.
- [ ] Add structured logs for sync diagnostics.
- Deliverable: Reliable asynchronous delivery pipeline.

## Week 7 (2026-04-06 to 2026-04-10): Mapping and Admin UX
- [ ] Build admin pages in Gibbon for unresolved mappings:
  - [ ] Person mapping
  - [ ] Class/cohort mapping
  - [ ] Exam/assessment mapping
- [ ] Add manual remap and record retry actions.
- [ ] Add guardrails/validation to prevent incorrect mappings.
- Deliverable: Ops team can reconcile failures without DB edits.

## Week 8 (2026-04-13 to 2026-04-17): Pilot and Hardening
- [ ] Pilot with 1-2 classes.
- [ ] Track metrics:
  - [ ] Success rate
  - [ ] Mapping miss rate
  - [ ] Queue lag
  - [ ] Sync latency
- [ ] Fix edge cases and conversion defects.
- [ ] Complete security replay/tamper tests.
- Deliverable: Pilot sign-off and production readiness checklist.

## Week 9 (2026-04-20 to 2026-04-24): Production Rollout
- [ ] Enable feature flags in phases by cohort/department.
- [ ] Publish runbook and rollback procedure.
- [ ] Set up alerts for error spikes and queue backlog.
- [ ] Conduct post-launch review.
- Deliverable: MVP live in production (Internal Assessment sync).

## Phase 2 (Post-MVP, 3-4 weeks)
- [ ] Add optional Markbook write-back (`gibbonMarkbookEntry`) by configuration.
- [ ] Add teacher-level override rules for exam-to-assessment mapping.
- [ ] Expand sync health dashboard and reconciliation analytics.

## Definition of Done (MVP)
- [ ] Enrollment sync runs on schedule with low mapping miss rate (<1% target).
- [ ] Grade write-back is idempotent and fully auditable.
- [ ] Failed records are recoverable through UI-driven remap/retry.
- [ ] No Gibbon core-file modifications are required.

## Dependencies
- Gibbon starter template: `https://github.com/GibbonEdu/module-gibbonStarterModule`
- TCExam API availability for cohort/participant and result workflows.
- Service account credentials and secret rotation policy.
