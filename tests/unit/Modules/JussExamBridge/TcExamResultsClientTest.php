<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../modules/juss-examBridge/api/v1/grades/pullClient.php';

class TcExamResultsClientTest extends TestCase
{
    public function testBuildsSignedResultsRequest(): void
    {
        $request = buildJussExamBridgeTcExamResultsRequest(
            'https://tcexam.example.test',
            'key-1',
            'secret-1',
            'exam-1',
            'cohort-1',
            1,
            100,
            null,
            '2026-02-20T12:30:00Z',
            'nonce-1'
        );

        $this->assertTrue($request['ok']);
        $this->assertSame('GET', $request['method']);
        $this->assertSame('/api/bridge/v1/results', $request['path']);
        $this->assertStringContainsString('examId=exam-1', $request['url']);
        $this->assertStringContainsString('classExternalId=cohort-1', $request['url']);
        $this->assertSame('key-1', $request['headers']['X-Bridge-KeyId']);
        $this->assertSame('2026-02-20T12:30:00Z', $request['headers']['X-Bridge-Timestamp']);
        $this->assertSame('nonce-1', $request['headers']['X-Bridge-Nonce']);

        $expectedCanonical = "GET\n/api/bridge/v1/results\n2026-02-20T12:30:00Z\nnonce-1\n" . hash('sha256', '');
        $this->assertSame($expectedCanonical, $request['canonical']);
        $this->assertSame(hash_hmac('sha256', $expectedCanonical, 'secret-1'), $request['headers']['X-Bridge-Signature']);
    }

    public function testRejectsUnsafePublicHttpBaseUrl(): void
    {
        $request = buildJussExamBridgeTcExamResultsRequest(
            'http://tcexam.example.com',
            'key-1',
            'secret-1',
            'exam-1',
            'cohort-1'
        );

        $this->assertFalse($request['ok']);
        $this->assertSame('unsafe_tcexam_base_url', $request['error']);
    }

    public function testFetchParsesSuccessfulResultsWithoutNetwork(): void
    {
        $request = buildJussExamBridgeTcExamResultsRequest(
            'https://tcexam.example.test',
            'key-1',
            'secret-1',
            'exam-1',
            'cohort-1',
            1,
            100,
            null,
            '2026-02-20T12:30:00Z',
            'nonce-1'
        );

        $result = fetchJussExamBridgeTcExamResults($request, static function () {
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
        });

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('batch-1', $result['payload']['resultBatchId']);
        $this->assertSame('student-1', $result['payload']['results'][0]['studentExternalId']);
    }

    public function testFetchPreservesTcExamErrorCode(): void
    {
        $request = buildJussExamBridgeTcExamResultsRequest(
            'https://tcexam.example.test',
            'key-1',
            'secret-1',
            'exam-1',
            'cohort-1'
        );

        $result = fetchJussExamBridgeTcExamResults($request, static function () {
            return [
                'statusCode' => 401,
                'body' => json_encode([
                    'ok' => false,
                    'error' => 'path_mismatch',
                    'message' => 'Signed path did not match request path.',
                ]),
                'error' => '',
            ];
        });

        $this->assertFalse($result['ok']);
        $this->assertSame('tcexam_error_response', $result['error']);
        $this->assertSame(401, $result['httpStatus']);
        $this->assertSame('path_mismatch', $result['tcexamError']);
    }
}
