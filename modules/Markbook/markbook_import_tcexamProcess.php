<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;

require_once '../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$gibbonMarkbookColumnID = $_GET['gibbonMarkbookColumnID'] ?? '';
$address = $_GET['address'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($address)."/markbook_import_tcexam.php&gibbonCourseClassID=$gibbonCourseClassID&gibbonMarkbookColumnID=$gibbonMarkbookColumnID";

if (isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_edit.php') == false) {
    header("Location: {$URL}&return=error0");
    exit();
}

if ($gibbonCourseClassID == '' || $gibbonMarkbookColumnID == '' || empty($_FILES['file']['tmp_name'])) {
    header("Location: {$URL}&return=error1");
    exit();
}

$highestAction = getHighestGroupedAction($guid, '/modules/Markbook/markbook_edit.php', $connection2);

$data = ['gibbonCourseClassID' => $gibbonCourseClassID];
$sql = "SELECT gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourse.gibbonDepartmentID
        FROM gibbonCourse
        JOIN gibbonCourseClass ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
        WHERE gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID";

try {
    $result = $pdo->executeQuery($data, $sql);
} catch (PDOException $e) {
    header("Location: {$URL}&return=error2");
    exit();
}

$class = ($result->rowCount() == 1) ? $result->fetch() : null;
if (empty($class)) {
    header("Location: {$URL}&return=error2");
    exit();
}

$teacherList = getTeacherList($pdo, $gibbonCourseClassID);
$departmentGateway = $container->get(DepartmentGateway::class);
$departmentAccess = $departmentGateway->selectMemberOfDepartmentByRole($class['gibbonDepartmentID'], $session->get('gibbonPersonID'), ['Coordinator', 'Teacher (Curriculum)'])->fetch();
$canEditThisClass = (isset($teacherList[$session->get('gibbonPersonID')]) || $highestAction == 'Edit Markbook_everything' || ($highestAction == 'Edit Markbook_multipleClassesInDepartment' && !empty($departmentAccess)));

if (!$canEditThisClass) {
    header("Location: {$URL}&return=error0");
    exit();
}

$settingGateway = $container->get(SettingGateway::class);
$enableRawAttainment = $settingGateway->getSettingByScope('Markbook', 'enableRawAttainment');

$data = [
    'gibbonMarkbookColumnID' => $gibbonMarkbookColumnID,
    'gibbonCourseClassID' => $gibbonCourseClassID,
];
$sql = "SELECT *
        FROM gibbonMarkbookColumn
        WHERE gibbonMarkbookColumnID=:gibbonMarkbookColumnID
        AND gibbonCourseClassID=:gibbonCourseClassID";

try {
    $result = $pdo->executeQuery($data, $sql);
} catch (PDOException $e) {
    header("Location: {$URL}&return=error2");
    exit();
}

$column = ($result->rowCount() == 1) ? $result->fetch() : null;
if (empty($column) || $column['attainment'] != 'Y') {
    header("Location: {$URL}&return=error2");
    exit();
}

$handle = fopen($_FILES['file']['tmp_name'], 'r');
if ($handle === false) {
    header("Location: {$URL}&return=error1");
    exit();
}

$headers = fgetcsv($handle);
if (!is_array($headers)) {
    fclose($handle);
    header("Location: {$URL}&return=error1");
    exit();
}

$headerMap = [];
foreach ($headers as $index => $header) {
    $headerMap[strtolower(trim((string) $header))] = $index;
}

$requiredHeaders = ['email', 'score', 'percentage', 'submitted at'];
foreach ($requiredHeaders as $requiredHeader) {
    if (!array_key_exists($requiredHeader, $headerMap)) {
        fclose($handle);
        header("Location: {$URL}&return=error1");
        exit();
    }
}

$latestByEmail = [];
$rejected = 0;

while (($row = fgetcsv($handle)) !== false) {
    $email = strtolower(trim((string) ($row[$headerMap['email']] ?? '')));
    $score = trim((string) ($row[$headerMap['score']] ?? ''));
    $percentage = trim((string) ($row[$headerMap['percentage']] ?? ''));
    $submittedAt = trim((string) ($row[$headerMap['submitted at']] ?? ''));
    $status = array_key_exists('status', $headerMap) ? strtolower(trim((string) ($row[$headerMap['status']] ?? ''))) : 'submitted';

    if ($email == '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_numeric($score) || !is_numeric($percentage)) {
        $rejected++;
        continue;
    }

    if (!in_array($status, ['submitted', 'graded', 'final'], true)) {
        $rejected++;
        continue;
    }

    $submittedTimestamp = strtotime($submittedAt);
    if ($submittedTimestamp === false) {
        $submittedTimestamp = 0;
    }

    if (!isset($latestByEmail[$email]) || $submittedTimestamp > $latestByEmail[$email]['submittedTimestamp']) {
        $latestByEmail[$email] = [
            'email' => $email,
            'score' => $score,
            'total' => array_key_exists('total points possible', $headerMap) ? trim((string) ($row[$headerMap['total points possible']] ?? '')) : '',
            'percentage' => $percentage,
            'submittedAt' => $submittedAt,
            'submittedTimestamp' => $submittedTimestamp,
            'examName' => array_key_exists('exam name', $headerMap) ? trim((string) ($row[$headerMap['exam name']] ?? '')) : '',
        ];
    }
}

fclose($handle);

$data = [
    'gibbonCourseClassID' => $gibbonCourseClassID,
    'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
    'today' => date('Y-m-d'),
];
$sql = "SELECT gibbonPerson.gibbonPersonID, LOWER(gibbonPerson.email) AS email, LOWER(gibbonPerson.emailAlternate) AS emailAlternate
        FROM gibbonCourseClassPerson
        JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID)
        LEFT JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
        WHERE gibbonCourseClassPerson.gibbonCourseClassID=:gibbonCourseClassID
        AND gibbonCourseClassPerson.role='Student'
        AND gibbonPerson.status='Full'
        AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
        AND (dateStart IS NULL OR dateStart<=:today)
        AND (dateEnd IS NULL OR dateEnd>=:today)";

