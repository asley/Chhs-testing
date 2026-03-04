<?php

require_once __DIR__ . '/service.php';

function processJussExamBridgeGradesUpsertRequest(PDO $connection2, string $rawBody, bool $gradeSyncEnabled, bool $dryRunEnabled, int $servicePersonID)
{
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        return [
            'httpStatus' => 400,
            'payload' => [
                'ok' => false,
                'error' => 'invalid_json',
            ],
        ];
    }

    return processJussExamBridgeGradesUpsert(
        $connection2,
        $payload,
        $rawBody,
        $gradeSyncEnabled,
        $dryRunEnabled,
        $servicePersonID
    );
}
