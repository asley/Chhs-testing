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
            DATE(al.date) AS logDate,
            COUNT(DISTINCT se.gibbonPersonID) AS totalEnrolled,
            COUNT(DISTINCT CASE WHEN al.direction = 'Out' THEN al.gibbonPersonID END) AS absent
        FROM gibbonStudentEnrolment se
        LEFT JOIN gibbonAttendanceLogPerson al
            ON al.gibbonPersonID = se.gibbonPersonID
           AND al.date BETWEEN :dateFrom AND :dateTo
        WHERE se.gibbonSchoolYearID = :yearID
          {$filters}
          AND al.date IS NOT NULL
        GROUP BY logDate
        ORDER BY logDate";

try {
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $values = [];
    foreach ($rows as $row) {
        $labels[] = $row['logDate'];
        $total    = (int) $row['totalEnrolled'];
        $absent   = (int) $row['absent'];
        $values[] = $total > 0 ? round((($total - $absent) / $total) * 100, 1) : null;
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => ['labels' => $labels, 'values' => $values]]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
