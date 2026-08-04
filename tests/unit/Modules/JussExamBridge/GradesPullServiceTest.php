<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../modules/juss-examBridge/api/v1/grades/pullService.php';

class GradesPullServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->seedValidMappingsAndEnrollment();
    }

    public function testPullDryRunNormalizesAndAppliesResults(): void
    {
        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            1001,
            5001,
            true,
            true,
            999,
            $this->makeSuccessTransport()
        );

        $this->assertSame(200, $result['httpStatus']);
        $this->assertTrue($result['payload']['ok']);
        $this->assertSame('batch-1', $result['payload']['resultBatchId']);
        $this->assertSame(1, $result['payload']['summary']['accepted']);
        $this->assertSame('dry_run', $result['payload']['results'][0]['mode']);

        $entryCount = (int) $this->pdo->query('SELECT COUNT(*) FROM gibbonInternalAssessmentEntry')->fetchColumn();
        $this->assertSame(0, $entryCount);

        $log = $this->pdo->query('SELECT direction, operationType, status FROM gibbonJussExamBridgeSyncLog LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('outbound', $log['direction']);
        $this->assertSame('grades_pull', $log['operationType']);
        $this->assertSame('accepted', $log['status']);
    }

    public function testPullWritesInternalAssessmentEntryWhenDryRunDisabled(): void
    {
        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            1001,
            5001,
            true,
            false,
            999,
            $this->makeSuccessTransport()
        );

        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('write', $result['payload']['results'][0]['mode']);

        $entry = $this->pdo->query('SELECT attainmentValue, gibbonPersonIDLastEdit FROM gibbonInternalAssessmentEntry LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('84.00', $entry['attainmentValue']);
        $this->assertSame('999', (string) $entry['gibbonPersonIDLastEdit']);
    }

    public function testPullAcceptsBothSyncModeForInternalAssessment(): void
    {
        $this->pdo->exec("UPDATE gibbonJussExamBridgeAssessmentMap SET syncMode = 'both' WHERE gibbonInternalAssessmentColumnID = 1001");

        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            1001,
            5001,
            true,
            false,
            999,
            $this->makeSuccessTransport()
        );

        $this->assertSame(200, $result['httpStatus']);
        $this->assertTrue($result['payload']['ok']);
        $this->assertSame(1, $result['payload']['summary']['accepted']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM gibbonInternalAssessmentEntry')->fetchColumn());
    }

    public function testPullFetchesAllTcExamPagesBeforeWriting(): void
    {
        $this->seedSecondStudent();
        $calls = [];

        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            array_merge($this->buildBridgeConfig(), ['pageSize' => 1]),
            1001,
            5001,
            true,
            false,
            999,
            function (string $method, string $url) use (&$calls) {
                $calls[] = $url;
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $page = (int) ($query['page'] ?? 1);

                return [
                    'statusCode' => 200,
                    'body' => json_encode($this->buildTcExamPayloadForPage($page)),
                    'error' => '',
                ];
            }
        );

        $this->assertSame(200, $result['httpStatus']);
        $this->assertTrue($result['payload']['ok']);
        $this->assertSame(2, $result['payload']['summary']['accepted']);
        $this->assertCount(2, $calls);
        $this->assertStringContainsString('page=1', $calls[0]);
        $this->assertStringContainsString('page=2', $calls[1]);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM gibbonInternalAssessmentEntry')->fetchColumn());
    }

    public function testPullRejectsAggregateResultsOverLimitAcrossPages(): void
    {
        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            array_merge($this->buildBridgeConfig(), ['pageSize' => 200]),
            1001,
            5001,
            true,
            true,
            999,
            static function (string $method, string $url) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $page = (int) ($query['page'] ?? 1);
                $count = $page === 1 ? 200 : 1;
                $results = [];
                for ($i = 1; $i <= $count; $i++) {
                    $results[] = [
                        'attemptId' => 'attempt-' . $page . '-' . $i,
                        'studentExternalId' => 'student-1',
                        'rawPoints' => 42,
                        'maxPoints' => 50,
                        'percentage' => 84,
                        'gradeStatus' => 'final',
                        'gradedAt' => '2026-02-20T12:00:00Z',
                    ];
                }

                return [
                    'statusCode' => 200,
                    'body' => json_encode([
                        'ok' => true,
                        'examId' => 'exam-1',
                        'classExternalId' => 'cohort-1',
                        'resultBatchId' => 'batch-over-limit',
                        'pagination' => [
                            'page' => $page,
                            'pageSize' => 200,
                            'total' => 201,
                            'totalPages' => 2,
                        ],
                        'results' => $results,
                    ]),
                    'error' => '',
                ];
            }
        );

        $this->assertSame(400, $result['httpStatus']);
        $this->assertSame('too_many_tcexam_results', $result['payload']['error']);
    }

    public function testPullReturnsIdempotentReplayForSameResultBatch(): void
    {
        $first = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            1001,
            5001,
            true,
            true,
            999,
            $this->makeSuccessTransport()
        );
        $this->assertSame(200, $first['httpStatus']);

        $second = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            1001,
            5001,
            true,
            true,
            999,
            $this->makeSuccessTransport()
        );

        $this->assertSame(200, $second['httpStatus']);
        $this->assertTrue($second['payload']['idempotentReplay']);
    }

    public function testPullRejectsMissingMappingBeforeCallingTcExam(): void
    {
        $called = false;
        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            9999,
            5001,
            true,
            true,
            999,
            static function () use (&$called) {
                $called = true;
                return ['statusCode' => 500, 'body' => '{}', 'error' => 'should_not_call'];
            }
        );

        $this->assertSame(400, $result['httpStatus']);
        $this->assertSame('unmapped_exam', $result['payload']['error']);
        $this->assertFalse($called);
    }

    public function testHasRecentPullDetectsRecentPullForSameTarget(): void
    {
        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            1001,
            5001,
            true,
            true,
            999,
            $this->makeSuccessTransport()
        );
        $this->assertSame(200, $result['httpStatus']);

        $this->assertTrue(hasRecentJussExamBridgePullForTarget($this->pdo, 'exam-1', 'cohort-1', 1001));
    }

    public function testHasRecentPullIsFalseForDifferentTarget(): void
    {
        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            1001,
            5001,
            true,
            true,
            999,
            $this->makeSuccessTransport()
        );
        $this->assertSame(200, $result['httpStatus']);

        $this->assertFalse(hasRecentJussExamBridgePullForTarget($this->pdo, 'exam-1', 'cohort-1', 9999));
    }

    public function testHasRecentPullIsFalseWithNoPriorPulls(): void
    {
        $this->assertFalse(hasRecentJussExamBridgePullForTarget($this->pdo, 'exam-1', 'cohort-1', 1001));
    }

    public function testPullRejectsMissingResultBatchId(): void
    {
        $result = processJussExamBridgeGradesPull(
            $this->pdo,
            $this->buildBridgeConfig(),
            1001,
            5001,
            true,
            true,
            999,
            static function () {
                return [
                    'statusCode' => 200,
                    'body' => json_encode([
                        'ok' => true,
                        'results' => [],
                    ]),
                    'error' => '',
                ];
            }
        );

        $this->assertSame(502, $result['httpStatus']);
        $this->assertSame('missing_result_batch_id', $result['payload']['error']);
    }

    private function buildBridgeConfig(): array
    {
        return [
            'tcexamBaseUrl' => 'https://tcexam.example.test',
            'bridgeKeyId' => 'key-1',
            'bridgeSharedSecret' => 'secret-1',
            'timeoutSeconds' => 10,
        ];
    }

    private function makeSuccessTransport(): callable
    {
        return static function () {
            return [
                'statusCode' => 200,
                'body' => json_encode([
                    'ok' => true,
                    'sourceSystem' => 'tcexam',
                    'examId' => 'exam-1',
                    'classExternalId' => 'cohort-1',
                    'resultBatchId' => 'batch-1',
                    'generatedAt' => '2026-02-20T12:30:00Z',
                    'pagination' => [
                        'page' => 1,
                        'pageSize' => 100,
                        'total' => 1,
                        'totalPages' => 1,
                    ],
                    'results' => [
                        [
                            'resultId' => 'result-1',
                            'attemptId' => 'attempt-1',
                            'studentExternalId' => 'student-1',
                            'rawPoints' => 42,
                            'maxPoints' => 50,
                            'percentage' => 84,
                            'gradeStatus' => 'final',
                            'gradedAt' => '2026-02-20T12:00:00Z',
                        ],
                    ],
                ]),
                'error' => '',
            ];
        };
    }

    private function createSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE gibbonJussExamBridgeSyncLog (
                gibbonJussExamBridgeSyncLogID INTEGER PRIMARY KEY AUTOINCREMENT,
                direction TEXT NOT NULL,
                operationType TEXT NOT NULL,
                idempotencyKey TEXT NOT NULL UNIQUE,
                payloadHash TEXT NOT NULL,
                status TEXT NOT NULL,
                errorCode TEXT NULL,
                errorDetail TEXT NULL,
                createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE gibbonJussExamBridgeAssessmentMap (
                gibbonJussExamBridgeAssessmentMapID INTEGER PRIMARY KEY AUTOINCREMENT,
                externalExamId TEXT NOT NULL UNIQUE,
                gibbonInternalAssessmentColumnID INTEGER,
                gibbonMarkbookColumnID INTEGER,
                syncMode TEXT NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE gibbonJussExamBridgeClassMap (
                gibbonJussExamBridgeClassMapID INTEGER PRIMARY KEY AUTOINCREMENT,
                gibbonCourseClassID INTEGER NOT NULL,
                externalCohortId TEXT NOT NULL UNIQUE
            )
        ');

        $this->pdo->exec('
            CREATE TABLE gibbonJussExamBridgePersonMap (
                gibbonJussExamBridgePersonMapID INTEGER PRIMARY KEY AUTOINCREMENT,
                gibbonPersonID INTEGER NOT NULL,
                externalUserId TEXT NOT NULL UNIQUE
            )
        ');

        $this->pdo->exec('
            CREATE TABLE gibbonInternalAssessmentColumn (
                gibbonInternalAssessmentColumnID INTEGER PRIMARY KEY,
                gibbonCourseClassID INTEGER NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE gibbonCourseClassPerson (
                gibbonCourseClassPersonID INTEGER PRIMARY KEY AUTOINCREMENT,
                gibbonCourseClassID INTEGER NOT NULL,
                gibbonPersonID INTEGER NOT NULL,
                role TEXT NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE gibbonPerson (
                gibbonPersonID INTEGER PRIMARY KEY,
                email TEXT NULL,
                studentID TEXT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE gibbonInternalAssessmentEntry (
                gibbonInternalAssessmentEntryID INTEGER PRIMARY KEY AUTOINCREMENT,
                gibbonInternalAssessmentColumnID INTEGER NOT NULL,
                gibbonPersonIDStudent INTEGER NOT NULL,
                attainmentValue TEXT NULL,
                attainmentDescriptor TEXT NULL,
                effortValue TEXT NULL,
                effortDescriptor TEXT NULL,
                comment TEXT NULL,
                response TEXT NULL,
                gibbonPersonIDLastEdit INTEGER NOT NULL
            )
        ');
    }

    private function seedValidMappingsAndEnrollment(): void
    {
        $this->pdo->exec("INSERT INTO gibbonJussExamBridgeAssessmentMap (externalExamId, gibbonInternalAssessmentColumnID, syncMode) VALUES ('exam-1', 1001, 'internal_assessment')");
        $this->pdo->exec("INSERT INTO gibbonJussExamBridgeClassMap (gibbonCourseClassID, externalCohortId) VALUES (5001, 'cohort-1')");
        $this->pdo->exec("INSERT INTO gibbonJussExamBridgePersonMap (gibbonPersonID, externalUserId) VALUES (3001, 'student-1')");
        $this->pdo->exec("INSERT INTO gibbonInternalAssessmentColumn (gibbonInternalAssessmentColumnID, gibbonCourseClassID) VALUES (1001, 5001)");
        $this->pdo->exec("INSERT INTO gibbonCourseClassPerson (gibbonCourseClassID, gibbonPersonID, role) VALUES (5001, 3001, 'Student')");
        $this->pdo->exec("INSERT INTO gibbonPerson (gibbonPersonID, email, studentID) VALUES (3001, 'student@example.com', 'S-3001')");
    }

    private function seedSecondStudent(): void
    {
        $this->pdo->exec("INSERT INTO gibbonJussExamBridgePersonMap (gibbonPersonID, externalUserId) VALUES (3002, 'student-2')");
        $this->pdo->exec("INSERT INTO gibbonCourseClassPerson (gibbonCourseClassID, gibbonPersonID, role) VALUES (5001, 3002, 'Student')");
        $this->pdo->exec("INSERT INTO gibbonPerson (gibbonPersonID, email, studentID) VALUES (3002, 'student2@example.com', 'S-3002')");
    }

    private function buildTcExamPayloadForPage(int $page): array
    {
        $studentExternalId = $page === 1 ? 'student-1' : 'student-2';

        return [
            'ok' => true,
            'sourceSystem' => 'tcexam',
            'examId' => 'exam-1',
            'classExternalId' => 'cohort-1',
            'resultBatchId' => 'batch-paginated',
            'pagination' => [
                'page' => $page,
                'pageSize' => 1,
                'total' => 2,
                'totalPages' => 2,
            ],
            'results' => [
                [
                    'attemptId' => 'attempt-' . $page,
                    'studentExternalId' => $studentExternalId,
                    'rawPoints' => 40 + $page,
                    'maxPoints' => 50,
                    'percentage' => 80 + $page,
                    'gradeStatus' => 'final',
                    'gradedAt' => '2026-02-20T12:00:00Z',
                ],
            ],
        ];
    }
}
