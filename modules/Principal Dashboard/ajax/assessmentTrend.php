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
$assessmentName = trim($_GET['assessmentName'] ?? '');

if ($yearID === '') {
    echo json_encode(['success' => false, 'message' => 'Missing yearID']);
    exit;
}

$baseParams  = [':yearID' => $yearID];
$filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $baseParams);

$teacherFilter = '';
if ($teacherID !== '') {
    $teacherFilter = ' AND EXISTS (
        SELECT 1 FROM gibbonCourseClassPerson tcp
        WHERE tcp.gibbonCourseClassID = iac.gibbonCourseClassID
          AND tcp.gibbonPersonID = :teacherID
          AND tcp.role = \'Teacher\'
    )';
    $baseParams[':teacherID'] = $teacherID;
}

$assessmentFilter = '';
$params = $baseParams;
if ($assessmentName !== '') {
    $assessmentFilter = ' AND iac.name = :assessmentName';
    $params[':assessmentName'] = $assessmentName;
}

// attainmentValue is stored as e.g. '81%' — strip % and cast to numeric.
// Non-numeric entries (eg Abs/Incomplete) are intentionally treated as 0.
$scoreExpr = "CASE
    WHEN TRIM(iae.attainmentValue) REGEXP '^[0-9]+([.][0-9]+)?%?$'
        THEN CAST(REPLACE(TRIM(iae.attainmentValue), '%', '') AS DECIMAL(6,2))
    ELSE 0
END";

$sql = "SELECT
            iac.gibbonInternalAssessmentColumnID AS columnID,
            iac.name                             AS columnName,
            gc.name                              AS className,
            SUM(CASE WHEN {$scoreExpr} < 50 THEN 1 ELSE 0 END) AS lowCnt,
            SUM(CASE WHEN {$scoreExpr} >= 50 AND {$scoreExpr} < 65 THEN 1 ELSE 0 END) AS amberCnt,
            SUM(CASE WHEN {$scoreExpr} >= 65 AND {$scoreExpr} < 80 THEN 1 ELSE 0 END) AS greenCnt,
            SUM(CASE WHEN {$scoreExpr} >= 80 THEN 1 ELSE 0 END) AS blueCnt,
            COUNT(*) AS totalCnt,
            ROUND(AVG({$scoreExpr}), 1) AS avgPct
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
          {$assessmentFilter}
          {$filters}
          {$teacherFilter}
        GROUP BY iac.gibbonInternalAssessmentColumnID
        ORDER BY iac.name";

$sqlOptions = "SELECT DISTINCT iac.name AS assessmentName
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
                 {$teacherFilter}
               ORDER BY iac.name";

try {
    $stmtOptions = $connection2->prepare($sqlOptions);
    $stmtOptions->execute($baseParams);
    $assessmentOptions = array_values(array_filter(array_map(static function ($row) {
        return isset($row['assessmentName']) ? trim((string) $row['assessmentName']) : '';
    }, $stmtOptions->fetchAll(PDO::FETCH_ASSOC)), static function ($name) {
        return $name !== '';
    }));

    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo json_encode(['success' => true, 'data' => [
            'labels' => [],
            'columnIDs' => [],
            'series' => [],
            'assessmentOptions' => $assessmentOptions,
            'selectedAssessment' => $assessmentName,
        ]]);
        exit;
    }

    $columnOrder = [];
    $columnIDs = [];
    $lowSeries = [];
    $amberSeries = [];
    $greenSeries = [];
    $blueSeries = [];

    $totalEntries = 0;
    $totalLow = 0;
    $totalAmber = 0;
    $totalBlue = 0;
    $weightedAvgTotal = 0.0;

    foreach ($rows as $row) {
        $total = (int) ($row['totalCnt'] ?? 0);
        $low = (int) ($row['lowCnt'] ?? 0);
        $amber = (int) ($row['amberCnt'] ?? 0);
        $green = (int) ($row['greenCnt'] ?? 0);
        $blue = (int) ($row['blueCnt'] ?? 0);
        $avg = (float) ($row['avgPct'] ?? 0);

        $columnOrder[] = trim(($row['columnName'] ?? '') . ' - ' . ($row['className'] ?? ''));
        $columnIDs[] = $row['columnID'];
        $lowSeries[] = $total > 0 ? round(($low / $total) * 100, 1) : 0.0;
        $amberSeries[] = $total > 0 ? round(($amber / $total) * 100, 1) : 0.0;
        $greenSeries[] = $total > 0 ? round(($green / $total) * 100, 1) : 0.0;
        $blueSeries[] = $total > 0 ? round(($blue / $total) * 100, 1) : 0.0;

        $totalEntries += $total;
        $totalLow += $low;
        $totalAmber += $amber;
        $totalBlue += $blue;
        $weightedAvgTotal += ($avg * $total);
    }

    $series = [
        [
            'name' => 'Below 50%',
            'data' => $lowSeries,
        ],
        [
            'name' => '50-64%',
            'data' => $amberSeries,
        ],
        [
            'name' => '65-79%',
            'data' => $greenSeries,
        ],
        [
            'name' => '80%+',
            'data' => $blueSeries,
        ],
    ];

    $overallAvg = $totalEntries > 0 ? round($weightedAvgTotal / $totalEntries, 1) : 0.0;
    $below65Pct = $totalEntries > 0 ? round((($totalLow + $totalAmber) / $totalEntries) * 100, 1) : 0.0;
    $strongPct = $totalEntries > 0 ? round(($totalBlue / $totalEntries) * 100, 1) : 0.0;

    ob_clean();
    echo json_encode([
        'success' => true,
        'data'    => [
            'labels'    => $columnOrder,
            'columnIDs' => $columnIDs,
            'series'    => $series,
            'assessmentOptions' => $assessmentOptions,
            'selectedAssessment' => $assessmentName,
            'summary'   => [
                'avgScore' => $overallAvg,
                'below65Pct' => $below65Pct,
                'strongPct' => $strongPct,
                'totalEntries' => $totalEntries,
                'columnsTracked' => count($columnOrder),
            ],
        ],
    ]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
