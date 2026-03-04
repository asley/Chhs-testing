<?php
/*
Gibbon, Flexible & Open School System
*/

require_once __DIR__ . '/../../../../gibbon.php';
require_once __DIR__ . '/../../moduleFunctions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    outputJussExamBridgeJson(405, [
        'ok' => false,
        'error' => 'method_not_allowed',
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$expectedPath = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH);

$verification = verifyJussExamBridgeSignedRequest($container, $connection2, $rawBody, $expectedPath);
if (!$verification['ok']) {
    outputJussExamBridgeJson($verification['status'], [
        'ok' => false,
        'error' => $verification['error'],
    ]);
    exit;
}

$schoolYearID = isset($_GET['schoolYearID']) && ctype_digit((string) $_GET['schoolYearID']) ? (int) $_GET['schoolYearID'] : null;
$classID = isset($_GET['classID']) && ctype_digit((string) $_GET['classID']) ? (int) $_GET['classID'] : null;
$updatedAfter = isset($_GET['updatedAfter']) ? trim((string) $_GET['updatedAfter']) : '';

$updatedAfterDate = null;
if ($updatedAfter !== '') {
    $updatedAfterTimestamp = strtotime($updatedAfter);
    if ($updatedAfterTimestamp === false) {
        outputJussExamBridgeJson(400, [
            'ok' => false,
            'error' => 'invalid_updated_after',
        ]);
        exit;
    }

    $updatedAfterDate = date('Y-m-d', $updatedAfterTimestamp);
}

$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$pageSize = isset($_GET['pageSize']) && ctype_digit((string) $_GET['pageSize']) ? (int) $_GET['pageSize'] : 50;
$pageSize = max(1, min(200, $pageSize));
$offset = ($page - 1) * $pageSize;

$where = [];
$params = [];

if ($schoolYearID !== null) {
    $where[] = 'c.gibbonSchoolYearID = :schoolYearID';
    $params['schoolYearID'] = $schoolYearID;
}

if ($classID !== null) {
    $where[] = 'cc.gibbonCourseClassID = :classID';
    $params['classID'] = $classID;
}

