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

$columnID    = $_GET['columnID'] ?? '';
$yearID      = $_GET['yearID'] ?? '';
$yearGroupID = $_GET['yearGroupID'] ?? '';
$formGroupID = $_GET['formGroupID'] ?? '';
$teacherID   = $_GET['teacherID'] ?? '';

if ($columnID === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing columnID']);
    exit;
}

// Use selected year from filters when available; fall back to current session year.
$sessionYearID = $_SESSION[$guid]['gibbonSchoolYearID'] ?? '';
if ($yearID === '') {
    $yearID = $sessionYearID;
}
if ($yearID === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing yearID']);
    exit;
}

$params = [':yearID' => $yearID];
$enrolFilters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

$teacherFilter = '';
if ($teacherID !== '') {
    $teacherFilter = " AND EXISTS (
        SELECT 1
        FROM gibbonCourseClassPerson tcp
        WHERE tcp.gibbonCourseClassID = iac.gibbonCourseClassID
          AND tcp.gibbonPersonID = :teacherID
          AND tcp.role = 'Teacher'
    )";
    $params[':teacherID'] = $teacherID;
}

$sqlCheck = "SELECT iac.gibbonInternalAssessmentColumnID
             FROM gibbonInternalAssessmentColumn iac
             JOIN gibbonCourseClass gc  ON gc.gibbonCourseClassID  = iac.gibbonCourseClassID
             JOIN gibbonCourse c        ON c.gibbonCourseID        = gc.gibbonCourseID
             WHERE iac.gibbonInternalAssessmentColumnID = :id
               AND c.gibbonSchoolYearID = :yearID
               {$teacherFilter}";
$stmtCheck = $connection2->prepare($sqlCheck);
$checkParams = [
    ':id' => $columnID,
    ':yearID' => $yearID,
];
if ($teacherID !== '') {
    $checkParams[':teacherID'] = $teacherID;
}
$stmtCheck->execute($checkParams);
$col = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$col) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Column not found or access denied']);
    exit;
}

// attainmentValue stored as '81%' — strip % sign for numeric sort/display.
$sql = "SELECT
            p.gibbonPersonID                                                          AS personID,
            CONCAT(p.preferredName, ' ', p.surname)                                   AS studentName,
            ROUND(CAST(REPLACE(iae.attainmentValue, '%', '') AS DECIMAL(6,2)), 1)     AS scorePct,
            iae.comment                                                                AS comment,
            se.gibbonYearGroupID                                                       AS yearGroupID,
            se.gibbonFormGroupID                                                       AS formGroupID
        FROM gibbonInternalAssessmentEntry iae
        JOIN gibbonInternalAssessmentColumn iac
            ON iac.gibbonInternalAssessmentColumnID = iae.gibbonInternalAssessmentColumnID
        JOIN gibbonStudentEnrolment se
            ON se.gibbonPersonID = iae.gibbonPersonIDStudent
           AND se.gibbonSchoolYearID = :yearID
        JOIN gibbonPerson p
            ON p.gibbonPersonID = iae.gibbonPersonIDStudent
        WHERE iae.gibbonInternalAssessmentColumnID = :columnID
          AND iae.attainmentValue IS NOT NULL
          AND iae.attainmentValue != ''
          {$teacherFilter}
          {$enrolFilters}
        ORDER BY scorePct DESC, p.surname ASC, p.preferredName ASC";

try {
    $stmt = $connection2->prepare($sql);
    $queryParams = $params;
    $queryParams[':columnID'] = $columnID;
    $stmt->execute($queryParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add rank manually for deterministic ordering.
    $result = [];
    $rank   = 1;
    $classTotal = count($rows);
    foreach ($rows as $i => $row) {
        $result[] = [
            'personID'   => $row['personID'],
            'name'       => $row['studentName'],
            'scorePct'   => (float) $row['scorePct'],
            'rank'       => $rank++,
            'classTotal' => $classTotal,
            'comment'    => $row['comment'] ?? '',
        ];
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
