<?php
/*
Gibbon, Flexible & Open School System
*/

use Gibbon\Data\Validator;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . getModuleName($_POST['address']) . '/mapping_class.php';

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/mapping_class.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'upsert') {
        $externalCohortId = trim((string) ($_POST['externalCohortId'] ?? ''));
        $externalClassCode = trim((string) ($_POST['externalClassCode'] ?? ''));
        $gibbonCourseClassID = isset($_POST['gibbonCourseClassID']) ? (int) $_POST['gibbonCourseClassID'] : 0;

        if ($externalCohortId === '' || $gibbonCourseClassID <= 0) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $sql = "
            INSERT INTO gibbonJussExamBridgeClassMap
            (gibbonCourseClassID, externalCohortId, externalClassCode)
            VALUES (:gibbonCourseClassID, :externalCohortId, :externalClassCode)
            ON DUPLICATE KEY UPDATE
                gibbonCourseClassID = VALUES(gibbonCourseClassID),
                externalClassCode = VALUES(externalClassCode),
                updatedAt = CURRENT_TIMESTAMP
        ";
        $stmt = $connection2->prepare($sql);
        $stmt->execute([
            'gibbonCourseClassID' => $gibbonCourseClassID,
            'externalCohortId' => $externalCohortId,
            'externalClassCode' => $externalClassCode !== '' ? $externalClassCode : null,
        ]);
    } elseif ($action === 'delete') {
        $id = isset($_POST['gibbonJussExamBridgeClassMapID']) ? (int) $_POST['gibbonJussExamBridgeClassMapID'] : 0;
        if ($id <= 0) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $sql = 'DELETE FROM gibbonJussExamBridgeClassMap WHERE gibbonJussExamBridgeClassMapID = :id';
        $stmt = $connection2->prepare($sql);
        $stmt->execute(['id' => $id]);
    } else {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
} catch (PDOException $e) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

$URL .= '&return=success0';
header("Location: {$URL}");
