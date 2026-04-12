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

$teacherFilterIA = '';
$teacherFilterMB = '';
if ($teacherID !== '') {
    $teacherFilterIA = " AND EXISTS (
        SELECT 1 FROM gibbonCourseClassPerson tcp
        WHERE tcp.gibbonCourseClassID = iac.gibbonCourseClassID
          AND tcp.gibbonPersonID = :teacherID
          AND tcp.role = 'Teacher'
    )";
    $teacherFilterMB = " AND EXISTS (
        SELECT 1 FROM gibbonCourseClassPerson tcp
        WHERE tcp.gibbonCourseClassID = mc.gibbonCourseClassID
          AND tcp.gibbonPersonID = :teacherID
          AND tcp.role = 'Teacher'
    )";
    $params[':teacherID'] = $teacherID;
}

// Internal Assessment class averages, student-weighted:
// 1) average each student's IA entries in a class, 2) average student averages.
$sqlIA = "SELECT
              studentClassAverages.className,
              ROUND(AVG(studentClassAverages.studentAvg), 1) AS avgPct
          FROM (
              SELECT
                  gc.gibbonCourseClassID,
                  gc.name AS className,
                  iae.gibbonPersonIDStudent,
                  AVG(
                      CASE
                          WHEN TRIM(iae.attainmentValue) REGEXP '^[0-9]+([.][0-9]+)?%?$'
                              THEN CAST(REPLACE(TRIM(iae.attainmentValue), '%', '') AS DECIMAL(6,2))
                          ELSE 0
                      END
                  ) AS studentAvg
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
                AND iae.attainmentValue IS NOT NULL
                AND iae.attainmentValue != ''
                {$filters}
                {$teacherFilterIA}
              GROUP BY gc.gibbonCourseClassID, iae.gibbonPersonIDStudent
          ) AS studentClassAverages
          GROUP BY studentClassAverages.gibbonCourseClassID, studentClassAverages.className
          ORDER BY studentClassAverages.className";

// Markbook class averages, student-weighted:
// 1) average each student's markbook entries in a class, 2) average student averages.
$sqlMB = "SELECT
              studentClassAverages.className,
              ROUND(AVG(studentClassAverages.studentAvg), 1) AS avgPct
          FROM (
              SELECT
                  gc.gibbonCourseClassID,
                  gc.name AS className,
                  me.gibbonPersonIDStudent,
                  AVG(me.attainmentValue) AS studentAvg
              FROM gibbonMarkbookEntry me
              JOIN gibbonMarkbookColumn mc
                  ON mc.gibbonMarkbookColumnID = me.gibbonMarkbookColumnID
              JOIN gibbonCourseClass gc
                  ON gc.gibbonCourseClassID = mc.gibbonCourseClassID
              JOIN gibbonCourse c
                  ON c.gibbonCourseID = gc.gibbonCourseID
              JOIN gibbonStudentEnrolment se
                  ON se.gibbonPersonID = me.gibbonPersonIDStudent
                 AND se.gibbonSchoolYearID = :yearID
              WHERE c.gibbonSchoolYearID = :yearID
                AND me.attainmentValue IS NOT NULL
                {$filters}
                {$teacherFilterMB}
              GROUP BY gc.gibbonCourseClassID, me.gibbonPersonIDStudent
          ) AS studentClassAverages
          GROUP BY studentClassAverages.gibbonCourseClassID, studentClassAverages.className
          ORDER BY studentClassAverages.className";

try {
    $stmtIA = $connection2->prepare($sqlIA);
    $stmtIA->execute($params);
    $iaRows = $stmtIA->fetchAll(PDO::FETCH_ASSOC);

    $stmtMB = $connection2->prepare($sqlMB);
    $stmtMB->execute($params);
    $mbRows = $stmtMB->fetchAll(PDO::FETCH_ASSOC);

    // Merge on class name
    $iaMapped = array_column($iaRows, 'avgPct', 'className');
    $mbMapped = array_column($mbRows, 'avgPct', 'className');
    $allClasses = array_unique(array_merge(array_keys($iaMapped), array_keys($mbMapped)));
    sort($allClasses);

    $labels   = [];
    $iaData   = [];
    $mbData   = [];
    foreach ($allClasses as $cls) {
        $labels[] = $cls;
        $iaData[] = isset($iaMapped[$cls]) ? (float) $iaMapped[$cls] : null;
        $mbData[] = isset($mbMapped[$cls]) ? (float) $mbMapped[$cls] : null;
    }

    echo json_encode([
        'success' => true,
        'data'    => ['labels' => $labels, 'ia' => $iaData, 'markbook' => $mbData],
    ]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
