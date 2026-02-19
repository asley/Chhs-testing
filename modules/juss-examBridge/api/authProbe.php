<?php
/*
Gibbon, Flexible & Open School System
*/

require_once __DIR__ . '/../../../gibbon.php';
require_once __DIR__ . '/../moduleFunctions.php';

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

outputJussExamBridgeJson(200, [
    'ok' => true,
    'message' => 'Signed request accepted.',
    'serverTime' => gmdate('c'),
]);
