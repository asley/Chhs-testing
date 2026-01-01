<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

// Bootstrap Gibbon core
$gibbon_path = realpath(dirname(__FILE__) . '/../../../');
if (!is_file($gibbon_path.'/gibbon.php')) {
    die('{"success": false, "message": "Gibbon bootstrap file not found"}');
}
require_once $gibbon_path.'/gibbon.php';

use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;

// Check permissions
if (!isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/gradeDashboard.php')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get parameters
$grade = $_GET['grade'] ?? '';
$courseID = $_GET['courseID'] ?? '';
$classID = $_GET['classID'] ?? '';
$formGroupID = $_GET['formGroupID'] ?? '';
$teacherID = $_GET['teacherID'] ?? '';
$yearGroup = $_GET['yearGroup'] ?? '';
$assessmentType = $_GET['assessmentType'] ?? '';
$gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'] ?? '';

// Initialize response
$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

try {
    if (empty($grade)) {
        throw new Exception('Grade parameter is required');
    }

    if (empty($gibbonSchoolYearID)) {
        throw new Exception('School year not found');
    }

    // Get the gateway
    $gateway = $container->get(GradeAnalyticsGateway::class);

    // Build filters array
    $filters = [];
    if (!empty($courseID)) $filters['courseID'] = $courseID;
    if (!empty($classID)) $filters['classID'] = $classID;
    if (!empty($formGroupID)) $filters['formGroupID'] = $formGroupID;
    if (!empty($teacherID)) $filters['teacherID'] = $teacherID;
    if (!empty($yearGroup)) $filters['yearGroup'] = $yearGroup;
    if (!empty($assessmentType)) $filters['assessmentType'] = $assessmentType;

    // Fetch students
    $students = $gateway->selectStudentsByGrade($gibbonSchoolYearID, $grade, $filters);

    // Format student data with profile links
    $studentList = [];
    $absoluteURL = $_SESSION[$guid]['absoluteURL'];

    foreach ($students as $student) {
        $studentList[] = [
            'gibbonPersonID' => $student['gibbonPersonID'],
            'name' => $student['surname'] . ', ' . $student['preferredName'],
            'formGroup' => $student['formGroup'] ?? 'N/A',
            'yearGroup' => $student['yearGroup'] ?? 'N/A',
            'grade' => $student['grade'] ?? 'N/A',
            'courseName' => $student['courseName'] ?? 'N/A',
            'assessmentName' => $student['assessmentName'] ?? 'N/A',
            'profileLink' => $absoluteURL . '/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID=' . $student['gibbonPersonID']
        ];
    }

    $response['success'] = true;
    $response['data'] = $studentList;
    $response['count'] = count($studentList);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
