<?php
/*
Gibbon, Flexible & Open School System
*/

use Gibbon\Domain\System\SettingGateway;

require_once __DIR__ . '/../../../../../gibbon.php';
require_once __DIR__ . '/../../../moduleFunctions.php';
require_once __DIR__ . '/request.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    outputJussExamBridgeJson(405, [
        'ok' => false,
        'error' => 'method_not_allowed',
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$expectedPath = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH);

$verification = verifyJussExamBridgeSignedRequest($container, $connection2, $rawBody, $expectedPath);
if (!$verification['ok']) {
    outputJussExamBridgeJson($verification['status'], [
        'ok' => false,
        'error' => $verification['error'],
    ]);
    exit;
}

/** @var SettingGateway $settingGateway */
$settingGateway = $container->get(SettingGateway::class);
$gradeSyncEnabled = getJussExamBridgeSetting($settingGateway, 'gradeSyncEnabled', 'N') === 'Y';
$dryRunEnabled = getJussExamBridgeSetting($settingGateway, 'dryRunEnabled', 'Y') === 'Y';
$servicePersonID = getJussExamBridgeServicePersonID($container, $connection2);

$result = processJussExamBridgeGradesUpsertRequest(
    $connection2,
    (string) $rawBody,
    $gradeSyncEnabled,
    $dryRunEnabled,
    $servicePersonID
);

outputJussExamBridgeJson((int) $result['httpStatus'], $result['payload']);
