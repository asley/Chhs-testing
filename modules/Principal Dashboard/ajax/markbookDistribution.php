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

$yearID      = $_GET['yearID']      ?? '';
$yearGroupID = $_GET['yearGroupID'] ?? '';
$formGroupID = $_GET['formGroupID'] ?? '';
$teacherID   = $_GET['teacherID']   ?? '';

if ($yearID === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing yearID']);
    exit;
}

$params  = [':yearID' => $yearID];
$filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

$teacherFilter = '';
if ($teacherID !== '') {
    $teacherFilter = " AND EXISTS (
        SELECT 1 FROM gibbonCourseClassPerson tcp
        WHERE tcp.gibbonCourseClassID = mc.gibbonCourseClassID
          AND tcp.gibbonPersonID = :teacherID
          AND tcp.role = 'Teacher'
    )";
    $params[':teacherID'] = $teacherID;
}

$sql = "SELECT
            CASE
                WHEN studentAvg >= 85 THEN 'A (85-100)'
                WHEN studentAvg >= 70 THEN 'B (70-84)'
                WHEN studentAvg >= 60 THEN 'C (60-69)'
                WHEN studentAvg >= 50 THEN 'D (50-59)'
                ELSE                     'E (0-49)'
            END AS gradeBand,
            COUNT(*) AS studentCount
        FROM (
            SELECT
                se.gibbonPersonID,
                AVG(me.attainmentValue) AS studentAvg
            FROM gibbonStudentEnrolment se
            JOIN gibbonMarkbookEntry me
                ON me.gibbonPersonIDStudent = se.gibbonPersonID
            JOIN gibbonMarkbookColumn mc
                ON mc.gibbonMarkbookColumnID = me.gibbonMarkbookColumnID
            JOIN gibbonCourseClass gc
                ON gc.gibbonCourseClassID = mc.gibbonCourseClassID
            JOIN gibbonCourse c
                ON c.gibbonCourseID = gc.gibbonCourseID
            WHERE se.gibbonSchoolYearID = :yearID
              AND c.gibbonSchoolYearID = :yearID
              AND me.attainmentValue IS NOT NULL
              {$filters}
              {$teacherFilter}
            GROUP BY se.gibbonPersonID
        ) AS studentAverages
        GROUP BY gradeBand
        ORDER BY MIN(studentAvg) DESC";

try {
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure consistent order
    $order  = ['A (85-100)', 'B (70-84)', 'C (60-69)', 'D (50-59)', 'E (0-49)'];
    $mapped = array_column($rows, 'studentCount', 'gradeBand');
    $labels = [];
    $values = [];
    foreach ($order as $band) {
        $labels[] = $band;
        $values[] = (int) ($mapped[$band] ?? 0);
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => ['labels' => $labels, 'values' => $values]]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
