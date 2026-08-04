<?php

require_once __DIR__ . '/service.php';
require_once __DIR__ . '/pullClient.php';

function processJussExamBridgeGradesPull(
    PDO $connection2,
    array $bridgeConfig,
    int $gibbonInternalAssessmentColumnID,
    int $gibbonCourseClassID,
    bool $gradeSyncEnabled,
    bool $dryRunEnabled,
    int $servicePersonID,
    ?callable $transport = null
) {
    if (!$gradeSyncEnabled) {
        return [
            'httpStatus' => 403,
            'payload' => [
                'ok' => false,
                'error' => 'grade_sync_disabled',
            ],
        ];
    }

    $mapping = resolveJussExamBridgePullMapping($connection2, $gibbonInternalAssessmentColumnID, $gibbonCourseClassID);
    if (($mapping['ok'] ?? false) !== true) {
        return [
            'httpStatus' => 400,
            'payload' => [
                'ok' => false,
                'error' => $mapping['error'],
            ],
        ];
    }

    $fetchResult = fetchJussExamBridgePaginatedTcExamResults(
        $bridgeConfig,
        $mapping['examId'],
        $mapping['classExternalId'],
        $transport
    );

    if (($fetchResult['ok'] ?? false) !== true) {
        return [
            'httpStatus' => (int) ($fetchResult['httpStatus'] ?? 502),
            'payload' => [
                'ok' => false,
                'error' => $fetchResult['error'] ?? 'tcexam_pull_failed',
                'tcexamError' => $fetchResult['tcexamError'] ?? null,
                'message' => $fetchResult['message'] ?? null,
            ],
        ];
    }

    $tcexamPayload = $fetchResult['payload'];
    $resultBatchId = trim((string) ($tcexamPayload['resultBatchId'] ?? ''));
    if ($resultBatchId === '') {
        return [
            'httpStatus' => 502,
            'payload' => [
                'ok' => false,
                'error' => 'missing_result_batch_id',
            ],
        ];
    }

    $normalizeResult = normalizeJussExamBridgeTcExamResults(
        $tcexamPayload,
        $mapping['examId'],
        $mapping['classExternalId']
    );

    $records = $normalizeResult['records'];
    $skippedResults = $normalizeResult['skippedResults'];

    if (count($records) > 200) {
        return [
            'httpStatus' => 400,
            'payload' => [
                'ok' => false,
                'error' => 'too_many_records',
                'maxRecords' => 200,
            ],
        ];
    }

    $payloadHash = hash('sha256', json_encode([
        'resultBatchId' => $resultBatchId,
        'records' => $records,
    ], JSON_UNESCAPED_SLASHES) ?: '');
    $idempotencyKey = buildJussExamBridgePullIdempotencyKey(
        $mapping['examId'],
        $mapping['classExternalId'],
        $gibbonInternalAssessmentColumnID,
        $resultBatchId
    );

    try {
        $existingSql = 'SELECT * FROM gibbonJussExamBridgeSyncLog WHERE idempotencyKey = :idempotencyKey LIMIT 1';
        $existingStmt = $connection2->prepare($existingSql);
        $existingStmt->execute(['idempotencyKey' => $idempotencyKey]);
        $existingLog = $existingStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [
            'httpStatus' => 500,
            'payload' => [
                'ok' => false,
                'error' => 'sync_log_query_failed',
            ],
        ];
    }

    if (!empty($existingLog)) {
        if (!hash_equals((string) $existingLog['payloadHash'], $payloadHash)) {
            return [
                'httpStatus' => 409,
                'payload' => [
                    'ok' => false,
                    'error' => 'idempotency_conflict',
                ],
            ];
        }

        return [
            'httpStatus' => 200,
            'payload' => [
                'ok' => true,
                'idempotentReplay' => true,
                'idempotencyKey' => $idempotencyKey,
                'status' => $existingLog['status'],
                'message' => 'Duplicate pull with identical TCExam result batch accepted as no-op.',
            ],
        ];
    }

    try {
        $insertSyncSql = "
            INSERT INTO gibbonJussExamBridgeSyncLog
            (direction, operationType, idempotencyKey, payloadHash, status, errorCode, errorDetail)
            VALUES ('outbound', 'grades_pull', :idempotencyKey, :payloadHash, 'accepted', NULL, NULL)
        ";
        $insertSyncStmt = $connection2->prepare($insertSyncSql);
        $insertSyncStmt->execute([
            'idempotencyKey' => $idempotencyKey,
            'payloadHash' => $payloadHash,
        ]);
    } catch (PDOException $e) {
        return [
            'httpStatus' => 500,
            'payload' => [
                'ok' => false,
                'error' => 'sync_log_insert_failed',
            ],
        ];
    }

    $writeResult = applyJussExamBridgeInternalAssessmentGradeRecords(
        $connection2,
        $records,
        'tcexam',
        $dryRunEnabled,
        $servicePersonID
    );

    $acceptedCount = $writeResult['acceptedCount'];
    $rejectedCount = $writeResult['rejectedCount'] + $skippedResults;
    $hasConflict = $writeResult['hasConflict'];
    $syncStatus = $acceptedCount > 0 ? 'accepted' : 'rejected';
    $errorCode = $acceptedCount > 0 ? null : ($hasConflict ? 'mapping_conflict' : 'validation_failed');
    $errorDetail = $acceptedCount > 0 ? null : 'All pulled records were rejected or skipped.';

    try {
        $updateSyncSql = "
            UPDATE gibbonJussExamBridgeSyncLog
            SET status = :status,
                errorCode = :errorCode,
                errorDetail = :errorDetail
            WHERE idempotencyKey = :idempotencyKey
        ";
        $updateSyncStmt = $connection2->prepare($updateSyncSql);
        $updateSyncStmt->execute([
            'status' => $syncStatus,
            'errorCode' => $errorCode,
            'errorDetail' => $errorDetail,
            'idempotencyKey' => $idempotencyKey,
        ]);
    } catch (PDOException $e) {
        return [
            'httpStatus' => 500,
            'payload' => [
                'ok' => false,
                'error' => 'sync_log_update_failed',
            ],
        ];
    }

    return [
        'httpStatus' => ($acceptedCount === 0 && $hasConflict) ? 409 : 200,
        'payload' => [
            'ok' => $acceptedCount > 0,
            'idempotencyKey' => $idempotencyKey,
            'sourceSystem' => 'tcexam',
            'resultBatchId' => $resultBatchId,
            'dryRun' => $dryRunEnabled,
            'summary' => [
                'total' => count($records) + $skippedResults,
                'accepted' => $acceptedCount,
                'rejected' => $rejectedCount,
                'skipped' => $skippedResults,
            ],
            'results' => $writeResult['results'],
        ],
    ];
}

