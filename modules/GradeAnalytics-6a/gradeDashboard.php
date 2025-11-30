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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Bootstrap Gibbon core
$gibbon_path = realpath(dirname(__FILE__) . '/../../');
if (!is_file($gibbon_path.'/gibbon.php')) {
    die('Your ../../gibbon.php file does not exist. Please check your file path and try again.');
}
require_once $gibbon_path.'/gibbon.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;

// Common variables
$gibbonSchoolYearID = $_SESSION[$guid]['gibbonSchoolYearID'];
$gibbonPersonID = $_SESSION[$guid]['gibbonPersonID'];
$address = $_SESSION[$guid]['address'];
$URL = $_SESSION[$guid]['absoluteURL'];

// Setup page title and breadcrumb
$page->breadcrumbs
    ->add(__('Grade Analytics'));

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/gradeDashboard.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get filter parameters
    $courseID = $_GET['courseID'] ?? '';
    $formGroupID = $_GET['formGroupID'] ?? '';
    $teacherID = $_GET['teacherID'] ?? '';
    $assessmentType = $_GET['assessmentType'] ?? '';

    // Get filter options using the database connection
    $courses = getCourses($connection2);
    $formGroups = getFormGroups($connection2);
    $teachers = getTeachers($connection2);
    $assessmentTypes = getAssessmentTypes($connection2);

    // Add module CSS
    $page->stylesheets->add('gradeAnalytics', $session->get('absoluteURL').'/modules/GradeAnalytics/assets/css/gradeAnalytics.css');

    // Add jQuery for dynamic filtering
    echo "<script src='".$session->get('absoluteURL')."/lib/jquery/jquery.min.js'></script>";
    echo "<script>
        $(document).ready(function() {
            $('#courseID').change(function() {
                var courseID = $(this).val();
                $.ajax({
                    url: '".$session->get('absoluteURL')."/modules/GradeAnalytics/ajax_getAssessmentColumns.php',
                    type: 'POST',
                    data: {courseID: courseID},
                    success: function(data) {
                        $('#assessmentColumnID').html(data);
                    }
                });
            });
        });
    </script>";

    // Output the page header
    echo '<div class="container-fluid">';
    
    // Add a top bar for the filter button
    echo '<div class="top-filter-bar mb-4">';
    echo '<button type="submit" class="btn btn-primary btn-filter" form="filterForm">';
    echo '<i class="fas fa-filter mr-2"></i>Apply Filters';
    echo '</button>';
    echo '</div>';
    
    echo '<div class="row">';
    
    // Filters sidebar with improved layout
    echo '<div class="col-lg-3 col-md-4 mb-4">';
    echo '<div class="filter-card card shadow-sm">';
    echo '<div class="card-header">';
    echo '<h6 class="filter-title m-0">Filters</h6>';
    echo '</div>';
    echo '<div class="card-body">';

    // Create filter form with ID
    echo '<form method="get" action="' . $_SESSION[$guid]['absoluteURL'] . '/index.php" class="filterForm" id="filterForm">';
    echo '<input type="hidden" name="q" value="/modules/GradeAnalytics/gradeDashboard.php">';

    // Filter grid container
    echo '<div class="filter-container">';

    // Course filter
    echo '<div class="filter-item">';
    echo '<label for="courseID" class="form-label">Course</label>';
    echo '<div class="select-wrapper">';
    echo '<select name="courseID" id="courseID" class="form-control">';
    echo '<option value="">All Courses</option>';
    if (!empty($courses)) {
        foreach ($courses as $course) {
            $selected = ($courseID == $course['value']) ? 'selected' : '';
            echo '<option value="' . $course['value'] . '" ' . $selected . '>' . $course['name'] . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    // Assessment Type filter
    echo '<div class="filter-item">';
    echo '<label for="assessmentType" class="form-label">Assessment Type</label>';
    echo '<div class="select-wrapper">';
    echo '<select name="assessmentType" id="assessmentType" class="form-control">';
    echo '<option value="">All Types</option>';
    if (!empty($assessmentTypes)) {
        foreach ($assessmentTypes as $type) {
            $selected = ($assessmentType == $type['value']) ? 'selected' : '';
            echo '<option value="' . $type['value'] . '" ' . $selected . '>' . $type['name'] . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    // Form Group filter
    echo '<div class="filter-item">';
    echo '<label for="formGroupID" class="form-label">Form Group</label>';
    echo '<div class="select-wrapper">';
    echo '<select name="formGroupID" id="formGroupID" class="form-control">';
    echo '<option value="">All Form Groups</option>';
    if (!empty($formGroups)) {
        foreach ($formGroups as $group) {
            $selected = ($formGroupID == $group['value']) ? 'selected' : '';
            echo '<option value="' . $group['value'] . '" ' . $selected . '>' . $group['name'] . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    // Teacher filter (remove the inline button since we moved it to top)
    echo '<div class="filter-item teacher-filter">';
    echo '<label for="teacherID" class="form-label">Teacher</label>';
    echo '<div class="select-wrapper teacher-select">';
    echo '<select name="teacherID" id="teacherID" class="form-control">';
    echo '<option value="">All Teachers</option>';
    if (!empty($teachers)) {
        foreach ($teachers as $teacher) {
            $selected = ($teacherID == $teacher['value']) ? 'selected' : '';
            echo '<option value="' . $teacher['value'] . '" ' . $selected . '>' . $teacher['name'] . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';
    echo '</div>'; // End teacher-filter

    echo '</div>'; // End filter-container
    echo '</form>';
    echo '</div>'; // End card-body
    echo '</div>'; // End card
    echo '</div>'; // End col

    // Main content area
    echo '<div class="col-md-9">';
    
    // Get grade distribution data with assessment column filter
    $gradeData = getGradeDistribution($connection2, $courseID, $formGroupID, $teacherID, $assessmentType);
    
    // Prepare data for charts
    $labels = array();
    $data = array();
    foreach ($gradeData as $grade) {
        $labels[] = $grade['grade'];
        $data[] = $grade['count'];
    }

    // Add Chart.js from CDN in the header
    $page->scripts->add('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', ['weight' => 0]);

    // Grade Distribution Bar Chart
    echo '<div class="card shadow mb-4">';
    echo '<div class="card-header py-3 d-flex justify-content-between align-items-center">';
    echo '<h6 class="m-0 font-weight-bold text-primary">Grade Distribution (Bar Chart)</h6>';
    echo '</div>';
    echo '<div class="card-body" style="height: 400px;">'; // Fixed height for better visibility
    if (empty($gradeData)) {
        echo '<div class="alert alert-info">No grade data available for the selected filters.</div>';
    } else {
        echo '<canvas id="gradeDistributionBarChart"></canvas>';
    }
    echo '</div>';
    echo '</div>';

    // Grade Distribution Pie Chart
    echo '<div class="card shadow mb-4">';
    echo '<div class="card-header py-3 d-flex justify-content-between align-items-center">';
    echo '<h6 class="m-0 font-weight-bold text-primary">Grade Distribution (Pie Chart)</h6>';
    echo '</div>';
    echo '<div class="card-body" style="height: 400px;">'; // Fixed height for better visibility
    if (empty($gradeData)) {
        echo '<div class="alert alert-info">No grade data available for the selected filters.</div>';
    } else {
        echo '<canvas id="gradeDistributionPieChart"></canvas>';
    }
    echo '</div>';
    echo '</div>';

    // Grade Distribution Line Chart
    echo '<div class="card shadow mb-4">';
    echo '<div class="card-header py-3 d-flex justify-content-between align-items-center">';
    echo '<h6 class="m-0 font-weight-bold text-primary">Grade Distribution (Line Chart)</h6>';
    echo '</div>';
    echo '<div class="card-body" style="height: 400px;">'; // Fixed height for better visibility
    if (empty($gradeData)) {
        echo '<div class="alert alert-info">No grade data available for the selected filters.</div>';
    } else {
        echo '<canvas id="gradeDistributionLineChart"></canvas>';
    }
    echo '</div>';
    echo '</div>';

    // Only add chart initialization if we have data
    if (!empty($gradeData)) {
        // Chart colors with consistent colors for each grade
        $backgroundColors = [
            'A+' => 'rgba(0, 200, 81, 0.5)',    // Green
            'A' => 'rgba(54, 162, 235, 0.5)',   // Blue
            'B' => 'rgba(255, 206, 86, 0.5)',   // Yellow
            'C' => 'rgba(75, 192, 192, 0.5)',   // Teal
            'D' => 'rgba(153, 102, 255, 0.5)',  // Purple
            'F' => 'rgba(255, 99, 132, 0.5)'    // Red
        ];
        $borderColors = [
            'A+' => 'rgba(0, 200, 81, 1)',
            'A' => 'rgba(54, 162, 235, 1)',
            'B' => 'rgba(255, 206, 86, 1)',
            'C' => 'rgba(75, 192, 192, 1)',
            'D' => 'rgba(153, 102, 255, 1)',
            'F' => 'rgba(255, 99, 132, 1)'
        ];

        // Map colors to data
        $dataColors = array_map(function($grade) use ($backgroundColors) {
            return $backgroundColors[$grade];
        }, $labels);
        
        $dataBorders = array_map(function($grade) use ($borderColors) {
            return $borderColors[$grade];
        }, $labels);

        // Add JavaScript for charts
        echo '<script>';
        echo 'window.addEventListener("load", function() {';
        echo '    if (typeof Chart !== "undefined") {';
        
        // Bar Chart
        echo '        const barCtx = document.getElementById("gradeDistributionBarChart");';
        echo '        if (barCtx) {';
        echo '            new Chart(barCtx, {';
        echo '                type: "bar",';
        echo '                data: {';
        echo '                    labels: ' . json_encode($labels) . ',';
        echo '                    datasets: [{';
        echo '                        label: "Number of Students",';
        echo '                        data: ' . json_encode($data) . ',';
        echo '                        backgroundColor: ' . json_encode($dataColors) . ',';
        echo '                        borderColor: ' . json_encode($dataBorders) . ',';
        echo '                        borderWidth: 1';
        echo '                    }]';
        echo '                },';
        echo '                options: {';
        echo '                    responsive: true,';
        echo '                    maintainAspectRatio: false,';
        echo '                    plugins: {';
        echo '                        legend: {';
        echo '                            display: false';
        echo '                        }';
        echo '                    },';
        echo '                    scales: {';
        echo '                        y: {';
        echo '                            beginAtZero: true,';
        echo '                            ticks: { stepSize: 1 }';
        echo '                        }';
        echo '                    }';
        echo '                }';
        echo '            });';
        echo '        }';

        // Pie Chart
        echo '        const pieCtx = document.getElementById("gradeDistributionPieChart");';
        echo '        if (pieCtx) {';
        echo '            new Chart(pieCtx, {';
        echo '                type: "pie",';
        echo '                data: {';
        echo '                    labels: ' . json_encode($labels) . ',';
        echo '                    datasets: [{';
        echo '                        data: ' . json_encode($data) . ',';
        echo '                        backgroundColor: ' . json_encode($dataColors) . ',';
        echo '                        borderColor: ' . json_encode($dataBorders) . ',';
        echo '                        borderWidth: 1';
        echo '                    }]';
        echo '                },';
        echo '                options: {';
        echo '                    responsive: true,';
        echo '                    maintainAspectRatio: false,';
        echo '                    plugins: {';
        echo '                        legend: {';
        echo '                            position: "right",';
        echo '                            labels: {';
        echo '                                padding: 20';
        echo '                            }';
        echo '                        }';
        echo '                    }';
        echo '                }';
        echo '            });';
        echo '        }';

        // Line Chart
        echo '        const lineCtx = document.getElementById("gradeDistributionLineChart");';
        echo '        if (lineCtx) {';
        echo '            new Chart(lineCtx, {';
        echo '                type: "line",';
        echo '                data: {';
        echo '                    labels: ' . json_encode($labels) . ',';
        echo '                    datasets: [{';
        echo '                        label: "Grade Distribution Trend",';
        echo '                        data: ' . json_encode($data) . ',';
        echo '                        fill: false,';
        echo '                        borderColor: "rgba(75, 192, 192, 1)",';
        echo '                        tension: 0.1,';
        echo '                        pointBackgroundColor: ' . json_encode($dataColors) . ',';
        echo '                        pointBorderColor: ' . json_encode($dataBorders) . ',';
        echo '                        pointRadius: 6';
        echo '                    }]';
        echo '                },';
        echo '                options: {';
        echo '                    responsive: true,';
        echo '                    maintainAspectRatio: false,';
        echo '                    plugins: {';
        echo '                        legend: {';
        echo '                            display: false';
        echo '                        }';
        echo '                    },';
        echo '                    scales: {';
        echo '                        y: {';
        echo '                            beginAtZero: true,';
        echo '                            ticks: { stepSize: 1 }';
        echo '                        }';
        echo '                    }';
        echo '                }';
        echo '            });';
        echo '        }';
        
        echo '    } else {';
        echo '        console.error("Chart.js is not loaded");';
        echo '    }';
        echo '});';
        echo '</script>';
    }

    echo '</div>'; // End col-md-9
    echo '</div>'; // End row
    echo '</div>'; // End container-fluid
} 