try {
    $result = $pdo->executeQuery($data, $sql);
} catch (PDOException $e) {
    header("Location: {$URL}&return=error2");
    exit();
}

$studentsByEmail = [];
while ($student = $result->fetch()) {
    if (!empty($student['email'])) {
        $studentsByEmail[strtolower($student['email'])] = $student['gibbonPersonID'];
    }
    if (!empty($student['emailAlternate'])) {
        $studentsByEmail[strtolower($student['emailAlternate'])] = $student['gibbonPersonID'];
    }
}

$accepted = 0;
$inserted = 0;
$updated = 0;
$partialFail = false;
$useRaw = ($enableRawAttainment == 'Y' && $column['attainmentRaw'] == 'Y' && !empty($column['attainmentRawMax']));

foreach ($latestByEmail as $record) {
    if (!isset($studentsByEmail[$record['email']])) {
        $rejected++;
        continue;
    }

    $gibbonPersonIDStudent = $studentsByEmail[$record['email']];
    $attainmentValue = number_format((float) $record['percentage'], 2, '.', '');
    $attainmentValueRaw = $useRaw ? number_format((float) $record['score'], 2, '.', '') : null;
    $commentParts = ['Imported from TCExam CSV.'];
    if ($record['examName'] != '') {
        $commentParts[] = 'Exam: '.$record['examName'].'.';
    }
    if ($record['total'] != '') {
        $commentParts[] = 'Raw score: '.$record['score'].'/'.$record['total'].'.';
    }
    if ($record['submittedAt'] != '') {
        $commentParts[] = 'Submitted: '.$record['submittedAt'].'.';
    }
    $comment = ($column['comment'] == 'Y') ? implode(' ', $commentParts) : null;

    try {
        $data = [
            'gibbonMarkbookColumnID' => $gibbonMarkbookColumnID,
            'gibbonPersonIDStudent' => $gibbonPersonIDStudent,
        ];
        $sql = "SELECT gibbonMarkbookEntryID
                FROM gibbonMarkbookEntry
                WHERE gibbonMarkbookColumnID=:gibbonMarkbookColumnID
                AND gibbonPersonIDStudent=:gibbonPersonIDStudent";
        $result = $pdo->executeQuery($data, $sql);
        $existingEntryID = ($result->rowCount() > 0) ? $result->fetchColumn(0) : null;

        if ($existingEntryID) {
            $data = [
                'attainmentValue' => $attainmentValue,
                'attainmentValueRaw' => $attainmentValueRaw,
                'attainmentDescriptor' => '',
                'attainmentConcern' => 'N',
                'comment' => $comment,
                'gibbonPersonIDLastEdit' => $session->get('gibbonPersonID'),
                'gibbonMarkbookEntryID' => $existingEntryID,
            ];
            $sql = "UPDATE gibbonMarkbookEntry
                    SET attainmentValue=:attainmentValue,
                        attainmentValueRaw=:attainmentValueRaw,
                        attainmentDescriptor=:attainmentDescriptor,
                        attainmentConcern=:attainmentConcern,
                        comment=:comment,
                        gibbonPersonIDLastEdit=:gibbonPersonIDLastEdit
                    WHERE gibbonMarkbookEntryID=:gibbonMarkbookEntryID";
            $pdo->executeQuery($data, $sql);
            $updated++;
        } else {
            $data = [
                'gibbonMarkbookColumnID' => $gibbonMarkbookColumnID,
                'gibbonPersonIDStudent' => $gibbonPersonIDStudent,
                'attainmentValue' => $attainmentValue,
                'attainmentValueRaw' => $attainmentValueRaw,
                'attainmentDescriptor' => '',
                'attainmentConcern' => 'N',
                'effortValue' => null,
                'effortDescriptor' => null,
                'effortConcern' => null,
                'comment' => $comment,
                'gibbonPersonIDLastEdit' => $session->get('gibbonPersonID'),
            ];
            $sql = "INSERT INTO gibbonMarkbookEntry
                    SET gibbonMarkbookColumnID=:gibbonMarkbookColumnID,
                        gibbonPersonIDStudent=:gibbonPersonIDStudent,
                        attainmentValue=:attainmentValue,
                        attainmentValueRaw=:attainmentValueRaw,
                        attainmentDescriptor=:attainmentDescriptor,
                        attainmentConcern=:attainmentConcern,
                        effortValue=:effortValue,
                        effortDescriptor=:effortDescriptor,
                        effortConcern=:effortConcern,
                        comment=:comment,
                        gibbonPersonIDLastEdit=:gibbonPersonIDLastEdit";
            $pdo->executeQuery($data, $sql);
            $inserted++;
        }

        $accepted++;
    } catch (PDOException $e) {
        $partialFail = true;
        $rejected++;
    }
}

if ($accepted > 0) {
    try {
        $data = [
            'completeDate' => date('Y-m-d'),
            'gibbonMarkbookColumnID' => $gibbonMarkbookColumnID,
        ];
        $sql = "UPDATE gibbonMarkbookColumn
                SET complete='Y', completeDate=:completeDate
                WHERE gibbonMarkbookColumnID=:gibbonMarkbookColumnID";
        $pdo->executeQuery($data, $sql);
    } catch (PDOException $e) {
        $partialFail = true;
    }
}

$return = $partialFail ? 'warning1' : 'success0';
$URL .= '&return='.$return.'&importAccepted='.$accepted.'&importUpdated='.$updated.'&importInserted='.$inserted.'&importRejected='.$rejected;
header("Location: {$URL}");
