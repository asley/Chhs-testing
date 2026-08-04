<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker
*/

use Gibbon\Data\Validator;
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/api/v1/grades/pullService.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonCourseClassID = isset($_GET['gibbonCourseClassID']) ? (int) $_GET['gibbonCourseClassID'] : 0;
$gibbonInternalAssessmentColumnID = isset($_GET['gibbonInternalAssessmentColumnID']) ? (int) $_GET['gibbonInternalAssessmentColumnID'] : 0;
$gibbonPersonID = isset($_GET['gibbonPersonID']) ? (int) $_GET['gibbonPersonID'] : (int) $session->get('gibbonPersonID');

$URL = $session->get('absoluteURL')
    . '/index.php?q=/modules/Formal Assessment/internalAssessment_write_data.php'
    . '&gibbonCourseClassID=' . $gibbonCourseClassID
    . '&gibbonInternalAssessmentColumnID=' . $gibbonInternalAssessmentColumnID
    . '&gibbonPersonID=' . $gibbonPersonID;

if (isActionAccessible($guid, $connection2, '/modules/Formal Assessment/internalAssessment_write_data.php') == false
    || isActionAccessible($guid, $connection2, '/modules/juss-examBridge/internalAssessment_write_pullProcess.php') == false
) {
    header("Location: {$URL}&return=error0");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    header("Location: {$URL}&return=error3");
    exit;
}

if ($gibbonCourseClassID <= 0 || $gibbonInternalAssessmentColumnID <= 0) {
    header("Location: {$URL}&return=error1");
    exit;
}

$highestAction = getHighestGroupedAction($guid, '/modules/Formal Assessment/internalAssessment_write_data.php', $connection2);
if ($highestAction == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

try {
    if ($highestAction === 'Write Internal Assessments_all') {
        $classSql = "
            SELECT gibbonCourseClassID
            FROM gibbonCourseClass
            WHERE gibbonCourseClassID = :classID
            LIMIT 1
        ";
        $classData = ['classID' => $gibbonCourseClassID];
    } else {
        $classSql = "
            SELECT gibbonCourseClass.gibbonCourseClassID
            FROM gibbonCourseClass
            JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID)
            WHERE gibbonCourseClass.gibbonCourseClassID = :classID
              AND gibbonCourseClassPerson.gibbonPersonID = :personID
              AND gibbonCourseClassPerson.role = 'Teacher'
            LIMIT 1
        ";
        $classData = [
            'classID' => $gibbonCourseClassID,
            'personID' => $session->get('gibbonPersonID'),
        ];
    }

    $classStmt = $connection2->prepare($classSql);
    $classStmt->execute($classData);
    if (!$classStmt->fetch(PDO::FETCH_ASSOC)) {
        header("Location: {$URL}&return=error0");
        exit;
    }

    $columnSql = "
        SELECT locked
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
} catch (PDOException $e) {
    header("Location: {$URL}&return=error2");
    exit;
}

if (empty($column)) {
    header("Location: {$URL}&return=error2");
    exit;
}

if (($column['locked'] ?? 'N') === 'Y' && $highestAction !== 'Write Internal Assessments_all') {
    header("Location: {$URL}&return=error0");
    exit;
}

$mapping = resolveJussExamBridgePullMapping($connection2, $gibbonInternalAssessmentColumnID, $gibbonCourseClassID);
if (($mapping['ok'] ?? false) !== true) {
    header("Location: {$URL}&return=error1&pullError=" . urlencode((string) $mapping['error']));
    exit;
}

if (hasRecentJussExamBridgePullForTarget(
    $connection2,
    $mapping['examId'],
    $mapping['classExternalId'],
    $gibbonInternalAssessmentColumnID
)) {
    header("Location: {$URL}&return=warning1&pullError=recent_pull_exists");
    exit;
}

/** @var SettingGateway $settingGateway */
$settingGateway = $container->get(SettingGateway::class);
$bridgeConfig = [
    'tcexamBaseUrl' => getJussExamBridgeSetting($settingGateway, 'tcexamBaseUrl'),
    'bridgeKeyId' => getJussExamBridgeSetting($settingGateway, 'bridgeKeyId'),
    'bridgeSharedSecret' => getJussExamBridgeSetting($settingGateway, 'bridgeSharedSecret'),
    'timeoutSeconds' => 10,
];

$gradeSyncEnabled = getJussExamBridgeSetting($settingGateway, 'gradeSyncEnabled', 'N') === 'Y';
$dryRunEnabled = getJussExamBridgeSetting($settingGateway, 'dryRunEnabled', 'Y') === 'Y';
$servicePersonID = getJussExamBridgeServicePersonID($container, $connection2);

$result = processJussExamBridgeGradesPull(
    $connection2,
    $bridgeConfig,
    $gibbonInternalAssessmentColumnID,
    $gibbonCourseClassID,
    $gradeSyncEnabled,
    $dryRunEnabled,
    $servicePersonID
);

$payload = $result['payload'] ?? [];
$summary = is_array($payload) ? ($payload['summary'] ?? []) : [];
$returnCode = ((int) ($result['httpStatus'] ?? 500) >= 200 && (int) ($result['httpStatus'] ?? 500) < 300 && ($payload['ok'] ?? false))
    ? 'success0'
    : 'warning1';

$URL .= '&return=' . $returnCode
    . '&pullAccepted=' . urlencode((string) ($summary['accepted'] ?? 0))
    . '&pullRejected=' . urlencode((string) ($summary['rejected'] ?? 0))
    . '&pullSkipped=' . urlencode((string) ($summary['skipped'] ?? 0))
    . '&pullDryRun=' . ($dryRunEnabled ? 'Y' : 'N');

if (isset($payload['error'])) {
    $URL .= '&pullError=' . urlencode((string) $payload['error']);
}

header("Location: {$URL}");
