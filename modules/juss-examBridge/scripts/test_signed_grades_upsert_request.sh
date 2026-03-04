#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   BASE_URL="http://localhost" \
#   BRIDGE_KEY_ID="your-key-id" \
#   BRIDGE_SHARED_SECRET="your-secret" \
#   TEST_CASE="happy_path" \
#   IDEMPOTENCY_KEY="grade-sync-001" \
#   EXAM_ID="exam-001" \
#   CLASS_EXTERNAL_ID="cohort-001" \
#   STUDENT_EXTERNAL_ID="student-001" \
#   ./modules/juss-examBridge/scripts/test_signed_grades_upsert_request.sh

BASE_URL="${BASE_URL:-http://localhost}"
ENDPOINT_PATH="${ENDPOINT_PATH:-/modules/juss-examBridge/api/v1/grades/upsert.php}"
BRIDGE_KEY_ID="${BRIDGE_KEY_ID:-}"
BRIDGE_SHARED_SECRET="${BRIDGE_SHARED_SECRET:-}"
IDEMPOTENCY_KEY="${IDEMPOTENCY_KEY:-grade-sync-001}"
SOURCE_SYSTEM="${SOURCE_SYSTEM:-tcexam}"
EXAM_ID="${EXAM_ID:-exam-001}"
CLASS_EXTERNAL_ID="${CLASS_EXTERNAL_ID:-cohort-001}"
STUDENT_EXTERNAL_ID="${STUDENT_EXTERNAL_ID:-student-001}"
RAW_POINTS="${RAW_POINTS:-42}"
MAX_POINTS="${MAX_POINTS:-50}"
PERCENTAGE="${PERCENTAGE:-84}"
GRADE_STATUS="${GRADE_STATUS:-final}"
GRADED_AT="${GRADED_AT:-$(date -u +"%Y-%m-%dT%H:%M:%SZ")}"
TEST_CASE="${TEST_CASE:-happy_path}"

if [[ "${TEST_CASE}" == "list" ]]; then
  cat <<'EOF'
Available TEST_CASE values:
  happy_path
  invalid_json
  missing_records
  missing_source_system
  invalid_percentage
  invalid_score_fields
  invalid_graded_at
  unmapped_exam
  unmapped_class
  unmapped_student
EOF
  exit 0
fi

if [[ -z "${BRIDGE_KEY_ID}" || -z "${BRIDGE_SHARED_SECRET}" ]]; then
  echo "Error: BRIDGE_KEY_ID and BRIDGE_SHARED_SECRET are required." >&2
  exit 1
fi

EXPECTED_ERROR=""

BODY_BASE="$(cat <<EOF
{
  "idempotencyKey": "${IDEMPOTENCY_KEY}",
  "sourceSystem": "${SOURCE_SYSTEM}",
  "records": [
    {
      "examId": "${EXAM_ID}",
      "classExternalId": "${CLASS_EXTERNAL_ID}",
      "studentExternalId": "${STUDENT_EXTERNAL_ID}",
      "rawPoints": ${RAW_POINTS},
      "maxPoints": ${MAX_POINTS},
      "percentage": ${PERCENTAGE},
      "gradeStatus": "${GRADE_STATUS}",
      "gradedAt": "${GRADED_AT}"
    }
  ]
}
EOF
)"

BODY="${BODY_BASE}"

