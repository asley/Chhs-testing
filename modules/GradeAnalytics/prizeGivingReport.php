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
use Gibbon\Tables\DataTable;
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
    echo __('Use this page to generate reports for prize giving based on grade criteria.');
    echo '</p>';

    // Get URL parameters
    $courseID = $_GET['courseID'] ?? '';
    $formGroupID = $_GET['formGroupID'] ?? '';
    $yearGroup = $_GET['yearGroup'] ?? '';
    $assessmentType = $_GET['assessmentType'] ?? '';
    $gradeThreshold = $_GET['gradeThreshold'] ?? '75';

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

        if ($students->rowCount() > 0) {
            echo '<h3>'. __('Results') .'</h3>';

            // Add print link
            echo '<div class="linkTop">';
            echo '<a target="_blank" href="'.$session->get('absoluteURL').'/report.php?q=/modules/GradeAnalytics/prizeGivingReport_print.php&'.http_build_query($_GET).'">';
            echo __('Print').' <img style="margin-left: 5px" title="'.__('Print').'" src="./themes/'.$session->get('gibbonThemeName').'/img/print.png"/>';
            echo '</a>';
            echo '</div>';

            // Build data table
            $table = DataTable::createDetails('prizeGivingReport');
            $table->addColumn('studentName', __('Student Name'))
                  ->format(function($row) {
                      return Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
                  });
            $table->addColumn('formGroup', __('Form Group'));
            $table->addColumn('courseName', __('Subject'));
            $table->addColumn('assessmentName', __('Assessment'));
            $table->addColumn('grade', __('Grade'));

            echo $table->render($students->toDataSet());

            // Add CSV export button
            echo '<div class="linkTop" style="margin-top: 20px;">';
            echo '<a href="#" onclick="exportTableToCSV(\'prize-giving-report.csv\')" class="button">';
            echo __('Export to CSV');
            echo '</a>';
            echo '</div>';

            // Add JavaScript for CSV export
            echo '<script>
            function exportTableToCSV(filename) {
                var csv = [];
                var rows = document.querySelectorAll("#prizeGivingReport tr");

                for (var i = 0; i < rows.length; i++) {
                    var row = [];
                    var cols = rows[i].querySelectorAll("td, th");

                    for (var j = 0; j < cols.length; j++) {
                        var text = cols[j].innerText || "";
                        text = text.replace(/"/g, \'""\'); // Escape quotes
                        row.push(\'"\' + text + \'"\');
                    }

                    csv.push(row.join(","));
                }

                var csvContent = csv.join("\\n");
                var blob = new Blob([csvContent], {type: "text/csv"});
                var url = window.URL.createObjectURL(blob);
                var downloadLink = document.createElement("a");
                downloadLink.href = url;
                downloadLink.download = filename;
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                window.URL.revokeObjectURL(url);
            }
            </script>';
        } else {
            echo Format::alert(__('No students found matching the selected criteria.'), 'message');
        }
    }
}
