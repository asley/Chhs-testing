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

if ($yearID === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing yearID']);
    exit;
}

$params  = [':yearID' => $yearID];
$filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);
$yearRange = pdGetSchoolYearDateRange($connection2, $yearID);
$params[':yearStart'] = $yearRange['firstDay'];
$params[':yearEnd'] = $yearRange['lastDay'];

$sql = "SELECT
            p.gibbonPersonID                                          AS personID,
            CONCAT(p.preferredName, ' ', p.surname)                   AS studentName,
            yg.name                                                   AS yearGroup,
            fg.name                                                   AS formGroup,
            ROUND(mb.avgGrade, 1)                                     AS avgGrade,
            COALESCE(att.absences, 0)                                 AS absences
        FROM gibbonStudentEnrolment se
        JOIN gibbonPerson p       ON p.gibbonPersonID    = se.gibbonPersonID
        LEFT JOIN gibbonYearGroup yg ON yg.gibbonYearGroupID = se.gibbonYearGroupID
        LEFT JOIN gibbonFormGroup fg ON fg.gibbonFormGroupID = se.gibbonFormGroupID
        LEFT JOIN (
            SELECT
                me.gibbonPersonIDStudent AS gibbonPersonID,
                AVG(me.attainmentValue) AS avgGrade
            FROM gibbonMarkbookEntry me
            JOIN gibbonMarkbookColumn mc
                ON mc.gibbonMarkbookColumnID = me.gibbonMarkbookColumnID
            JOIN gibbonCourseClass gc
                ON gc.gibbonCourseClassID = mc.gibbonCourseClassID
            JOIN gibbonCourse c
                ON c.gibbonCourseID = gc.gibbonCourseID
            WHERE c.gibbonSchoolYearID = :yearID
              AND me.attainmentValue IS NOT NULL
            GROUP BY me.gibbonPersonIDStudent
        ) mb ON mb.gibbonPersonID = se.gibbonPersonID
        LEFT JOIN (
            SELECT
                al.gibbonPersonID,
                COUNT(*) AS absences
            FROM gibbonAttendanceLogPerson al
            WHERE al.direction = 'Out'
              AND al.date BETWEEN :yearStart AND :yearEnd
            GROUP BY al.gibbonPersonID
        ) att ON att.gibbonPersonID = se.gibbonPersonID
        WHERE se.gibbonSchoolYearID = :yearID
          AND p.status = 'Full'
          {$filters}
          AND (
            (mb.avgGrade IS NOT NULL AND mb.avgGrade < 50)
            OR COALESCE(att.absences, 0) > 18
          )
        ORDER BY mb.avgGrade IS NULL, mb.avgGrade ASC, absences DESC
        LIMIT 100";

try {
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'personID'  => $row['personID'],
            'name'      => $row['studentName'],
            'yearGroup' => $row['yearGroup'],
            'formGroup' => $row['formGroup'],
            'avgGrade'  => $row['avgGrade'] !== null ? (float) $row['avgGrade'] : null,
            'absences'  => (int) $row['absences'],
        ];
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
