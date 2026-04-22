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

if (isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/prizeGivingReport.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Set up page breadcrumbs
    $page->breadcrumbs
        ->add(__('Grade Analytics'), 'gradeDashboard.php')
        ->add(__('Prize Giving Report'));

    echo '<h2>';
    echo __('Prize Giving Report');
    echo '</h2>';

    echo '<p>';
    echo __('Use this page to generate reports for prize giving based on grade criteria. You can also view the ');
    echo '<a href="'.$session->get('absoluteURL').'/index.php?q=/modules/GradeAnalytics/studentAveragesRanking.php">';
    echo __('Student Averages Ranking');
    echo '</a> to see final averages across all subjects.';
    echo '</p>';

    // Get URL parameters
    $courseID = $_GET['courseID'] ?? '';
    $formGroupID = $_GET['formGroupID'] ?? [];
    if (!is_array($formGroupID)) {
        $formGroupID = !empty($formGroupID) ? [$formGroupID] : [];
    }
    $yearGroup = $_GET['yearGroup'] ?? '';
    $assessmentType = $_GET['assessmentType'] ?? '';
    $gradeThreshold = $_GET['gradeThreshold'] ?? '75';
    $showAverage = $_GET['showAverage'] ?? 'N';

    // Handle operator - convert from safe keys to SQL operators
    $operatorKey = $_GET['operator'] ?? 'gt';
    $operatorMap = [
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
        'eq' => '='
    ];
    $operator = $operatorMap[$operatorKey] ?? '>';

    // Initialize Gateway
    $gateway = $container->get(GradeAnalyticsGateway::class);
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Build filter form
    $form = Form::create('filterForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/GradeAnalytics/prizeGivingReport.php');

    $row = $form->addRow();
        $row->addLabel('courseID', __('Course'));
        $courses = $gateway->selectCourses($gibbonSchoolYearID)->fetchKeyPair();
        $row->addSelect('courseID')
            ->fromArray($courses)
            ->placeholder(__('All Courses'))
            ->selected($courseID);

    $row = $form->addRow();
        $row->addLabel('formGroupID', __('Form Group'));
        $formGroups = $gateway->selectFormGroups($gibbonSchoolYearID)->fetchKeyPair();
        $row->addSelect('formGroupID')
            ->fromArray($formGroups)
            ->placeholder(__('All Form Groups'))
            ->selectMultiple()
            ->selected($formGroupID);

    $row = $form->addRow();
        $row->addLabel('yearGroup', __('Year Group'));
        $yearGroups = $gateway->selectYearGroups($gibbonSchoolYearID)->fetchKeyPair();
        $row->addSelect('yearGroup')
            ->fromArray($yearGroups)
            ->placeholder(__('All Year Groups'))
            ->selected($yearGroup);

    $row = $form->addRow();
        $row->addLabel('assessmentType', __('Assessment Type'));
        $assessmentTypes = $gateway->selectAssessmentTypes()->fetchAll(\PDO::FETCH_COLUMN);
        $assessmentTypesArray = array_combine($assessmentTypes, $assessmentTypes);
        $row->addSelect('assessmentType')
            ->fromArray($assessmentTypesArray)
            ->placeholder(__('All Types'))
            ->selected($assessmentType);

    $row = $form->addRow();
        $row->addLabel('gradeCriteria', __('Grade Criteria'));
        $col = $row->addColumn()->addClass('right');
        $col->addSelect('operator')
            ->fromArray([
                'gt' => '>',
                'gte' => '≥',
                'lt' => '<',
                'lte' => '≤',
                'eq' => '='
            ])
            ->setClass('shortWidth')
            ->selected($operatorKey)
            ->required();
        $col->addNumber('gradeThreshold')
            ->setValue($gradeThreshold)
            ->minimum(0)
            ->maximum(100)
            ->onlyInteger(true)
            ->setClass('shortWidth')
            ->required();

    $row = $form->addRow();
        $row->addLabel('showAverage', __('Show Average'));
        $row->addSelect('showAverage')
            ->fromArray(['N' => __('No'), 'Y' => __('Yes')])
            ->selected($showAverage);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Apply Filters'));

    echo $form->getOutput();

    // Display results if filters have been applied
    if (!empty($_GET) && isset($_GET['q'])) {
        // Prepare filters for gateway
        $filters = [
            'courseID' => $courseID,
            'formGroupID' => $formGroupID,
            'yearGroup' => $yearGroup,
            'assessmentType' => $assessmentType,
            'gradeThreshold' => $gradeThreshold,
            'operator' => $operator
        ];

        // Get students matching criteria
        $students = $gateway->selectPrizeGivingStudents($gibbonSchoolYearID, $filters);

        $allStudents = $students->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($allStudents)) {
            echo '<h3>'. __('Results') .'</h3>';

            // Add print link
            echo '<div class="linkTop">';
            echo '<a target="_blank" href="'.$session->get('absoluteURL').'/report.php?q=/modules/GradeAnalytics/prizeGivingReport_print.php&'.http_build_query($_GET).'">';
            echo __('Print').' <img style="margin-left: 5px" title="'.__('Print').'" src="./themes/'.$session->get('gibbonThemeName').'/img/print.png"/>';
            echo '</a>';
            echo '</div>';

            // Pre-compute per-student numeric averages (strip % before parsing)
            $studentGrades = [];
            foreach ($allStudents as $student) {
                $pid = $student['gibbonPersonID'];
                $rawGrade = trim(str_replace('%', '', $student['grade']));
                if (is_numeric($rawGrade)) {
                    $studentGrades[$pid][] = (float) $rawGrade;
                }
            }
            $studentAvg = [];
            foreach ($studentGrades as $pid => $grades) {
                $studentAvg[$pid] = array_sum($grades) / count($grades);
            }

            $formatGrade = function($grade) {
                if (!is_numeric($grade)) return ['', $grade];
                $g = (float) $grade;
                $display = number_format($g, 2) . '%';
                if ($g >= 85) $color = 'color: #2ecc71; font-weight: bold;';
                elseif ($g >= 70) $color = 'color: #3498db; font-weight: bold;';
                elseif ($g >= 60) $color = 'color: #f39c12; font-weight: bold;';
                else $color = 'color: #e74c3c; font-weight: bold;';
                return [$color, $display];
            };

            echo '<div class="overflow-x-auto">';
            echo '<table class="fullWidth colorOddEven" cellspacing="0" id="prizeGivingReportTable">';
            echo '<thead><tr>';

            if ($showAverage === 'Y') {
                // Averaged view: one row per student
                echo '<th style="width: 30%;">'.__('Student Name').'</th>';
                echo '<th style="width: 20%;">'.__('Form Group').'</th>';
                echo '<th style="width: 25%;">'.__('Subject').'</th>';
                echo '<th style="width: 25%; text-align: center;">'.__('Average Grade').'</th>';
            } else {
                echo '<th style="width: 22%;">'.__('Student Name').'</th>';
                echo '<th style="width: 13%;">'.__('Form Group').'</th>';
                echo '<th style="width: 18%;">'.__('Subject').'</th>';
                echo '<th style="width: 18%;">'.__('Assessment').'</th>';
                echo '<th style="width: 20%; text-align: center;">'.__('Grade').'</th>';
            }

            echo '</tr></thead><tbody>';

            if ($showAverage === 'Y') {
                // Build one row per student, deduplicated and sorted by average descending
                $uniqueStudents = [];
                foreach ($allStudents as $student) {
                    $pid = $student['gibbonPersonID'];
                    if (!isset($uniqueStudents[$pid])) {
                        $uniqueStudents[$pid] = $student;
                    }
                }
                usort($uniqueStudents, function($a, $b) use ($studentAvg) {
                    return ($studentAvg[$b['gibbonPersonID']] ?? 0) <=> ($studentAvg[$a['gibbonPersonID']] ?? 0);
                });

                foreach ($uniqueStudents as $student) {
                    $pid = $student['gibbonPersonID'];

                    $studentLink = $session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php';
                    $studentLink .= '&gibbonPersonID='.$pid.'&search=&allStudents=&subpage=Internal%20Assessment';

                    [$avgStyle, $avgDisplay] = isset($studentAvg[$pid])
                        ? $formatGrade($studentAvg[$pid])
                        : ['', '—'];

                    echo '<tr>';
                    echo '<td><a href="'.$studentLink.'">'.Format::name('', $student['preferredName'], $student['surname'], 'Student', true).'</a></td>';
                    echo '<td>'.htmlspecialchars($student['formGroup']).'</td>';
                    echo '<td>'.htmlspecialchars($student['courseName']).'</td>';
                    echo '<td style="text-align: center;"><span style="'.$avgStyle.' font-size: 1.1em;">'.$avgDisplay.'</span></td>';
                    echo '</tr>';
                }
            } else {
                foreach ($allStudents as $student) {
                    $pid = $student['gibbonPersonID'];
                    $studentLink = $session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php';
                    $studentLink .= '&gibbonPersonID='.$pid.'&search=&allStudents=&subpage=Internal%20Assessment';

                    [$color, $gradeDisplay] = $formatGrade(trim(str_replace('%', '', $student['grade'])));

                    echo '<tr>';
                    echo '<td><a href="'.$studentLink.'">'.Format::name('', $student['preferredName'], $student['surname'], 'Student', true).'</a></td>';
                    echo '<td>'.htmlspecialchars($student['formGroup']).'</td>';
                    echo '<td>'.htmlspecialchars($student['courseName']).'</td>';
                    echo '<td>'.htmlspecialchars($student['assessmentName']).'</td>';
                    echo '<td style="text-align: center;"><span style="'.$color.' font-size: 1.1em;">'.$gradeDisplay.'</span></td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table></div>';

            // Add CSV export button
            echo '<div class="linkTop" style="margin-top: 20px;">';
            echo '<a href="#" onclick="exportPrizeGivingToCSV(); return false;" class="button">';
            echo __('Export to CSV');
            echo '</a>';
            echo '</div>';

            // Add JavaScript for CSV export
            echo '<script>
            function exportPrizeGivingToCSV() {
                var csv = [];
                var rows = document.querySelectorAll("#prizeGivingReportTable tr");

                for (var i = 0; i < rows.length; i++) {
                    var row = [];
                    var cols = rows[i].querySelectorAll("td, th");

                    for (var j = 0; j < cols.length; j++) {
                        var text = cols[j].innerText || "";
                        text = text.replace(/"/g, \'"\' + \'"\'); // Escape quotes
                        row.push(\'"\' + text + \'"\');
                    }

                    csv.push(row.join(","));
                }

                var csvContent = csv.join("\\n");
                var blob = new Blob([csvContent], {type: "text/csv;charset=utf-8;"});
                var url = window.URL.createObjectURL(blob);
                var downloadLink = document.createElement("a");
                downloadLink.href = url;
                downloadLink.download = "prize-giving-report.csv";
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                window.URL.revokeObjectURL(url);
                return false;
            }
            </script>';
        } else {
            echo Format::alert(__('No students found matching the selected criteria.'), 'message');
        }
    }
}
