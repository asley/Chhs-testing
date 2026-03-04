#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   BASE_URL="http://localhost" \
#   BRIDGE_KEY_ID="your-key-id" \
#   BRIDGE_SHARED_SECRET="your-secret" \
#   ./modules/juss-examBridge/scripts/test_signed_classes_request.sh
#
# Optional env vars:
#   ENDPOINT_PATH="/modules/juss-examBridge/api/v1/classes.php"
#   SCHOOL_YEAR_ID="27"
#   CLASS_ID="10001234"
#   UPDATED_AFTER="2026-02-01T00:00:00Z"
#   PAGE="1"
#   PAGE_SIZE="50"

BASE_URL="${BASE_URL:-http://localhost}"
ENDPOINT_PATH="${ENDPOINT_PATH:-/modules/juss-examBridge/api/v1/classes.php}"
BRIDGE_KEY_ID="${BRIDGE_KEY_ID:-}"
BRIDGE_SHARED_SECRET="${BRIDGE_SHARED_SECRET:-}"
SCHOOL_YEAR_ID="${SCHOOL_YEAR_ID:-}"
CLASS_ID="${CLASS_ID:-}"
UPDATED_AFTER="${UPDATED_AFTER:-}"
PAGE="${PAGE:-1}"
PAGE_SIZE="${PAGE_SIZE:-50}"

if [[ -z "${BRIDGE_KEY_ID}" || -z "${BRIDGE_SHARED_SECRET}" ]]; then
  echo "Error: BRIDGE_KEY_ID and BRIDGE_SHARED_SECRET are required." >&2
  exit 1
fi

QUERY="page=${PAGE}&pageSize=${PAGE_SIZE}"
if [[ -n "${SCHOOL_YEAR_ID}" ]]; then
  QUERY="${QUERY}&schoolYearID=${SCHOOL_YEAR_ID}"
fi
if [[ -n "${CLASS_ID}" ]]; then
  QUERY="${QUERY}&classID=${CLASS_ID}"
fi
if [[ -n "${UPDATED_AFTER}" ]]; then
  QUERY="${QUERY}&updatedAfter=${UPDATED_AFTER}"
fi

TIMESTAMP="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
NONCE="$(openssl rand -hex 16)"
BODY=""
BODY_HASH="$(printf "%s" "${BODY}" | openssl dgst -sha256 | awk '{print $2}')"
BASE_PATH="$(printf "%s" "${BASE_URL}" | sed -E 's#^[a-zA-Z]+://[^/]+##')"
if [[ -z "${BASE_PATH}" ]]; then
  BASE_PATH="/"
fi

CANONICAL_PATH="${ENDPOINT_PATH}"
if [[ "${BASE_PATH}" != "/" && "${ENDPOINT_PATH}" != "${BASE_PATH}"* ]]; then
  CANONICAL_PATH="${BASE_PATH}${ENDPOINT_PATH}"
fi

CANONICAL="GET
${CANONICAL_PATH}
${TIMESTAMP}
${NONCE}
${BODY_HASH}"
SIGNATURE="$(printf "%s" "${CANONICAL}" | openssl dgst -sha256 -hmac "${BRIDGE_SHARED_SECRET}" | awk '{print $2}')"

FULL_URL="${BASE_URL}${ENDPOINT_PATH}?${QUERY}"

echo "Request URL: ${FULL_URL}"
echo "Timestamp: ${TIMESTAMP}"
echo "Nonce: ${NONCE}"
echo "Signature: ${SIGNATURE}"
echo

curl -sS -X GET "${FULL_URL}" \
  -H "X-Bridge-KeyId: ${BRIDGE_KEY_ID}" \
  -H "X-Bridge-Timestamp: ${TIMESTAMP}" \
  -H "X-Bridge-Nonce: ${NONCE}" \
  -H "X-Bridge-Signature: ${SIGNATURE}" \
  -H "Accept: application/json"
echo
