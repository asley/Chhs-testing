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
$personID = isset($_GET['personID']) && ctype_digit((string) $_GET['personID']) ? (int) $_GET['personID'] : null;
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

$where[] = "ccp.role IN ('Student', 'Student - Left')";

if ($schoolYearID !== null) {
    $where[] = 'c.gibbonSchoolYearID = :schoolYearID';
    $params['schoolYearID'] = $schoolYearID;
}

if ($classID !== null) {
    $where[] = 'ccp.gibbonCourseClassID = :classID';
    $params['classID'] = $classID;
}

if ($personID !== null) {
    $where[] = 'ccp.gibbonPersonID = :personID';
    $params['personID'] = $personID;
}

if ($updatedAfterDate !== null) {
    $where[] = '(ccp.dateEnrolled >= :updatedAfterDate OR ccp.dateUnenrolled >= :updatedAfterDate)';
    $params['updatedAfterDate'] = $updatedAfterDate;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

try {
    $countSql = "
        SELECT COUNT(*)
        FROM gibbonCourseClassPerson ccp
        INNER JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
        INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
        INNER JOIN gibbonPerson p ON p.gibbonPersonID = ccp.gibbonPersonID
        $whereSql
    ";
    $countStmt = $connection2->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int) $countStmt->fetchColumn();

    $dataSql = "
        SELECT
            ccp.gibbonCourseClassID,
            ccp.gibbonPersonID,
            ccp.role AS classRole,
            ccp.reportable,
            ccp.dateEnrolled,
            ccp.dateUnenrolled,
            cc.name AS className,
            cc.nameShort AS classCode,
            c.gibbonCourseID,
            c.gibbonSchoolYearID,
            c.name AS courseName,
            c.nameShort AS courseCode,
            cm.externalCohortId,
            cm.externalClassCode,
            p.status AS personStatus,
            p.firstName,
            p.surname,
            p.preferredName,
            p.studentID,
            p.email,
            p.username,
            p.canLogin
        FROM gibbonCourseClassPerson ccp
        INNER JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
        INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
        LEFT JOIN gibbonJussExamBridgeClassMap cm ON cm.gibbonCourseClassID = ccp.gibbonCourseClassID
        INNER JOIN gibbonPerson p ON p.gibbonPersonID = ccp.gibbonPersonID
        $whereSql
        ORDER BY c.gibbonSchoolYearID DESC, cc.gibbonCourseClassID, ccp.gibbonPersonID
        LIMIT :limit OFFSET :offset
    ";

    $dataStmt = $connection2->prepare($dataSql);
    foreach ($params as $key => $value) {
        $dataStmt->bindValue(':' . $key, $value);
    }
    $dataStmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    outputJussExamBridgeJson(500, [
        'ok' => false,
        'error' => 'query_failed',
    ]);
    exit;
}

$data = [];
foreach ($rows as $row) {
    $courseClassID = (int) $row['gibbonCourseClassID'];
    $hasMappedExternalId = $row['externalCohortId'] !== null && trim((string) $row['externalCohortId']) !== '';
    $classExternalId = $hasMappedExternalId ? (string) $row['externalCohortId'] : (string) $courseClassID;
    $classExternalIdSource = $hasMappedExternalId ? 'mapped' : 'fallback_classId';
    $mappingStatus = $hasMappedExternalId ? 'mapped' : 'unmapped';

    $data[] = [
        'classId' => $courseClassID,
        'classExternalId' => $classExternalId,
        'classExternalIdSource' => $classExternalIdSource,
        'mappingStatus' => $mappingStatus,
        'externalClassCode' => $row['externalClassCode'] !== null ? (string) $row['externalClassCode'] : null,
        'schoolYearId' => (int) $row['gibbonSchoolYearID'],
        'course' => [
            'courseId' => (int) $row['gibbonCourseID'],
            'name' => $row['courseName'],
            'code' => $row['courseCode'],
        ],
        'class' => [
            'name' => $row['className'],
            'code' => $row['classCode'],
        ],
        'person' => [
            'personId' => (int) $row['gibbonPersonID'],
            'status' => $row['personStatus'],
            'name' => [
                'firstName' => $row['firstName'],
                'surname' => $row['surname'],
                'preferredName' => $row['preferredName'],
            ],
            'identity' => [
                'studentId' => $row['studentID'],
                'email' => $row['email'],
                'username' => $row['username'],
                'canLogin' => $row['canLogin'],
            ],
        ],
        'enrollment' => [
            'role' => $row['classRole'],
            'reportable' => $row['reportable'],
            'dateEnrolled' => $row['dateEnrolled'],
            'dateUnenrolled' => $row['dateUnenrolled'],
        ],
    ];
}

outputJussExamBridgeJson(200, [
    'ok' => true,
    'filters' => [
        'schoolYearID' => $schoolYearID,
        'classID' => $classID,
        'personID' => $personID,
        'updatedAfter' => $updatedAfterDate,
    ],
    'pagination' => [
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $totalRecords,
        'totalPages' => $pageSize > 0 ? (int) ceil($totalRecords / $pageSize) : 0,
    ],
    'data' => $data,
]);
