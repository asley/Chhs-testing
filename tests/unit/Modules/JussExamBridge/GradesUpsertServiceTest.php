<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../modules/juss-examBridge/api/v1/grades/service.php';

class GradesUpsertServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->seedValidMappingsAndEnrollment();
    }

    public function testReturnsIdempotencyConflictForSameKeyDifferentPayload(): void
    {
        $payloadA = $this->buildPayload('idem-1', 80);
        $rawBodyA = json_encode($payloadA);
        $resultA = processJussExamBridgeGradesUpsert($this->pdo, $payloadA, $rawBodyA, true, true, 999);
        $this->assertSame(200, $resultA['httpStatus']);

        $payloadB = $this->buildPayload('idem-1', 81);
        $rawBodyB = json_encode($payloadB);
        $resultB = processJussExamBridgeGradesUpsert($this->pdo, $payloadB, $rawBodyB, true, true, 999);

        $this->assertSame(409, $resultB['httpStatus']);
        $this->assertSame('idempotency_conflict', $resultB['payload']['error']);
    }

    public function testReturnsIdempotentReplayForSameKeySamePayload(): void
    {
        $payload = $this->buildPayload('idem-2', 80);
        $rawBody = json_encode($payload);

        $first = processJussExamBridgeGradesUpsert($this->pdo, $payload, $rawBody, true, true, 999);
        $this->assertSame(200, $first['httpStatus']);

        $second = processJussExamBridgeGradesUpsert($this->pdo, $payload, $rawBody, true, true, 999);
        $this->assertSame(200, $second['httpStatus']);
        $this->assertTrue($second['payload']['idempotentReplay']);
    }

    public function testRejectsUnmappedExam(): void
    {
        $payload = $this->buildPayload('case-unmapped-exam', 80);
        $payload['records'][0]['examId'] = 'missing-exam';
        $result = processJussExamBridgeGradesUpsert($this->pdo, $payload, json_encode($payload), true, false, 999);

        $this->assertSame(409, $result['httpStatus']);
        $this->assertSame('unmapped_exam', $result['payload']['results'][0]['code']);
    }

    public function testRejectsUnmappedClass(): void
    {
        $payload = $this->buildPayload('case-unmapped-class', 80);
        $payload['records'][0]['classExternalId'] = 'missing-class';
        $result = processJussExamBridgeGradesUpsert($this->pdo, $payload, json_encode($payload), true, false, 999);

        $this->assertSame(409, $result['httpStatus']);
        $this->assertSame('unmapped_class', $result['payload']['results'][0]['code']);
    }

    public function testRejectsUnmappedStudent(): void
    {
        $payload = $this->buildPayload('case-unmapped-student', 80);
        $payload['records'][0]['studentExternalId'] = 'missing-student';
        $result = processJussExamBridgeGradesUpsert($this->pdo, $payload, json_encode($payload), true, false, 999);

        $this->assertSame(409, $result['httpStatus']);
        $this->assertSame('unmapped_student', $result['payload']['results'][0]['code']);
    }

    public function testRejectsWhenStudentNotEnrolled(): void
    {
        $this->pdo->exec('DELETE FROM gibbonCourseClassPerson');

        $payload = $this->buildPayload('case-not-enrolled', 80);
        $result = processJussExamBridgeGradesUpsert($this->pdo, $payload, json_encode($payload), true, false, 999);

        $this->assertSame(409, $result['httpStatus']);
        $this->assertSame('student_not_enrolled', $result['payload']['results'][0]['code']);
    }

    public function testWritesInternalAssessmentEntryWhenDryRunDisabled(): void
    {
        $payload = $this->buildPayload('case-write', 92);
        $result = processJussExamBridgeGradesUpsert($this->pdo, $payload, json_encode($payload), true, false, 999);

        $this->assertSame(200, $result['httpStatus']);
        $this->assertTrue($result['payload']['ok']);
        $this->assertSame('write', $result['payload']['results'][0]['mode']);

        $stmt = $this->pdo->query('SELECT attainmentValue, gibbonPersonIDLastEdit FROM gibbonInternalAssessmentEntry LIMIT 1');
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('92.00', $entry['attainmentValue']);
        $this->assertSame('999', (string) $entry['gibbonPersonIDLastEdit']);
    }

    public function testDoesNotWriteEntryWhenDryRunEnabled(): void
    {
        $payload = $this->buildPayload('case-dry-run', 88);
        $result = processJussExamBridgeGradesUpsert($this->pdo, $payload, json_encode($payload), true, true, 999);

        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('dry_run', $result['payload']['results'][0]['mode']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM gibbonInternalAssessmentEntry')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testRejectsOversizedRecordBatchBeforeWritingSyncLog(): void
    {
        $payload = $this->buildPayload('case-too-many-records', 88);
        $payload['records'] = array_fill(0, 201, $payload['records'][0]);

        $result = processJussExamBridgeGradesUpsert($this->pdo, $payload, json_encode($payload), true, true, 999);

        $this->assertSame(400, $result['httpStatus']);
        $this->assertSame('too_many_records', $result['payload']['error']);
        $this->assertSame(200, $result['payload']['maxRecords']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM gibbonJussExamBridgeSyncLog')->fetchColumn();
        $this->assertSame(0, $count);
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
