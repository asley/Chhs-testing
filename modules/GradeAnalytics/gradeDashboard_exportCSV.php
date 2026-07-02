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
$gibbon_path = realpath(dirname(__FILE__) . '/../../');
if (!is_file($gibbon_path.'/gibbon.php')) {
    die('Your ../../gibbon.php file does not exist. Please check your file path and try again.');
}
require_once $gibbon_path.'/gibbon.php';

use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;

if (!isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/gradeDashboard.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];

$filters = [];
foreach (['courseID', 'classID', 'formGroupID', 'teacherID', 'yearGroup', 'assessmentType', 'assessmentName'] as $filterName) {
    if (!empty($_GET[$filterName])) {
        $filters[$filterName] = $_GET[$filterName];
    }
}

$gateway = $container->get(GradeAnalyticsGateway::class);

$rows = [];
foreach (['A', 'B', 'C', 'D', 'E'] as $grade) {
    $students = $gateway->selectStudentsByGrade($gibbonSchoolYearID, $grade, $filters);
    foreach ($students as $student) {
        $rows[] = [
            $student['surname'] . ', ' . $student['preferredName'],
            $student['formGroup'] ?? '',
            $student['yearGroup'] ?? '',
            $student['grade'] ?? '',
            $student['courseName'] ?? '',
            $student['assessmentName'] ?? '',
        ];
    }
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="grade-analytics-'.date('Y-m-d').'.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Student', 'Form Group', 'Year Group', 'Grade', 'Course', 'Assessment']);
foreach ($rows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