function fetchJussExamBridgePaginatedTcExamResults(array $bridgeConfig, string $examId, string $classExternalId, ?callable $transport = null): array
{
    $pageSize = max(1, min(200, (int) ($bridgeConfig['pageSize'] ?? 100)));
    $timeoutSeconds = (int) ($bridgeConfig['timeoutSeconds'] ?? 10);
    $updatedAfter = isset($bridgeConfig['updatedAfter']) ? (string) $bridgeConfig['updatedAfter'] : null;
    $page = 1;
    $totalPages = null;
    $resultBatchId = null;
    $combinedPayload = null;
    $combinedResults = [];

    do {
        $request = buildJussExamBridgeTcExamResultsRequest(
            (string) ($bridgeConfig['tcexamBaseUrl'] ?? ''),
            (string) ($bridgeConfig['bridgeKeyId'] ?? ''),
            (string) ($bridgeConfig['bridgeSharedSecret'] ?? ''),
            $examId,
            $classExternalId,
            $page,
            $pageSize,
            $updatedAfter
        );

        $fetchResult = fetchJussExamBridgeTcExamResults(
            $request,
            $transport,
            $timeoutSeconds
        );

        if (($fetchResult['ok'] ?? false) !== true) {
            return $fetchResult;
        }

        $payload = $fetchResult['payload'];
        $pageResultBatchId = trim((string) ($payload['resultBatchId'] ?? ''));
        if ($pageResultBatchId === '') {
            return [
                'ok' => false,
                'error' => 'missing_result_batch_id',
                'httpStatus' => 502,
            ];
        }

        if ($resultBatchId === null) {
            $resultBatchId = $pageResultBatchId;
            $combinedPayload = $payload;
        } elseif (!hash_equals($resultBatchId, $pageResultBatchId)) {
            return [
                'ok' => false,
                'error' => 'result_batch_changed_during_pagination',
                'httpStatus' => 409,
            ];
        }

        $pageResults = $payload['results'] ?? [];
        if (!is_array($pageResults)) {
            return [
                'ok' => false,
                'error' => 'invalid_tcexam_results',
                'httpStatus' => 502,
            ];
        }

        $combinedResults = array_merge($combinedResults, $pageResults);
        if (count($combinedResults) > 200) {
            return [
                'ok' => false,
                'error' => 'too_many_tcexam_results',
                'httpStatus' => 400,
                'maxRecords' => 200,
            ];
        }

        $pagination = $payload['pagination'] ?? null;
        if (is_array($pagination) && isset($pagination['totalPages']) && is_numeric($pagination['totalPages'])) {
            $totalPages = max(1, (int) $pagination['totalPages']);
        } else {
            $totalPages = $page;
        }

        $page++;
    } while ($page <= $totalPages);

    if (!is_array($combinedPayload)) {
        return [
            'ok' => false,
            'error' => 'invalid_tcexam_response',
            'httpStatus' => 502,
        ];
    }

    $combinedPayload['results'] = $combinedResults;
    $combinedPayload['pagination'] = [
        'page' => 1,
        'pageSize' => $pageSize,
        'total' => count($combinedResults),
        'totalPages' => max(1, $totalPages ?? 1),
    ];

    return [
        'ok' => true,
        'httpStatus' => 200,
        'payload' => $combinedPayload,
    ];
}

