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

use Gibbon\Services\Format;
use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/studentAveragesRanking.php') == false) {
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo "</div>";
} else {
    // Get URL parameters
    $formGroupID = $_GET['formGroupID'] ?? '';
    $yearGroup = $_GET['yearGroup'] ?? '';
    $assessmentType = $_GET['assessmentType'] ?? '';
    $assessmentName = $_GET['assessmentName'] ?? '';

    // Initialize Gateway
    $gateway = $container->get(GradeAnalyticsGateway::class);
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Prepare filters
    $filters = [
        'formGroupID' => $formGroupID,
        'yearGroup' => $yearGroup,
        'assessmentType' => $assessmentType,
        'assessmentName' => $assessmentName
    ];

    // Get student averages
    $students = $gateway->selectStudentAverages($gibbonSchoolYearID, $filters);

    echo '<h2>';
    echo __('Student Averages Ranking');
    echo '</h2>';

    // Display filter information
    if (!empty($formGroupID) || !empty($yearGroup) || !empty($assessmentType) || !empty($assessmentName)) {
        echo '<p><strong>Filters Applied:</strong><br/>';
        if (!empty($formGroupID)) {
            $formGroups = $gateway->selectFormGroups($gibbonSchoolYearID)->fetchKeyPair();
            echo 'Form Group: ' . ($formGroups[$formGroupID] ?? 'N/A') . '<br/>';
        }
        if (!empty($yearGroup)) {
            $yearGroups = $gateway->selectYearGroups($gibbonSchoolYearID)->fetchKeyPair();
            echo 'Year Group: ' . ($yearGroups[$yearGroup] ?? 'N/A') . '<br/>';
        }
        if (!empty($assessmentType)) {
            echo 'Assessment Type: ' . $assessmentType . '<br/>';
        }
        if (!empty($assessmentName)) {
            echo 'Assessment: ' . htmlspecialchars($assessmentName) . '<br/>';
        }
        echo '</p>';
    }

    if ($students->rowCount() > 0) {
        echo '<table cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        echo '<thead>';
        echo '<tr style="background-color: #f0f0f0; border-bottom: 2px solid #333;">';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Rank</th>';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Student Name</th>';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Form Group</th>';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Year Group</th>';
        echo '<th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Total Subjects</th>';
        echo '<th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Final Average (%)</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $rank = 1;
        foreach ($students as $student) {
            // Build student link to Internal Assessment page
            $studentLink = $session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php';
            $studentLink .= '&gibbonPersonID='.$student['gibbonPersonID'];
            $studentLink .= '&search=&allStudents=&subpage=Internal%20Assessment';

            echo '<tr style="border-bottom: 1px solid #ddd;">';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $rank . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;"><a href="'.$studentLink.'">' . Format::name('', $student['preferredName'], $student['surname'], 'Student', true) . '</a></td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($student['formGroup']) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($student['yearGroup']) . '</td>';
            echo '<td style="padding: 8px; text-align: center; border: 1px solid #ddd;">' . $student['totalCourses'] . '</td>';
            echo '<td style="padding: 8px; text-align: center; font-weight: bold; border: 1px solid #ddd;">' . number_format($student['finalAverage'], 2) . '%</td>';
            echo '</tr>';
            $rank++;
        }

        echo '</tbody>';
        echo '</table>';

        // Summary statistics
        $allAverages = [];
        foreach ($students as $student) {
            $allAverages[] = $student['finalAverage'];
        }

        if (!empty($allAverages)) {
            echo '<div style="margin-top: 30px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #3498db;">';
            echo '<h3 style="margin-top: 0;">Summary Statistics</h3>';
            echo '<p>';
            echo '<strong>Total Students:</strong> ' . count($allAverages) . '<br/>';
            echo '<strong>Highest Average:</strong> ' . number_format(max($allAverages), 2) . '%<br/>';
            echo '<strong>Lowest Average:</strong> ' . number_format(min($allAverages), 2) . '%<br/>';
            echo '<strong>Class Average:</strong> ' . number_format(array_sum($allAverages) / count($allAverages), 2) . '%<br/>';
            echo '</p>';
            echo '</div>';
        }
    } else {
        echo '<p style="font-style: italic; color: #666;">No student data found matching the selected criteria.</p>';
    }
}
