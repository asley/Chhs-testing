<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../modules/juss-examBridge/api/v1/grades/request.php';

class GradesUpsertRequestTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->seedValidMappingsAndEnrollment();
    }

    public function testRejectsMalformedJsonBody(): void
    {
        $result = processJussExamBridgeGradesUpsertRequest(
            $this->pdo,
            '{"idempotencyKey":"bad-json"',
            true,
            true,
            999
        );

        $this->assertSame(400, $result['httpStatus']);
        $this->assertSame('invalid_json', $result['payload']['error']);
    }

    public function testRejectsMissingRecordsAtRequestBoundary(): void
    {
        $payload = [
            'idempotencyKey' => 'req-missing-records',
            'sourceSystem' => 'tcexam',
        ];

        $result = processJussExamBridgeGradesUpsertRequest(
            $this->pdo,
            json_encode($payload),
            true,
            true,
            999
        );

        $this->assertSame(400, $result['httpStatus']);
        $this->assertSame('missing_records', $result['payload']['error']);
    }

    public function testRejectsInvalidPercentageInRecord(): void
    {
        $payload = $this->buildPayload('req-invalid-percentage', 101);
        $result = processJussExamBridgeGradesUpsertRequest(
            $this->pdo,
            json_encode($payload),
            true,
            false,
            999
        );

        $this->assertSame(200, $result['httpStatus']);
        $this->assertFalse($result['payload']['ok']);
        $this->assertSame('invalid_percentage', $result['payload']['results'][0]['code']);
        $this->assertSame(0, $result['payload']['summary']['accepted']);
        $this->assertSame(1, $result['payload']['summary']['rejected']);
    }

    public function testRejectsEmptyIdempotencyKeyAtRequestBoundary(): void
    {
        $payload = $this->buildPayload('', 80);
        $result = processJussExamBridgeGradesUpsertRequest(
            $this->pdo,
            json_encode($payload),
            true,
            true,
            999
        );

        $this->assertSame(400, $result['httpStatus']);
        $this->assertSame('invalid_idempotency_key', $result['payload']['error']);
    }

    public function testRejectsMissingSourceSystemAtRequestBoundary(): void
    {
        $payload = $this->buildPayload('req-missing-source', 80);
        unset($payload['sourceSystem']);

        $result = processJussExamBridgeGradesUpsertRequest(
            $this->pdo,
            json_encode($payload),
            true,
            true,
            999
        );

        $this->assertSame(400, $result['httpStatus']);
        $this->assertSame('missing_source_system', $result['payload']['error']);
    }

    public function testRejectsInvalidScoreFieldsInRecord(): void
    {
        $payload = $this->buildPayload('req-invalid-scores', 80);
        $payload['records'][0]['rawPoints'] = 'not-a-number';

        $result = processJussExamBridgeGradesUpsertRequest(
            $this->pdo,
            json_encode($payload),
            true,
            false,
            999
        );

        $this->assertSame(200, $result['httpStatus']);
        $this->assertFalse($result['payload']['ok']);
        $this->assertSame('invalid_score_fields', $result['payload']['results'][0]['code']);
        $this->assertSame(0, $result['payload']['summary']['accepted']);
        $this->assertSame(1, $result['payload']['summary']['rejected']);
    }

    public function testRejectsInvalidGradedAtInRecord(): void
    {
        $payload = $this->buildPayload('req-invalid-graded-at', 80);
        $payload['records'][0]['gradedAt'] = 'not-a-date';

        $result = processJussExamBridgeGradesUpsertRequest(
            $this->pdo,
            json_encode($payload),
            true,
            false,
            999
        );

        $this->assertSame(200, $result['httpStatus']);
        $this->assertFalse($result['payload']['ok']);
        $this->assertSame('invalid_graded_at', $result['payload']['results'][0]['code']);
        $this->assertSame(0, $result['payload']['summary']['accepted']);
        $this->assertSame(1, $result['payload']['summary']['rejected']);
    }

    private function buildPayload(string $idempotencyKey, float $percentage): array
    {
        return [
            'idempotencyKey' => $idempotencyKey,
            'sourceSystem' => 'tcexam',
            'records' => [
                [
                    'examId' => 'exam-1',
                    'classExternalId' => 'cohort-1',
                    'studentExternalId' => 'student-1',
                    'rawPoints' => 40,
                    'maxPoints' => 50,
                    'percentage' => $percentage,
                    'gradeStatus' => 'final',
                    'gradedAt' => '2026-02-20T00:00:00Z',
                ],
            ],
        ];
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
                errorDetail TEXT NULL
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
}
