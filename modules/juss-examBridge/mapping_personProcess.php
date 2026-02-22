<?php
/*
Gibbon, Flexible & Open School System
*/

use Gibbon\Data\Validator;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . getModuleName($_POST['address']) . '/mapping_person.php';

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/mapping_person.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'upsert') {
        $externalUserId = trim((string) ($_POST['externalUserId'] ?? ''));
        $externalEmail = trim((string) ($_POST['externalEmail'] ?? ''));
        $gibbonPersonID = isset($_POST['gibbonPersonID']) ? (int) $_POST['gibbonPersonID'] : 0;
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($externalUserId === '' || $gibbonPersonID <= 0) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $sql = "
            INSERT INTO gibbonJussExamBridgePersonMap
            (gibbonPersonID, externalUserId, externalEmail, status)
            VALUES (:gibbonPersonID, :externalUserId, :externalEmail, :status)
            ON DUPLICATE KEY UPDATE
                gibbonPersonID = VALUES(gibbonPersonID),
                externalEmail = VALUES(externalEmail),
                status = VALUES(status),
                updatedAt = CURRENT_TIMESTAMP
        ";
        $stmt = $connection2->prepare($sql);
        $stmt->execute([
            'gibbonPersonID' => $gibbonPersonID,
            'externalUserId' => $externalUserId,
            'externalEmail' => $externalEmail !== '' ? $externalEmail : null,
            'status' => $status,
        ]);
    } elseif ($action === 'delete') {
        $id = isset($_POST['gibbonJussExamBridgePersonMapID']) ? (int) $_POST['gibbonJussExamBridgePersonMapID'] : 0;
        if ($id <= 0) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $sql = 'DELETE FROM gibbonJussExamBridgePersonMap WHERE gibbonJussExamBridgePersonMapID = :id';
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
