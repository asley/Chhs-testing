# juss-examBridge

Week 1 scaffold for TCExam integration in Gibbon.

## Current capabilities
- Module installation metadata and permissions.
- Admin settings page for connection and feature flags.
- Landing page with masked configuration status.

## Phase boundary
Week 1 does not include:
- Roster sync endpoints
- Grade upsert endpoints
- Queue/scheduler jobs
- Assessment write-back logic

## Settings
Scope: `juss-examBridge`

- `tcexamBaseUrl`
- `bridgeKeyId`
- `bridgeSharedSecret`
- `enrollmentSyncEnabled`
- `gradeSyncEnabled`
- `dryRunEnabled`
