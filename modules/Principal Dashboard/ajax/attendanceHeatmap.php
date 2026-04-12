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
$dateFrom    = $_GET['dateFrom']    ?? date('Y-m-d', strtotime('-90 days'));
$dateTo      = $_GET['dateTo']      ?? date('Y-m-d');

if ($yearID === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing yearID']);
    exit;
}

$params  = [':yearID' => $yearID, ':dateFrom' => $dateFrom, ':dateTo' => $dateTo];
$filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

$sql = "SELECT
            DAYNAME(al.date)                    AS dayOfWeek,
            DATE_FORMAT(al.date, '%Y-W%u')      AS weekLabel,
            COUNT(al.gibbonAttendanceLogPersonID) AS absences
        FROM gibbonAttendanceLogPerson al
        JOIN gibbonStudentEnrolment se
            ON se.gibbonPersonID = al.gibbonPersonID
        WHERE se.gibbonSchoolYearID = :yearID
          AND al.direction = 'Out'
          AND al.date BETWEEN :dateFrom AND :dateTo
          {$filters}
        GROUP BY dayOfWeek, weekLabel
        ORDER BY MIN(al.date)";

try {
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build ApexCharts heatmap series: one series per day-of-week
    $days  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $weeks = [];
    $grid  = [];

    foreach ($rows as $row) {
        $weeks[$row['weekLabel']] = true;
        $grid[$row['dayOfWeek']][$row['weekLabel']] = (int) $row['absences'];
    }

    $weekList = array_keys($weeks);
    sort($weekList);

    $series = [];
    foreach ($days as $day) {
        $data = [];
        foreach ($weekList as $wk) {
            $data[] = ['x' => $wk, 'y' => $grid[$day][$wk] ?? 0];
        }
        $series[] = ['name' => $day, 'data' => $data];
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => ['series' => $series]]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
