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

$yearID = $_GET['yearID'] ?? '';
$yearGroupID = $_GET['yearGroupID'] ?? '';
$formGroupID = $_GET['formGroupID'] ?? '';
$teacherID = $_GET['teacherID'] ?? '';
$gradeBand = trim((string) ($_GET['gradeBand'] ?? ''));

if ($yearID === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing yearID']);
    exit;
}

$bandRanges = [
    'A (85-100)' => ['min' => 85.0, 'max' => 100.0],
    'B (70-84)' => ['min' => 70.0, 'max' => 84.9999],
    'C (60-69)' => ['min' => 60.0, 'max' => 69.9999],
    'D (50-59)' => ['min' => 50.0, 'max' => 59.9999],
    'E (0-49)' => ['min' => 0.0, 'max' => 49.9999],
];

if (!isset($bandRanges[$gradeBand])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid gradeBand']);
    exit;
}

$params = [
    ':yearID' => $yearID,
    ':bandMin' => $bandRanges[$gradeBand]['min'],
    ':bandMax' => $bandRanges[$gradeBand]['max'],
];
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
            sa.gibbonPersonID AS personID,
            CONCAT(p.preferredName, ' ', p.surname) AS studentName,
            fg.name AS formGroup,
            yg.name AS yearGroup,
            ROUND(sa.studentAvg, 1) AS avgGrade
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
            HAVING studentAvg >= :bandMin AND studentAvg <= :bandMax
        ) sa
        JOIN gibbonPerson p
            ON p.gibbonPersonID = sa.gibbonPersonID
        JOIN gibbonStudentEnrolment se
            ON se.gibbonPersonID = sa.gibbonPersonID
           AND se.gibbonSchoolYearID = :yearID
        LEFT JOIN gibbonYearGroup yg
            ON yg.gibbonYearGroupID = se.gibbonYearGroupID
        LEFT JOIN gibbonFormGroup fg
            ON fg.gibbonFormGroupID = se.gibbonFormGroupID
        WHERE p.status = 'Full'
        ORDER BY sa.studentAvg DESC, p.surname ASC, p.preferredName ASC
        LIMIT 500";

try {
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'personID' => (int) $row['personID'],
            'name' => (string) $row['studentName'],
            'formGroup' => $row['formGroup'] ?: '',
            'yearGroup' => $row['yearGroup'] ?: '',
            'avgGrade' => $row['avgGrade'] !== null ? (float) $row['avgGrade'] : null,
        ];
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => $result,
        'meta' => [
            'band' => $gradeBand,
            'returned' => count($result),
        ],
    ]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
