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

if (isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/studentAveragesRanking.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Set up page breadcrumbs
    $page->breadcrumbs
        ->add(__('Grade Analytics'), 'gradeDashboard.php')
        ->add(__('Student Averages Ranking'));

    echo '<h2>';
    echo __('Student Averages Ranking');
    echo '</h2>';

    echo '<p>';
    echo __('This report shows the final average percentage for each student across all their subjects, ranked from highest to lowest.');
    echo '</p>';

    // Get URL parameters
    $formGroupID = $_GET['formGroupID'] ?? '';
    $yearGroup = $_GET['yearGroup'] ?? '';
    $assessmentType = $_GET['assessmentType'] ?? '';
    $assessmentName = $_GET['assessmentName'] ?? '';

    // Initialize Gateway
    $gateway = $container->get(GradeAnalyticsGateway::class);
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Build filter form
    $form = Form::create('filterForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/GradeAnalytics/studentAveragesRanking.php');

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
        $row->addLabel('assessmentName', __('Assessment'));
        $assessmentColumns = $gateway->selectAssessmentColumns($gibbonSchoolYearID)->fetchKeyPair();
        $row->addSelect('assessmentName')
            ->fromArray($assessmentColumns)
            ->placeholder(__('All Assessments'))
            ->selected($assessmentName);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Apply Filters'));

    echo $form->getOutput();

    // Prepare filters for gateway
    $filters = [
        'formGroupID' => $formGroupID,
        'yearGroup' => $yearGroup,
        'assessmentType' => $assessmentType,
        'assessmentName' => $assessmentName
    ];

    // Get student averages
    $students = $gateway->selectStudentAverages($gibbonSchoolYearID, $filters);

    if ($students->rowCount() > 0) {
        echo '<h3>'. __('Results') .'</h3>';

        // Add custom CSS for better table display
        echo '<style>
            #studentAveragesRanking table { table-layout: fixed; width: 100%; }
            #studentAveragesRanking td, #studentAveragesRanking th { white-space: nowrap; overflow: visible; text-overflow: clip; }
            #studentAveragesRanking .dataTable tbody tr { cursor: default !important; }
            #studentAveragesRanking .dataTable tbody tr:hover { background-color: #f5f5f5 !important; }
        </style>';

        // Add print and chart toggle links
        echo '<div class="linkTop">';
        echo '<a target="_blank" href="'.$session->get('absoluteURL').'/report.php?q=/modules/GradeAnalytics/studentAveragesRanking_print.php&'.http_build_query($_GET).'">';
        echo __('Print').' <img style="margin-left: 5px" title="'.__('Print').'" src="./themes/'.$session->get('gibbonThemeName').'/img/print.png"/>';
        echo '</a> | ';
        echo '<a href="#" onclick="document.getElementById(\'chartContainer\').style.display = document.getElementById(\'chartContainer\').style.display === \'none\' ? \'block\' : \'none\'; return false;">';
        echo __('Toggle Chart View');
        echo '</a>';
        echo '</div>';

        // Convert students to array for multiple iterations
        $studentsArray = [];
        foreach ($students as $student) {
            $studentsArray[] = $student;
        }

        echo '<div id="chartContainer" style="margin: 20px 0; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
        echo '<canvas id="studentRankingChart" style="max-height: 400px;"></canvas>';
        echo '</div>';

        // Build data table with ranking
        echo '<div class="overflow-x-auto">';
        echo '<table class="fullWidth colorOddEven" cellspacing="0" id="studentAveragesRankingTable">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 5%; text-align: center;">'.__('Rank').'</th>';
        echo '<th style="width: 25%;">'.__('Student Name').'</th>';
        echo '<th style="width: 15%;">'.__('Form Group').'</th>';
        echo '<th style="width: 15%;">'.__('Year Group').'</th>';
        echo '<th style="width: 15%; text-align: center;">'.__('Total Subjects').'</th>';
        echo '<th style="width: 15%; text-align: center;">'.__('Final Average').'</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $rank = 1;
        foreach ($studentsArray as $student) {
            $average = number_format($student['finalAverage'], 2);
            $color = '';
            if ($student['finalAverage'] >= 85) {
                $color = 'color: #2ecc71; font-weight: bold;';
            } elseif ($student['finalAverage'] >= 70) {
                $color = 'color: #3498db; font-weight: bold;';
            } elseif ($student['finalAverage'] >= 55) {
                $color = 'color: #f39c12; font-weight: bold;';
            } else {
                $color = 'color: #e74c3c; font-weight: bold;';
            }

            // Build student link to Internal Assessment page
            $studentLink = $session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php';
            $studentLink .= '&gibbonPersonID='.$student['gibbonPersonID'];
            $studentLink .= '&search=&allStudents=&subpage=Internal%20Assessment';

            echo '<tr>';
            echo '<td style="text-align: center; font-weight: bold;">'.$rank.'</td>';
            echo '<td><a href="'.$studentLink.'">'.Format::name('', $student['preferredName'], $student['surname'], 'Student', true).'</a></td>';
            echo '<td>'.htmlspecialchars($student['formGroup']).'</td>';
            echo '<td>'.htmlspecialchars($student['yearGroup']).'</td>';
            echo '<td style="text-align: center;">'.$student['totalCourses'].'</td>';
            echo '<td style="text-align: center;"><span style="'.$color.' font-size: 1.1em;">'.$average.'%</span></td>';
            echo '</tr>';
            $rank++;
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // Add CSV export button
        echo '<div class="linkTop" style="margin-top: 20px;">';
        echo '<a href="#" onclick="exportTableToCSV(\'student-averages-ranking.csv\')" class="button">';
        echo __('Export to CSV');
        echo '</a>';
        echo '</div>';

        // Prepare data for chart (top 20 students)
        $chartLabels = [];
        $chartValues = [];
        $chartCount = min(20, count($studentsArray));

        for ($i = 0; $i < $chartCount; $i++) {
            $student = $studentsArray[$i];
            $chartLabels[] = $student['preferredName'] . ' ' . $student['surname'];
            $chartValues[] = $student['finalAverage'];
        }

        // Add JavaScript for chart and CSV export
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>';
        echo '<script>
        // Chart.js configuration
        const ctx = document.getElementById("studentRankingChart").getContext("2d");
        const chartData = {
            labels: ' . json_encode($chartLabels) . ',
            datasets: [{
                label: "Final Average (%)",
                data: ' . json_encode($chartValues) . ',
                backgroundColor: function(context) {
                    const value = context.parsed.y;
                    if (value >= 85) return "rgba(46, 204, 113, 0.8)";
                    if (value >= 70) return "rgba(52, 152, 219, 0.8)";
                    if (value >= 55) return "rgba(243, 156, 18, 0.8)";
                    return "rgba(231, 76, 60, 0.8)";
                },
                borderColor: function(context) {
                    const value = context.parsed.y;
                    if (value >= 85) return "rgba(46, 204, 113, 1)";
                    if (value >= 70) return "rgba(52, 152, 219, 1)";
                    if (value >= 55) return "rgba(243, 156, 18, 1)";
                    return "rgba(231, 76, 60, 1)";
                },
                borderWidth: 2
            }]
        };

        const config = {
            type: "bar",
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: "Top Students by Final Average",
                        font: {
                            size: 16,
                            weight: "bold"
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return "Average: " + context.parsed.y.toFixed(2) + "%";
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + "%";
                            }
                        },
                        title: {
                            display: true,
                            text: "Final Average (%)"
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        },
                        title: {
                            display: true,
                            text: "Students"
                        }
                    }
                }
            }
        };

        const studentRankingChart = new Chart(ctx, config);

        // CSV Export function
        function exportTableToCSV(filename) {
            var csv = [];
            var rows = document.querySelectorAll("#studentAveragesRankingTable tr");

            for (var i = 0; i < rows.length; i++) {
                var row = [];
                var cols = rows[i].querySelectorAll("td, th");

                for (var j = 0; j < cols.length; j++) {
                    var text = cols[j].innerText || "";
                    text = text.replace(/"/g, \'"\'); // Escape quotes
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
        echo Format::alert(__('No student data found matching the selected criteria.'), 'message');
    }
}
