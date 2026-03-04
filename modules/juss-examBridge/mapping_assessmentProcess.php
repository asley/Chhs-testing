<?php
/*
Gibbon, Flexible & Open School System
*/

use Gibbon\Data\Validator;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . getModuleName($_POST['address']) . '/mapping_assessment.php';

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/mapping_assessment.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'upsert') {
        $externalExamId = trim((string) ($_POST['externalExamId'] ?? ''));
        $gibbonInternalAssessmentColumnID = isset($_POST['gibbonInternalAssessmentColumnID']) ? (int) $_POST['gibbonInternalAssessmentColumnID'] : 0;
        $syncMode = $_POST['syncMode'] ?? 'internal_assessment';
        if (!in_array($syncMode, ['internal_assessment', 'markbook', 'both'], true)) {
            $syncMode = 'internal_assessment';
        }

        if ($externalExamId === '' || $gibbonInternalAssessmentColumnID <= 0) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $sql = "
            INSERT INTO gibbonJussExamBridgeAssessmentMap
            (externalExamId, gibbonInternalAssessmentColumnID, gibbonMarkbookColumnID, syncMode)
            VALUES (:externalExamId, :gibbonInternalAssessmentColumnID, NULL, :syncMode)
            ON DUPLICATE KEY UPDATE
                gibbonInternalAssessmentColumnID = VALUES(gibbonInternalAssessmentColumnID),
                syncMode = VALUES(syncMode),
                updatedAt = CURRENT_TIMESTAMP
        ";
        $stmt = $connection2->prepare($sql);
        $stmt->execute([
            'externalExamId' => $externalExamId,
            'gibbonInternalAssessmentColumnID' => $gibbonInternalAssessmentColumnID,
            'syncMode' => $syncMode,
        ]);
    } elseif ($action === 'delete') {
        $id = isset($_POST['gibbonJussExamBridgeAssessmentMapID']) ? (int) $_POST['gibbonJussExamBridgeAssessmentMapID'] : 0;
        if ($id <= 0) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $sql = 'DELETE FROM gibbonJussExamBridgeAssessmentMap WHERE gibbonJussExamBridgeAssessmentMapID = :id';
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
