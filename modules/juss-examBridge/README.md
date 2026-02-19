# juss-examBridge

Week 1 and Week 2 scaffold for TCExam integration in Gibbon.

## Current capabilities
- Module installation metadata and permissions.
- Admin settings page for connection and feature flags.
- Landing page with masked configuration status.
- Week 2 mapping and sync support tables.
- HMAC signature verification utilities with timestamp skew + nonce replay protection.
- Signed probe endpoint: `modules/juss-examBridge/api/authProbe.php`.

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
- `signatureMaxSkewSeconds`
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
