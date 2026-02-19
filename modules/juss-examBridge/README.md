# juss-examBridge

Week 1 and Week 2 scaffold for TCExam integration in Gibbon.

## Current capabilities
- Module installation metadata and permissions.
- Admin settings page for connection and feature flags.
- Landing page with masked configuration status.
- Week 2 mapping and sync support tables.
- HMAC signature verification utilities with timestamp skew + nonce replay protection.
- Signed probe endpoint: `modules/juss-examBridge/api/authProbe.php`.
- Week 3 roster endpoint: `modules/juss-examBridge/api/v1/classes.php`.

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
- Pagination object with `page`, `pageSize`, `total`, `totalPages`.

### Quick Signed Test Script
Script path:

- `modules/juss-examBridge/scripts/test_signed_classes_request.sh`

Run:

```bash
BASE_URL="http://localhost" \
BRIDGE_KEY_ID="replace-with-bridgeKeyId" \
BRIDGE_SHARED_SECRET="replace-with-bridgeSharedSecret" \
./modules/juss-examBridge/scripts/test_signed_classes_request.sh
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