if ($updatedAfterDate !== null) {
    $where[] = '(ccpFilter.dateEnrolled >= :updatedAfterDate OR ccpFilter.dateUnenrolled >= :updatedAfterDate)';
    $params['updatedAfterDate'] = $updatedAfterDate;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

try {
    $countSql = "
        SELECT COUNT(DISTINCT cc.gibbonCourseClassID)
        FROM gibbonCourseClass cc
        INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
        LEFT JOIN gibbonCourseClassPerson ccpFilter ON ccpFilter.gibbonCourseClassID = cc.gibbonCourseClassID
        $whereSql
    ";
    $countStmt = $connection2->prepare($countSql);
    $countStmt->execute($params);
    $totalClasses = (int) $countStmt->fetchColumn();

    $classSql = "
        SELECT
            cc.gibbonCourseClassID,
            cc.name AS className,
            cc.nameShort AS classCode,
            cc.reportable AS classReportable,
            cc.attendance AS classAttendance,
            cc.enrolmentMin,
            cc.enrolmentMax,
            c.gibbonCourseID,
            c.gibbonSchoolYearID,
            c.name AS courseName,
            c.nameShort AS courseCode,
            cm.externalCohortId,
            cm.externalClassCode
        FROM gibbonCourseClass cc
        INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
        LEFT JOIN gibbonJussExamBridgeClassMap cm ON cm.gibbonCourseClassID = cc.gibbonCourseClassID
        LEFT JOIN gibbonCourseClassPerson ccpFilter ON ccpFilter.gibbonCourseClassID = cc.gibbonCourseClassID
        $whereSql
        GROUP BY cc.gibbonCourseClassID
        ORDER BY c.nameShort, cc.nameShort
        LIMIT :limit OFFSET :offset
    ";

    $classStmt = $connection2->prepare($classSql);
    foreach ($params as $key => $value) {
        $classStmt->bindValue(':' . $key, $value);
    }
    $classStmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $classStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $classStmt->execute();
    $classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    outputJussExamBridgeJson(500, [
        'ok' => false,
        'error' => 'query_failed',
    ]);
    exit;
}

$classIds = array_values(array_map(static function ($row) {
    return (int) $row['gibbonCourseClassID'];
}, $classes));

$participantsByClass = [];
if (!empty($classIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($classIds), '?'));
    $participantSql = "
        SELECT
            ccp.gibbonCourseClassID,
            ccp.gibbonPersonID,
            ccp.role AS classRole,
            ccp.reportable,
            ccp.dateEnrolled,
            ccp.dateUnenrolled,
            p.status AS personStatus,
            p.firstName,
            p.surname,
            p.preferredName,
            p.email,
            p.studentID,
            p.username,
            p.canLogin
        FROM gibbonCourseClassPerson ccp
        INNER JOIN gibbonPerson p ON p.gibbonPersonID = ccp.gibbonPersonID
        WHERE ccp.gibbonCourseClassID IN ($inPlaceholders)
          AND ccp.role IN ('Student', 'Student - Left')
        ORDER BY ccp.gibbonCourseClassID, p.surname, p.firstName
    ";

    try {
        $participantStmt = $connection2->prepare($participantSql);
        foreach ($classIds as $i => $id) {
            $participantStmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $participantStmt->execute();
        $participants = $participantStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        outputJussExamBridgeJson(500, [
            'ok' => false,
            'error' => 'participant_query_failed',
        ]);
        exit;
    }

    foreach ($participants as $row) {
        $courseClassID = (int) $row['gibbonCourseClassID'];
        if (!isset($participantsByClass[$courseClassID])) {
            $participantsByClass[$courseClassID] = [];
        }

        $participantsByClass[$courseClassID][] = [
            'personId' => (int) $row['gibbonPersonID'],
            'classRole' => $row['classRole'],
            'reportable' => $row['reportable'],
            'dateEnrolled' => $row['dateEnrolled'],
            'dateUnenrolled' => $row['dateUnenrolled'],
            'personStatus' => $row['personStatus'],
            'name' => [
                'firstName' => $row['firstName'],
                'surname' => $row['surname'],
                'preferredName' => $row['preferredName'],
            ],
            'identity' => [
                'email' => $row['email'],
                'studentId' => $row['studentID'],
                'username' => $row['username'],
                'canLogin' => $row['canLogin'],
            ],
        ];
    }
}

$data = [];
foreach ($classes as $classRow) {
    $courseClassID = (int) $classRow['gibbonCourseClassID'];
    $hasMappedExternalId = $classRow['externalCohortId'] !== null && trim((string) $classRow['externalCohortId']) !== '';
    $classExternalId = $hasMappedExternalId ? (string) $classRow['externalCohortId'] : (string) $courseClassID;
    $classExternalIdSource = $hasMappedExternalId ? 'mapped' : 'fallback_classId';
    $mappingStatus = $hasMappedExternalId ? 'mapped' : 'unmapped';

    $data[] = [
        'classId' => $courseClassID,
        'classExternalId' => $classExternalId,
        'classExternalIdSource' => $classExternalIdSource,
        'mappingStatus' => $mappingStatus,
        'externalClassCode' => $classRow['externalClassCode'] !== null ? (string) $classRow['externalClassCode'] : null,
        'schoolYearId' => (int) $classRow['gibbonSchoolYearID'],
        'course' => [
            'courseId' => (int) $classRow['gibbonCourseID'],
            'name' => $classRow['courseName'],
            'code' => $classRow['courseCode'],
        ],
        'class' => [
            'name' => $classRow['className'],
            'code' => $classRow['classCode'],
            'reportable' => $classRow['classReportable'],
            'attendance' => $classRow['classAttendance'],
            'enrolmentMin' => $classRow['enrolmentMin'] !== null ? (int) $classRow['enrolmentMin'] : null,
            'enrolmentMax' => $classRow['enrolmentMax'] !== null ? (int) $classRow['enrolmentMax'] : null,
        ],
        'participants' => $participantsByClass[$courseClassID] ?? [],
    ];
}

outputJussExamBridgeJson(200, [
    'ok' => true,
    'filters' => [
        'schoolYearID' => $schoolYearID,
        'classID' => $classID,
        'updatedAfter' => $updatedAfterDate,
    ],
    'pagination' => [
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $totalClasses,
        'totalPages' => $pageSize > 0 ? (int) ceil($totalClasses / $pageSize) : 0,
    ],
    'data' => $data,
]);
