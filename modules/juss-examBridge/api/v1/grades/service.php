<?php

function processJussExamBridgeGradesUpsert(PDO $connection2, array $payload, string $rawBody, bool $gradeSyncEnabled, bool $dryRunEnabled, int $servicePersonID)
{
    if (!$gradeSyncEnabled) {
        return [
            'httpStatus' => 403,
            'payload' => [
                'ok' => false,
                'error' => 'grade_sync_disabled',
            ],
        ];
    }

    $idempotencyKey = trim((string) ($payload['idempotencyKey'] ?? ''));
    $sourceSystem = trim((string) ($payload['sourceSystem'] ?? ''));
    $records = $payload['records'] ?? null;

    if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 128) {
        return [
            'httpStatus' => 400,
            'payload' => [
                'ok' => false,
                'error' => 'invalid_idempotency_key',
            ],
        ];
    }

    if ($sourceSystem === '') {
        return [
            'httpStatus' => 400,
            'payload' => [
                'ok' => false,
                'error' => 'missing_source_system',
            ],
        ];
    }

    if (!is_array($records) || empty($records)) {
        return [
            'httpStatus' => 400,
            'payload' => [
                'ok' => false,
                'error' => 'missing_records',
            ],
        ];
    }

    $payloadHash = hash('sha256', (string) $rawBody);

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
                'message' => 'Duplicate request with identical payload accepted as no-op.',
            ],
        ];
    }

    try {
        $insertSyncSql = "
            INSERT INTO gibbonJussExamBridgeSyncLog
            (direction, operationType, idempotencyKey, payloadHash, status, errorCode, errorDetail)
            VALUES ('inbound', 'grades_upsert', :idempotencyKey, :payloadHash, 'accepted', NULL, NULL)
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

    $results = [];
    $acceptedCount = 0;
    $rejectedCount = 0;
    $hasConflict = false;

    foreach ($records as $index => $record) {
        $recordNumber = $index + 1;
        if (!is_array($record)) {
            $results[] = [
                'index' => $recordNumber,
                'status' => 'rejected',
                'code' => 'invalid_record',
                'message' => 'Record must be an object.',
            ];
            $rejectedCount++;
            continue;
        }

        $examId = trim((string) ($record['examId'] ?? ''));
        $classExternalId = trim((string) ($record['classExternalId'] ?? ''));
        $studentExternalId = trim((string) ($record['studentExternalId'] ?? ''));
        $rawPoints = $record['rawPoints'] ?? null;
        $maxPoints = $record['maxPoints'] ?? null;
        $percentage = $record['percentage'] ?? null;
        $gradeStatus = trim((string) ($record['gradeStatus'] ?? ''));
        $gradedAt = trim((string) ($record['gradedAt'] ?? ''));

        if ($examId === '' || $classExternalId === '' || $studentExternalId === '' || $gradeStatus === '' || $gradedAt === '') {
            $results[] = [
                'index' => $recordNumber,
                'status' => 'rejected',
                'code' => 'missing_required_fields',
                'message' => 'examId, classExternalId, studentExternalId, gradeStatus and gradedAt are required.',
            ];
            $rejectedCount++;
            continue;
        }

        if (!is_numeric($rawPoints) || !is_numeric($maxPoints) || (!is_numeric($percentage) && $percentage !== null && $percentage !== '')) {
            $results[] = [
                'index' => $recordNumber,
                'status' => 'rejected',
                'code' => 'invalid_score_fields',
                'message' => 'rawPoints, maxPoints and percentage must be numeric.',
            ];
            $rejectedCount++;
            continue;
        }

        $gradedAtTimestamp = strtotime($gradedAt);
        if ($gradedAtTimestamp === false) {
            $results[] = [
                'index' => $recordNumber,
                'status' => 'rejected',
                'code' => 'invalid_graded_at',
                'message' => 'gradedAt must be a valid date/time.',
            ];
            $rejectedCount++;
            continue;
        }

        $rawPointsValue = (float) $rawPoints;
        $maxPointsValue = (float) $maxPoints;
        $percentageValue = $percentage === null || $percentage === ''
            ? ($maxPointsValue > 0 ? ($rawPointsValue / $maxPointsValue) * 100 : null)
            : (float) $percentage;

        if ($percentageValue === null || $percentageValue < 0 || $percentageValue > 100) {
            $results[] = [
                'index' => $recordNumber,
                'status' => 'rejected',
                'code' => 'invalid_percentage',
                'message' => 'percentage must be between 0 and 100.',
            ];
            $rejectedCount++;
            continue;
        }

        try {
            $assessmentSql = "
                SELECT gibbonInternalAssessmentColumnID, syncMode
                FROM gibbonJussExamBridgeAssessmentMap
                WHERE externalExamId = :externalExamId
                LIMIT 1
            ";
            $assessmentStmt = $connection2->prepare($assessmentSql);
            $assessmentStmt->execute(['externalExamId' => $examId]);
            $assessmentMap = $assessmentStmt->fetch(PDO::FETCH_ASSOC);

            if (empty($assessmentMap) || empty($assessmentMap['gibbonInternalAssessmentColumnID'])) {
                $results[] = [
                    'index' => $recordNumber,
                    'status' => 'rejected',
                    'code' => 'unmapped_exam',
                    'message' => 'No Internal Assessment mapping found for examId.',
                ];
                $rejectedCount++;
                $hasConflict = true;
                continue;
            }

            if (!in_array($assessmentMap['syncMode'], ['internal_assessment', 'both'], true)) {
                $results[] = [
                    'index' => $recordNumber,
                    'status' => 'rejected',
                    'code' => 'unsupported_sync_mode',
                    'message' => 'Exam mapping is not configured for Internal Assessment writes.',
                ];
                $rejectedCount++;
                $hasConflict = true;
                continue;
            }

            $classSql = "
                SELECT gibbonCourseClassID
                FROM gibbonJussExamBridgeClassMap
                WHERE externalCohortId = :externalCohortId
                LIMIT 1
            ";
            $classStmt = $connection2->prepare($classSql);
            $classStmt->execute(['externalCohortId' => $classExternalId]);
            $classMap = $classStmt->fetch(PDO::FETCH_ASSOC);
            if (empty($classMap)) {
                $results[] = [
                    'index' => $recordNumber,
                    'status' => 'rejected',
                    'code' => 'unmapped_class',
                    'message' => 'No class mapping found for classExternalId.',
                ];
                $rejectedCount++;
                $hasConflict = true;
                continue;
            }

            $gibbonCourseClassID = (int) $classMap['gibbonCourseClassID'];
            $gibbonInternalAssessmentColumnID = (int) $assessmentMap['gibbonInternalAssessmentColumnID'];

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
            $column = $columnStmt->fetch(PDO::FETCH_ASSOC);

            if (empty($column)) {
                $results[] = [
                    'index' => $recordNumber,
                    'status' => 'rejected',
                    'code' => 'assessment_class_mismatch',
                    'message' => 'Mapped assessment column does not belong to mapped class.',
                ];
                $rejectedCount++;
                $hasConflict = true;
                continue;
            }

            $personSql = "
                SELECT gibbonPersonID
                FROM gibbonJussExamBridgePersonMap
                WHERE externalUserId = :externalUserId
                LIMIT 1
            ";
            $personStmt = $connection2->prepare($personSql);
            $personStmt->execute(['externalUserId' => $studentExternalId]);
            $personMap = $personStmt->fetch(PDO::FETCH_ASSOC);

            $gibbonPersonID = !empty($personMap) ? (int) $personMap['gibbonPersonID'] : 0;

            if ($gibbonPersonID === 0) {
                $externalEmail = trim((string) ($record['externalEmail'] ?? ''));
                $studentNumber = trim((string) ($record['studentNumber'] ?? ''));

                if ($externalEmail !== '') {
                    $emailSql = "
                        SELECT gibbonPersonID
                        FROM gibbonPerson
                        WHERE LOWER(email) = LOWER(:email)
                        LIMIT 1
                    ";
                    $emailStmt = $connection2->prepare($emailSql);
                    $emailStmt->execute(['email' => $externalEmail]);
                    $emailMatch = $emailStmt->fetch(PDO::FETCH_ASSOC);
                    if (!empty($emailMatch)) {
                        $gibbonPersonID = (int) $emailMatch['gibbonPersonID'];
                    }
                }

                if ($gibbonPersonID === 0 && $studentNumber !== '') {
                    $studentSql = "
                        SELECT gibbonPersonID
                        FROM gibbonPerson
                        WHERE studentID = :studentID
                        LIMIT 1
                    ";
                    $studentStmt = $connection2->prepare($studentSql);
                    $studentStmt->execute(['studentID' => $studentNumber]);
                    $studentMatch = $studentStmt->fetch(PDO::FETCH_ASSOC);
                    if (!empty($studentMatch)) {
                        $gibbonPersonID = (int) $studentMatch['gibbonPersonID'];
                    }
                }
            }

            if ($gibbonPersonID === 0) {
                $results[] = [
                    'index' => $recordNumber,
                    'status' => 'rejected',
                    'code' => 'unmapped_student',
                    'message' => 'No person mapping found for studentExternalId.',
                ];
                $rejectedCount++;
                $hasConflict = true;
                continue;
            }

            $enrollmentSql = "
                SELECT gibbonCourseClassPersonID
                FROM gibbonCourseClassPerson
                WHERE gibbonCourseClassID = :classID
                  AND gibbonPersonID = :personID
                  AND role IN ('Student', 'Student - Left')
                LIMIT 1
            ";
            $enrollmentStmt = $connection2->prepare($enrollmentSql);
            $enrollmentStmt->execute([
                'classID' => $gibbonCourseClassID,
                'personID' => $gibbonPersonID,
            ]);
            $enrollment = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);
            if (empty($enrollment)) {
                $results[] = [
                    'index' => $recordNumber,
                    'status' => 'rejected',
                    'code' => 'student_not_enrolled',
                    'message' => 'Student is not enrolled in the mapped class.',
                ];
                $rejectedCount++;
                $hasConflict = true;
                continue;
            }

            $attainmentValue = number_format($percentageValue, 2, '.', '');
            $gradeComment = sprintf(
                'TCExam sync: exam=%s status=%s raw=%.2f max=%.2f gradedAt=%s source=%s',
                $examId,
                $gradeStatus,
                $rawPointsValue,
                $maxPointsValue,
                gmdate('c', $gradedAtTimestamp),
                $sourceSystem
            );

            if ($dryRunEnabled) {
                $results[] = [
                    'index' => $recordNumber,
                    'status' => 'accepted',
                    'mode' => 'dry_run',
                    'gibbonPersonID' => $gibbonPersonID,
                    'gibbonInternalAssessmentColumnID' => $gibbonInternalAssessmentColumnID,
                    'attainmentValue' => $attainmentValue,
                ];
                $acceptedCount++;
                continue;
            }

            $entrySql = "
                SELECT gibbonInternalAssessmentEntryID
                FROM gibbonInternalAssessmentEntry
                WHERE gibbonInternalAssessmentColumnID = :columnID
                  AND gibbonPersonIDStudent = :personID
                LIMIT 1
            ";
            $entryStmt = $connection2->prepare($entrySql);
            $entryStmt->execute([
                'columnID' => $gibbonInternalAssessmentColumnID,
                'personID' => $gibbonPersonID,
            ]);
            $entry = $entryStmt->fetch(PDO::FETCH_ASSOC);

            if (empty($entry)) {
                $insertEntrySql = "
                    INSERT INTO gibbonInternalAssessmentEntry
                    (gibbonInternalAssessmentColumnID, gibbonPersonIDStudent, attainmentValue, attainmentDescriptor, effortValue, effortDescriptor, comment, response, gibbonPersonIDLastEdit)
                    VALUES (:columnID, :personID, :attainmentValue, NULL, NULL, NULL, :comment, NULL, :lastEditID)
                ";
                $insertEntryStmt = $connection2->prepare($insertEntrySql);
                $insertEntryStmt->execute([
                    'columnID' => $gibbonInternalAssessmentColumnID,
                    'personID' => $gibbonPersonID,
                    'attainmentValue' => $attainmentValue,
                    'comment' => $gradeComment,
                    'lastEditID' => $servicePersonID,
                ]);

                $results[] = [
                    'index' => $recordNumber,
                    'status' => 'accepted',
                    'mode' => 'write',
                    'action' => 'inserted',
                    'gibbonPersonID' => $gibbonPersonID,
                    'gibbonInternalAssessmentColumnID' => $gibbonInternalAssessmentColumnID,
                    'attainmentValue' => $attainmentValue,
                ];
                $acceptedCount++;
                continue;
            }

            $updateEntrySql = "
                UPDATE gibbonInternalAssessmentEntry
                SET attainmentValue = :attainmentValue,
                    comment = :comment,
                    gibbonPersonIDLastEdit = :lastEditID
                WHERE gibbonInternalAssessmentEntryID = :entryID
            ";
            $updateEntryStmt = $connection2->prepare($updateEntrySql);
            $updateEntryStmt->execute([
                'attainmentValue' => $attainmentValue,
                'comment' => $gradeComment,
                'lastEditID' => $servicePersonID,
                'entryID' => (int) $entry['gibbonInternalAssessmentEntryID'],
            ]);

            $results[] = [
                'index' => $recordNumber,
                'status' => 'accepted',
                'mode' => 'write',
                'action' => 'updated',
                'gibbonPersonID' => $gibbonPersonID,
                'gibbonInternalAssessmentColumnID' => $gibbonInternalAssessmentColumnID,
                'attainmentValue' => $attainmentValue,
            ];
            $acceptedCount++;
        } catch (PDOException $e) {
            $results[] = [
                'index' => $recordNumber,
                'status' => 'rejected',
                'code' => 'write_failed',
                'message' => 'Database operation failed for this record.',
            ];
            $rejectedCount++;
        }
    }

    $syncStatus = $acceptedCount > 0 ? 'accepted' : 'rejected';
    $errorCode = $acceptedCount > 0 ? null : ($hasConflict ? 'mapping_conflict' : 'validation_failed');
    $errorDetail = $acceptedCount > 0 ? null : 'All records were rejected.';

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

    $httpStatus = ($acceptedCount === 0 && $hasConflict) ? 409 : 200;

    return [
        'httpStatus' => $httpStatus,
        'payload' => [
            'ok' => $acceptedCount > 0,
            'idempotencyKey' => $idempotencyKey,
            'sourceSystem' => $sourceSystem,
            'dryRun' => $dryRunEnabled,
            'summary' => [
                'total' => count($records),
                'accepted' => $acceptedCount,
                'rejected' => $rejectedCount,
            ],
            'results' => $results,
        ],
    ];
}
