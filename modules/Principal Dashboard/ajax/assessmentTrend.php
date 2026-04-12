<?php
ob_start();
require_once __DIR__ . '/../../../gibbon.php';
require_once __DIR__ . '/../moduleFunctions.php';

header('Content-Type: application/json');

if (!isActionAccessible($guid, $connection2, '/modules/Principal Dashboard/dashboard.php')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$yearID      = $_GET['yearID']      ?? '';
$yearGroupID = $_GET['yearGroupID'] ?? '';
$formGroupID = $_GET['formGroupID'] ?? '';
$teacherID   = $_GET['teacherID']   ?? '';

if ($yearID === '') {
    echo json_encode(['success' => false, 'message' => 'Missing yearID']);
    exit;
}

$params  = [':yearID' => $yearID];
$filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

$teacherFilter = '';
if ($teacherID !== '') {
    $teacherFilter = ' AND EXISTS (
        SELECT 1 FROM gibbonCourseClassPerson tcp
        WHERE tcp.gibbonCourseClassID = iac.gibbonCourseClassID
          AND tcp.gibbonPersonID = :teacherID
          AND tcp.role = \'Teacher\'
    )';
    $params[':teacherID'] = $teacherID;
}

// attainmentValue is stored as e.g. '81%' — strip % and average as float
$sql = "SELECT
            iac.gibbonInternalAssessmentColumnID AS columnID,
            iac.name                             AS columnName,
            ROUND(AVG(CAST(REPLACE(iae.attainmentValue, '%', '') AS DECIMAL(6,2))), 1) AS avgPct
        FROM gibbonInternalAssessmentColumn iac
        JOIN gibbonCourseClass gc
            ON gc.gibbonCourseClassID = iac.gibbonCourseClassID
        JOIN gibbonCourse c
            ON c.gibbonCourseID = gc.gibbonCourseID
        JOIN gibbonInternalAssessmentEntry iae
            ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
        JOIN gibbonStudentEnrolment se
            ON se.gibbonPersonID = iae.gibbonPersonIDStudent
           AND se.gibbonSchoolYearID = :yearID
        WHERE c.gibbonSchoolYearID = :yearID
          AND (iac.locked IS NULL OR iac.locked = 'N')
          AND iae.attainmentValue IS NOT NULL
          AND iae.attainmentValue != ''
          {$filters}
          {$teacherFilter}
        GROUP BY iac.gibbonInternalAssessmentColumnID
        ORDER BY iac.name";

try {
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo json_encode(['success' => true, 'data' => ['labels' => [], 'columnIDs' => [], 'series' => []]]);
        exit;
    }

    // Build a single trend line to avoid overcrowded legends.
    $columnOrder = [];
    $columnIDs = [];
    $values = [];

    foreach ($rows as $row) {
        $columnOrder[] = $row['columnName'];
        $columnIDs[] = $row['columnID'];
        $values[] = (float) $row['avgPct'];
    }

    $series = [
        [
            'name' => 'School Avg %',
            'data' => $values,
        ],
    ];

    ob_clean();
    echo json_encode([
        'success' => true,
        'data'    => [
            'labels'    => $columnOrder,
            'columnIDs' => $columnIDs,
            'series'    => $series,
        ],
    ]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
