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
$params[':yearEnd'] = pdGetAttendanceDateUpperBound($yearRange['lastDay']);
$attendanceContextFilter = pdGetAttendanceContextFilter($connection2, 'al');
$passMark = 65.0;

$sql = "WITH cohort AS (
            SELECT
                se.gibbonPersonID,
                se.gibbonYearGroupID,
                se.gibbonFormGroupID
            FROM gibbonStudentEnrolment se
            JOIN gibbonPerson p
                ON p.gibbonPersonID = se.gibbonPersonID
            WHERE se.gibbonSchoolYearID = :yearID
              AND p.status = 'Full'
              {$filters}
        ),
        markbook AS (
            SELECT
                me.gibbonPersonIDStudent AS gibbonPersonID,
                AVG(me.attainmentValue) AS avgGrade
            FROM gibbonMarkbookEntry me
            JOIN cohort co
                ON co.gibbonPersonID = me.gibbonPersonIDStudent
            JOIN gibbonMarkbookColumn mc
                ON mc.gibbonMarkbookColumnID = me.gibbonMarkbookColumnID
            JOIN gibbonCourseClass gc
                ON gc.gibbonCourseClassID = mc.gibbonCourseClassID
            JOIN gibbonCourse c
                ON c.gibbonCourseID = gc.gibbonCourseID
            WHERE c.gibbonSchoolYearID = :yearID
              AND me.attainmentValue IS NOT NULL
            GROUP BY me.gibbonPersonIDStudent
        ),
        last_log AS (
            SELECT
                al.gibbonPersonID,
                al.date,
                MAX(al.timestampTaken) AS maxTimestamp
            FROM gibbonAttendanceLogPerson al
            JOIN cohort co
                ON co.gibbonPersonID = al.gibbonPersonID
            WHERE al.date BETWEEN :yearStart AND :yearEnd
              {$attendanceContextFilter}
            GROUP BY al.gibbonPersonID, al.date
        ),
        last_log_row AS (
            SELECT
                al.gibbonPersonID,
                al.date,
                MAX(al.gibbonAttendanceLogPersonID) AS maxLogID
            FROM gibbonAttendanceLogPerson al
            JOIN last_log ll
                ON ll.gibbonPersonID = al.gibbonPersonID
               AND ll.date = al.date
               AND ll.maxTimestamp = al.timestampTaken
            WHERE al.date BETWEEN :yearStart AND :yearEnd
              {$attendanceContextFilter}
            GROUP BY al.gibbonPersonID, al.date
        ),
        attendance AS (
            SELECT
                al.gibbonPersonID,
                COUNT(*) AS absences
            FROM gibbonAttendanceLogPerson al
            JOIN gibbonAttendanceCode ac
                ON ac.name = al.type
            JOIN last_log_row llr
                ON llr.maxLogID = al.gibbonAttendanceLogPersonID
            WHERE al.date BETWEEN :yearStart AND :yearEnd
              AND ac.direction = 'Out'
              AND ac.scope = 'Offsite'
            GROUP BY al.gibbonPersonID
        ),
        risk AS (
            SELECT
                p.gibbonPersonID AS personID,
                CONCAT(p.preferredName, ' ', p.surname) AS studentName,
                yg.name AS yearGroup,
                fg.name AS formGroup,
                ROUND(mb.avgGrade, 1) AS avgGrade,
                COALESCE(att.absences, 0) AS absences
            FROM cohort co
            JOIN gibbonPerson p
                ON p.gibbonPersonID = co.gibbonPersonID
            LEFT JOIN gibbonYearGroup yg
                ON yg.gibbonYearGroupID = co.gibbonYearGroupID
            LEFT JOIN gibbonFormGroup fg
                ON fg.gibbonFormGroupID = co.gibbonFormGroupID
            LEFT JOIN markbook mb
                ON mb.gibbonPersonID = co.gibbonPersonID
            LEFT JOIN attendance att
                ON att.gibbonPersonID = co.gibbonPersonID
            WHERE (mb.avgGrade IS NOT NULL AND mb.avgGrade < {$passMark})
               OR COALESCE(att.absences, 0) > 18
        )
        SELECT
            personID,
            studentName,
            yearGroup,
            formGroup,
            avgGrade,
            absences,
            COUNT(*) OVER() AS totalAtRisk
        FROM risk
        ORDER BY avgGrade IS NULL, avgGrade ASC, absences DESC
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

    $totalAtRisk = 0;
    if (!empty($rows)) {
        $totalAtRisk = (int) ($rows[0]['totalAtRisk'] ?? 0);
    }
    if ($totalAtRisk === 0 && !empty($rows)) {
        $totalAtRisk = count($rows);
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => $result,
        'meta' => [
            'total' => (int) $totalAtRisk,
            'returned' => count($result),
            'limited' => ((int) $totalAtRisk > count($result)),
        ],
    ]);
} catch (Exception $e) {
    ob_clean();
    error_log('PrincipalDashboard: ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