function resolveJussExamBridgePullMapping(PDO $connection2, int $gibbonInternalAssessmentColumnID, int $gibbonCourseClassID)
{
    if ($gibbonInternalAssessmentColumnID <= 0 || $gibbonCourseClassID <= 0) {
        return [
            'ok' => false,
            'error' => 'invalid_pull_target',
        ];
    }

    try {
        $assessmentSql = "
            SELECT externalExamId, syncMode
            FROM gibbonJussExamBridgeAssessmentMap
            WHERE gibbonInternalAssessmentColumnID = :columnID
            LIMIT 1
        ";
        $assessmentStmt = $connection2->prepare($assessmentSql);
        $assessmentStmt->execute(['columnID' => $gibbonInternalAssessmentColumnID]);
        $assessmentMap = $assessmentStmt->fetch(PDO::FETCH_ASSOC);

        if (empty($assessmentMap) || trim((string) $assessmentMap['externalExamId']) === '') {
            return [
                'ok' => false,
                'error' => 'unmapped_exam',
            ];
        }

        if (!in_array((string) $assessmentMap['syncMode'], ['internal_assessment', 'both'], true)) {
            return [
                'ok' => false,
                'error' => 'unsupported_sync_mode',
            ];
        }

        $classSql = "
            SELECT externalCohortId
            FROM gibbonJussExamBridgeClassMap
            WHERE gibbonCourseClassID = :classID
            LIMIT 1
        ";
        $classStmt = $connection2->prepare($classSql);
        $classStmt->execute(['classID' => $gibbonCourseClassID]);
        $classMap = $classStmt->fetch(PDO::FETCH_ASSOC);

        if (empty($classMap) || trim((string) $classMap['externalCohortId']) === '') {
            return [
                'ok' => false,
                'error' => 'unmapped_class',
            ];
        }

        $columnSql = "
            SELECT gibbonInternalAssessmentColumnID
            FROM gibbonInternalAssessmentColumn
            WHERE gibbonInternalAssessmentColumnID = :columnID
              AND gibbonCourseClassID = :classID
            LIMIT 1
        ";
        $columnStmt = $connection2->prepare($columnSql);
        $columnStmt->execute([
            'columnID' => $gibbonInternalAssessmentColumnID,
            'classID' => $gibbonCourseClassID,
        ]);

        if (!$columnStmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'ok' => false,
                'error' => 'assessment_class_mismatch',
            ];
        }
    } catch (PDOException $e) {
        return [
            'ok' => false,
            'error' => 'mapping_query_failed',
        ];
    }

    return [
        'ok' => true,
        'examId' => trim((string) $assessmentMap['externalExamId']),
        'classExternalId' => trim((string) $classMap['externalCohortId']),
    ];
}

