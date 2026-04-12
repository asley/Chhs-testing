<?php
ob_start();
require_once __DIR__ . '/../../../gibbon.php';
require_once __DIR__ . '/../moduleFunctions.php';

header('Content-Type: application/json');

if (!isActionAccessible($guid, $connection2, '/modules/Principal Dashboard/dashboard.php')) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$columnID = $_GET['columnID'] ?? '';

if ($columnID === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing columnID']);
    exit;
}

// Verify column exists, is not locked, AND belongs to a class in the current session's school year
$sessionYearID = $_SESSION[$guid]['gibbonSchoolYearID'] ?? '';

$sqlCheck = "SELECT iac.locked
             FROM gibbonInternalAssessmentColumn iac
             JOIN gibbonCourseClass gc  ON gc.gibbonCourseClassID  = iac.gibbonCourseClassID
             JOIN gibbonCourse c        ON c.gibbonCourseID        = gc.gibbonCourseID
             WHERE iac.gibbonInternalAssessmentColumnID = :id
               AND c.gibbonSchoolYearID = :yearID";
$stmtCheck = $connection2->prepare($sqlCheck);
$stmtCheck->execute([':id' => $columnID, ':yearID' => $sessionYearID]);
$col = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$col || ($col['locked'] ?? 'N') === 'Y') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Column not found or access denied']);
    exit;
}

// attainmentValue stored as '81%' — strip % sign for numeric sort/display
$sql = "SELECT
            p.gibbonPersonID                                                          AS personID,
            CONCAT(p.preferredName, ' ', p.surname)                                   AS studentName,
            ROUND(CAST(REPLACE(iae.attainmentValue, '%', '') AS DECIMAL(6,2)), 1)     AS scorePct,
            iae.comment                                                                AS comment,
            COUNT(*) OVER (
                PARTITION BY iae.gibbonInternalAssessmentColumnID
            )                                                                         AS classTotal
        FROM gibbonInternalAssessmentEntry iae
        JOIN gibbonInternalAssessmentColumn iac
            ON iac.gibbonInternalAssessmentColumnID = iae.gibbonInternalAssessmentColumnID
        JOIN gibbonPerson p
            ON p.gibbonPersonID = iae.gibbonPersonID
        WHERE iae.gibbonInternalAssessmentColumnID = :columnID
          AND iae.attainmentValue IS NOT NULL
          AND iae.attainmentValue != ''
        ORDER BY scorePct DESC";

try {
    $stmt = $connection2->prepare($sql);
    $stmt->execute([':columnID' => $columnID]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add rank manually (handles ties correctly)
    $result = [];
    $rank   = 1;
    foreach ($rows as $i => $row) {
        $result[] = [
            'personID'   => $row['personID'],
            'name'       => $row['studentName'],
            'scorePct'   => (float) $row['scorePct'],
            'rank'       => $rank++,
            'classTotal' => (int) $row['classTotal'],
            'comment'    => $row['comment'] ?? '',
        ];
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
