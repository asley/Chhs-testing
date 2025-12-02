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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/broadsheetExport.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Set up page breadcrumbs
    $page->breadcrumbs
        ->add(__('Grade Analytics'), 'gradeDashboard.php')
        ->add(__('Broadsheet Export'));

    echo '<h2>';
    echo __('Broadsheet Export');
    echo '</h2>';

    echo '<p>';
    echo __('Export a comprehensive spreadsheet showing all students and their grades across all subjects. Filter by form group, year group, teacher, or assessment type.');
    echo '</p>';

    // Get URL parameters
    $formGroupID = $_GET['formGroupID'] ?? '';
    $yearGroup = $_GET['yearGroup'] ?? '';
    $teacherID = $_GET['teacherID'] ?? '';
    $assessmentType = $_GET['assessmentType'] ?? '';
    $assessmentName = $_GET['assessmentName'] ?? '';

    // Initialize Gateway
    $gateway = $container->get(GradeAnalyticsGateway::class);
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Build filter form
    $form = Form::create('filterForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/GradeAnalytics/broadsheetExport.php');

    $row = $form->addRow();
        $row->addLabel('formGroupID', __('Form Group'));
        $formGroups = $gateway->selectFormGroups($gibbonSchoolYearID)->fetchKeyPair();
        $row->addSelect('formGroupID')
            ->fromArray($formGroups)
            ->placeholder(__('All Form Groups'))
            ->selected($formGroupID);

    $row = $form->addRow();
        $row->addLabel('yearGroup', __('Year Group'));
        $yearGroups = $gateway->selectYearGroups($gibbonSchoolYearID)->fetchKeyPair();
        $row->addSelect('yearGroup')
            ->fromArray($yearGroups)
            ->placeholder(__('All Year Groups'))
            ->selected($yearGroup);

    $row = $form->addRow();
        $row->addLabel('teacherID', __('Teacher'));
        $teachers = $gateway->selectTeachers($gibbonSchoolYearID)->fetchKeyPair();
        $row->addSelect('teacherID')
            ->fromArray($teachers)
            ->placeholder(__('All Teachers'))
            ->selected($teacherID);

    $row = $form->addRow();
        $row->addLabel('assessmentType', __('Assessment Type'));
        $assessmentTypes = $gateway->selectAssessmentTypes()->fetchAll(\PDO::FETCH_COLUMN);
        $assessmentTypesArray = array_combine($assessmentTypes, $assessmentTypes);
        $row->addSelect('assessmentType')
            ->fromArray($assessmentTypesArray)
            ->placeholder(__('All Types'))
            ->selected($assessmentType);

    $row = $form->addRow();
        $row->addLabel('assessmentName', __('Assessment'));
        $assessmentColumns = $gateway->selectAssessmentColumns($gibbonSchoolYearID)->fetchKeyPair();
        $row->addSelect('assessmentName')
            ->fromArray($assessmentColumns)
            ->placeholder(__('All Assessments'))
            ->selected($assessmentName);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Generate Broadsheet'));

    echo $form->getOutput();

    // Display results if filters have been applied
    if (!empty($_GET) && isset($_GET['q'])) {
        // Prepare filters for gateway
        $filters = [
            'formGroupID' => $formGroupID,
            'yearGroup' => $yearGroup,
            'teacherID' => $teacherID,
            'assessmentType' => $assessmentType,
            'assessmentName' => $assessmentName
        ];

        // Get broadsheet data
        $broadsheetData = $gateway->selectBroadsheetData($gibbonSchoolYearID, $filters);

        if (!empty($broadsheetData)) {
            echo '<h3>'. __('Results') .'</h3>';

            // Add download CSV button
            echo '<div class="linkTop">';
            echo '<a href="#" onclick="downloadBroadsheetCSV(); return false;" class="button">';
            echo __('Download CSV');
            echo '</a>';
            echo '</div>';

            // Display preview table
            echo '<p><em>Preview (showing first 10 students):</em></p>';
            echo '<div class="overflow-x-auto">';
            echo '<table class="fullWidth colorOddEven" cellspacing="0" id="broadsheetTable" style="font-size: 0.9em;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="width: 5%;">Rank</th>';
            echo '<th style="width: 20%;">Student Name</th>';
            echo '<th style="width: 10%;">Form Group</th>';
            echo '<th style="width: 10%;">Year Group</th>';

            // Get all unique courses
            $courses = [];
            foreach ($broadsheetData as $studentData) {
                foreach ($studentData['courses'] as $course => $grade) {
                    if (!in_array($course, $courses)) {
                        $courses[] = $course;
                    }
                }
            }
            sort($courses);

            foreach ($courses as $course) {
                echo '<th style="text-align: center; min-width: 80px;">'.htmlspecialchars($course).'</th>';
            }
            echo '<th style="text-align: center; font-weight: bold;">Average</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            $count = 0;
            $rank = 1;
            foreach ($broadsheetData as $student) {
                if ($count >= 10) break; // Preview only first 10

                echo '<tr>';
                echo '<td style="text-align: center; font-weight: bold;">'.$rank.'</td>';

                // Build student link
                $studentLink = $session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php';
                $studentLink .= '&gibbonPersonID='.$student['gibbonPersonID'];
                $studentLink .= '&search=&allStudents=&subpage=Internal%20Assessment';

                echo '<td><a href="'.$studentLink.'">'.Format::name('', $student['preferredName'], $student['surname'], 'Student', true).'</a></td>';
                echo '<td>'.htmlspecialchars($student['formGroup']).'</td>';
                echo '<td>'.htmlspecialchars($student['yearGroup']).'</td>';

                // Display grades for each course
                foreach ($courses as $course) {
                    $grade = $student['courses'][$course] ?? '-';
                    $color = '';
                    if (is_numeric($grade)) {
                        if ($grade >= 85) {
                            $color = 'color: #2ecc71; font-weight: bold;';
                        } elseif ($grade >= 70) {
                            $color = 'color: #3498db; font-weight: bold;';
                        } elseif ($grade >= 55) {
                            $color = 'color: #f39c12; font-weight: bold;';
                        } else {
                            $color = 'color: #e74c3c; font-weight: bold;';
                        }
                        $grade = number_format($grade, 1) . '%';
                    }
                    echo '<td style="text-align: center;"><span style="'.$color.'">'.$grade.'</span></td>';
                }

                // Display average
                $average = $student['average'];
                $avgColor = '';
                if ($average >= 85) {
                    $avgColor = 'color: #2ecc71; font-weight: bold;';
                } elseif ($average >= 70) {
                    $avgColor = 'color: #3498db; font-weight: bold;';
                } elseif ($average >= 55) {
                    $avgColor = 'color: #f39c12; font-weight: bold;';
                } else {
                    $avgColor = 'color: #e74c3c; font-weight: bold;';
                }
                echo '<td style="text-align: center;"><span style="'.$avgColor.' font-size: 1.1em;">'.number_format($average, 2).'%</span></td>';
                echo '</tr>';
                $rank++;
                $count++;
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            if (count($broadsheetData) > 10) {
                echo '<p><em>Showing 10 of '.count($broadsheetData).' students. Download CSV to see all data.</em></p>';
            }

            // Add JavaScript for CSV download with full data
            echo '<script>
            function downloadBroadsheetCSV() {
                var broadsheetData = ' . json_encode($broadsheetData) . ';
                var courses = ' . json_encode($courses) . ';

                var csv = [];

                // Header row
                var header = ["Rank", "Student Name", "Form Group", "Year Group"];
                courses.forEach(function(course) {
                    header.push(course);
                });
                header.push("Average");
                csv.push(header.map(function(cell) { return \'"\' + cell + \'"\'; }).join(","));

                // Data rows
                var rank = 1;
                broadsheetData.forEach(function(student) {
                    var row = [
                        rank,
                        student.preferredName + " " + student.surname,
                        student.formGroup,
                        student.yearGroup
                    ];

                    courses.forEach(function(course) {
                        var grade = student.courses[course] || "-";
                        if (typeof grade === "number") {
                            grade = grade.toFixed(1) + "%";
                        }
                        row.push(grade);
                    });

                    row.push(student.average.toFixed(2) + "%");

                    csv.push(row.map(function(cell) {
                        return \'"\' + String(cell).replace(/"/g, \'"\' + \'"\') + \'"\';
                    }).join(","));
                    rank++;
                });

                var csvContent = csv.join("\\n");
                var blob = new Blob([csvContent], {type: "text/csv;charset=utf-8;"});
                var url = window.URL.createObjectURL(blob);
                var downloadLink = document.createElement("a");
                downloadLink.href = url;
                downloadLink.download = "broadsheet-export.csv";
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                window.URL.revokeObjectURL(url);
                return false;
            }
            </script>';

        } else {
            echo Format::alert(__('No student data found matching the selected criteria.'), 'message');
        }
    }
}