function normalizeJussExamBridgeTcExamResults(array $tcexamPayload, string $examId, string $classExternalId)
{
    $records = [];
    $skippedResults = 0;
    $results = $tcexamPayload['results'] ?? [];

    if (!is_array($results)) {
        return [
            'records' => [],
            'skippedResults' => 0,
        ];
    }

    foreach ($results as $result) {
        if (!is_array($result)) {
            $skippedResults++;
            continue;
        }

        if (trim((string) ($result['gradeStatus'] ?? '')) !== 'final') {
            $skippedResults++;
            continue;
        }

        $records[] = [
            'examId' => $examId,
            'classExternalId' => $classExternalId,
            'studentExternalId' => trim((string) ($result['studentExternalId'] ?? '')),
            'rawPoints' => $result['rawPoints'] ?? null,
            'maxPoints' => $result['maxPoints'] ?? null,
            'percentage' => $result['percentage'] ?? null,
            'gradeStatus' => 'final',
            'gradedAt' => trim((string) ($result['gradedAt'] ?? '')),
            'externalEmail' => isset($result['externalEmail']) ? trim((string) $result['externalEmail']) : '',
            'studentNumber' => isset($result['studentNumber']) ? trim((string) $result['studentNumber']) : '',
        ];
    }

    return [
        'records' => $records,
        'skippedResults' => $skippedResults,
    ];
}

function buildJussExamBridgePullIdempotencyKey(string $examId, string $classExternalId, int $gibbonInternalAssessmentColumnID, string $resultBatchId): string
{
    return 'pull:' . buildJussExamBridgePullTargetHash($examId, $classExternalId, $gibbonInternalAssessmentColumnID)
        . ':' . hash('sha256', $resultBatchId);
}

function buildJussExamBridgePullTargetHash(string $examId, string $classExternalId, int $gibbonInternalAssessmentColumnID): string
{
    return substr(hash('sha256', implode(':', [
        $examId,
        $classExternalId,
        (string) $gibbonInternalAssessmentColumnID,
    ])), 0, 16);
}

function hasRecentJussExamBridgePullForTarget(PDO $connection2, string $examId, string $classExternalId, int $gibbonInternalAssessmentColumnID, int $debounceSeconds = 120): bool
{
    $targetHash = buildJussExamBridgePullTargetHash($examId, $classExternalId, $gibbonInternalAssessmentColumnID);

    try {
        $sql = "
            SELECT createdAt, CURRENT_TIMESTAMP AS dbNow
            FROM gibbonJussExamBridgeSyncLog
            WHERE direction = 'outbound'
              AND operationType = 'grades_pull'
              AND idempotencyKey LIKE :idempotencyPrefix
            ORDER BY createdAt DESC
            LIMIT 1
        ";
        $stmt = $connection2->prepare($sql);
        $stmt->execute(['idempotencyPrefix' => 'pull:' . $targetHash . ':%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }

    if (empty($row['createdAt']) || empty($row['dbNow'])) {
        return false;
    }

    // Diff two timestamps read from the same database clock/timezone in the
    // same query, rather than comparing a DB-local createdAt against PHP's
    // time() (they can be in different timezones and drift out of sync).
    $createdAtTimestamp = strtotime((string) $row['createdAt']);
    $dbNowTimestamp = strtotime((string) $row['dbNow']);
    if ($createdAtTimestamp === false || $dbNowTimestamp === false) {
        return false;
    }

    return $dbNowTimestamp - $createdAtTimestamp < max(1, $debounceSeconds);
}