case "${TEST_CASE}" in
  happy_path)
    ;;
  invalid_json)
    BODY='{"idempotencyKey":"broken-json"'
    EXPECTED_ERROR="invalid_json"
    ;;
  missing_records)
    BODY="$(cat <<EOF
{
  "idempotencyKey": "${IDEMPOTENCY_KEY}",
  "sourceSystem": "${SOURCE_SYSTEM}"
}
EOF
)"
    EXPECTED_ERROR="missing_records"
    ;;
  missing_source_system)
    BODY="$(cat <<EOF
{
  "idempotencyKey": "${IDEMPOTENCY_KEY}",
  "records": [
    {
      "examId": "${EXAM_ID}",
      "classExternalId": "${CLASS_EXTERNAL_ID}",
      "studentExternalId": "${STUDENT_EXTERNAL_ID}",
      "rawPoints": ${RAW_POINTS},
      "maxPoints": ${MAX_POINTS},
      "percentage": ${PERCENTAGE},
      "gradeStatus": "${GRADE_STATUS}",
      "gradedAt": "${GRADED_AT}"
    }
  ]
}
EOF
)"
    EXPECTED_ERROR="missing_source_system"
    ;;
  invalid_percentage)
    BODY="$(printf "%s" "${BODY_BASE}" | sed 's/"percentage":[[:space:]]*[0-9.]\+/"percentage": 101/g')"
    EXPECTED_ERROR="invalid_percentage"
    ;;
  invalid_score_fields)
    BODY="$(printf "%s" "${BODY_BASE}" | sed 's/"rawPoints":[[:space:]]*[0-9.]\+/"rawPoints": "abc"/g')"
    EXPECTED_ERROR="invalid_score_fields"
    ;;
  invalid_graded_at)
    BODY="$(printf "%s" "${BODY_BASE}" | sed 's/"gradedAt":[[:space:]]*"[^"]*"/"gradedAt": "not-a-date"/g')"
    EXPECTED_ERROR="invalid_graded_at"
    ;;
  unmapped_exam)
    BODY="$(printf "%s" "${BODY_BASE}" | sed 's/"examId":[[:space:]]*"[^"]*"/"examId": "missing-exam"/g')"
    EXPECTED_ERROR="unmapped_exam"
    ;;
  unmapped_class)
    BODY="$(printf "%s" "${BODY_BASE}" | sed 's/"classExternalId":[[:space:]]*"[^"]*"/"classExternalId": "missing-class"/g')"
    EXPECTED_ERROR="unmapped_class"
    ;;
  unmapped_student)
    BODY="$(printf "%s" "${BODY_BASE}" | sed 's/"studentExternalId":[[:space:]]*"[^"]*"/"studentExternalId": "missing-student"/g')"
    EXPECTED_ERROR="unmapped_student"
    ;;
  *)
    echo "Error: unknown TEST_CASE '${TEST_CASE}'." >&2
    echo "Run with TEST_CASE=list to see available values." >&2
    exit 1
    ;;
esac

TIMESTAMP="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
NONCE="$(openssl rand -hex 16)"
BODY_HASH="$(printf "%s" "${BODY}" | openssl dgst -sha256 | awk '{print $2}')"
BASE_PATH="$(printf "%s" "${BASE_URL}" | sed -E 's#^[a-zA-Z]+://[^/]+##')"
if [[ -z "${BASE_PATH}" ]]; then
  BASE_PATH="/"
fi

CANONICAL_PATH="${ENDPOINT_PATH}"
if [[ "${BASE_PATH}" != "/" && "${ENDPOINT_PATH}" != "${BASE_PATH}"* ]]; then
  CANONICAL_PATH="${BASE_PATH}${ENDPOINT_PATH}"
fi

CANONICAL="POST
${CANONICAL_PATH}
${TIMESTAMP}
${NONCE}
${BODY_HASH}"
SIGNATURE="$(printf "%s" "${CANONICAL}" | openssl dgst -sha256 -hmac "${BRIDGE_SHARED_SECRET}" | awk '{print $2}')"

FULL_URL="${BASE_URL}${ENDPOINT_PATH}"

echo "Test Case: ${TEST_CASE}"
echo "Request URL: ${FULL_URL}"
echo "Timestamp: ${TIMESTAMP}"
echo "Nonce: ${NONCE}"
echo "Signature: ${SIGNATURE}"
if [[ -n "${EXPECTED_ERROR}" ]]; then
  echo "Expected Error: ${EXPECTED_ERROR}"
fi
echo

curl -sS -X POST "${FULL_URL}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Bridge-KeyId: ${BRIDGE_KEY_ID}" \
  -H "X-Bridge-Timestamp: ${TIMESTAMP}" \
  -H "X-Bridge-Nonce: ${NONCE}" \
  -H "X-Bridge-Signature: ${SIGNATURE}" \
  --data "${BODY}"
echo
