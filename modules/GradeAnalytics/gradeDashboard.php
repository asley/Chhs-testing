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
use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;

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
    $classID = $_GET['classID'] ?? '';
    $formGroupID = $_GET['formGroupID'] ?? '';
    $teacherID = $_GET['teacherID'] ?? '';
    $yearGroup = $_GET['yearGroup'] ?? '';
    $assessmentType = $_GET['assessmentType'] ?? '';
    $assessmentName = $_GET['assessmentName'] ?? '';

    // Get filter options using the database connection
    $gateway = $container->get(GradeAnalyticsGateway::class);

    $courses = getCourses($connection2);
    $classes = $gateway->selectClasses($_SESSION[$guid]['gibbonSchoolYearID'])->fetchAll();
    $formGroups = getFormGroups($connection2);
    $teachers = getTeachers($connection2);
    $yearGroups = getGradeAnalyticsYearGroups($connection2);
    $assessmentTypes = getInternalAssessmentTypes($connection2);
    $assessmentColumns = $gateway->selectAssessmentColumns($gibbonSchoolYearID)->fetchAll();

    // Add module assets with cache-busting so UI/script changes load immediately.
    $cssPath = __DIR__ . '/assets/css/gradeAnalytics.css';
    $jsPath = __DIR__ . '/assets/js/interactive-charts.js';
    $cssVersion = is_file($cssPath) ? filemtime($cssPath) : time();
    $jsVersion = is_file($jsPath) ? filemtime($jsPath) : time();

    $page->stylesheets->add('gradeAnalytics', '/modules/GradeAnalytics/assets/css/gradeAnalytics.css?v='.$cssVersion);

    // Add jQuery for dynamic filtering
    echo "<script src='".$session->get('absoluteURL')."/lib/jquery/jquery.js'></script>";

    // Add interactive charts JavaScript
    echo "<script src='".$session->get('absoluteURL')."/modules/GradeAnalytics/assets/js/interactive-charts.js?v=".$jsVersion."'></script>";

    // Set base URL for AJAX requests
    echo '<script>document.body.setAttribute("data-absolute-url", "' . $_SESSION[$guid]['absoluteURL'] . '");</script>';

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

    echo '<div class="row">';

    // Main content area with improved layout
    echo '<div class="col-12">';

    // Use the gateway's own restriction logic so the UI matches the data queries.
    $isRestricted = $gateway->isRestrictedToOwnClasses();

    // Show info message for teachers
    if ($isRestricted) {
        echo Format::alert('<strong>' . __('Teacher View:') . '</strong> ' . __('You are viewing analytics for your assigned classes only.'), 'info');
    }

    // Start the filter form with the user's preferred layout
    echo '<div class="ga-filter-card" style="background:#fff;border:1px solid #e3e8f3;border-radius:12px;box-shadow:0 10px 24px rgba(15,23,42,0.06);margin:1rem 0 1.75rem;overflow:hidden;">';
    echo '<div class="ga-filter-card-header" style="background:linear-gradient(90deg,#4e73df,#3b5bcb);color:#fff;padding:0.85rem 1.25rem;">';
    echo '<h6 class="ga-filter-card-title">' . __('Data Analysis Dashboard') . '</h6>';
    echo '</div>';
    echo '<form id="filterForm" class="ga-filter-form" method="get" action="' . $_SESSION[$guid]['absoluteURL'] . '/index.php" style="margin:0;">';
    echo '<input type="hidden" name="q" value="/modules/GradeAnalytics/gradeDashboard.php">';
    echo '<div class="ga-filter-card-body" style="padding:1.5rem 1.5rem 1.25rem;">';
    echo '<div class="ga-filter-grid" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));column-gap:1.25rem;row-gap:1rem;align-items:end;width:100%;">';
    
    // Course filter
    echo '<div class="ga-filter-item" style="display:flex;flex-direction:column;gap:0.5rem;min-width:0;margin:0;">';
    echo '<label for="courseID">' . __('Course') . '</label>';
    echo '<select name="courseID" id="courseID" onchange="updateFormGroups(this.value)" style="display:block;width:100%;min-width:0;height:46px;padding:0.65rem 0.85rem;border:1px solid #d8e0ef;border-radius:10px;background:#fff;color:#1f2937;">';
    echo '<option value="">' . __('All Courses') . '</option>';
    if (!empty($courses)) {
        foreach ($courses as $course) {
            $selected = ($courseID == $course['value']) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($course['value']) . '" ' . $selected . '>' . htmlspecialchars($course['name']) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';

    // Class filter
    echo '<div class="ga-filter-item" style="display:flex;flex-direction:column;gap:0.5rem;min-width:0;margin:0;">';
    echo '<label for="classID">' . __('Class') . '</label>';
    echo '<select name="classID" id="classID" style="display:block;width:100%;min-width:0;height:46px;padding:0.65rem 0.85rem;border:1px solid #d8e0ef;border-radius:10px;background:#fff;color:#1f2937;">';
    echo '<option value="">' . __('All Classes') . '</option>';
    if (!empty($classes)) {
        foreach ($classes as $class) {
            $selected = ($classID == $class['value']) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($class['value']) . '" ' . $selected . '>' . htmlspecialchars($class['name']) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';

    // Form Group filter
    echo '<div class="ga-filter-item" style="display:flex;flex-direction:column;gap:0.5rem;min-width:0;margin:0;">';
    echo '<label for="formGroupID">' . __('Form Group') . '</label>';
    echo '<select name="formGroupID" id="formGroupID" style="display:block;width:100%;min-width:0;height:46px;padding:0.65rem 0.85rem;border:1px solid #d8e0ef;border-radius:10px;background:#fff;color:#1f2937;">';
    echo '<option value="">' . __('All Form Groups') . '</option>';
    if (!empty($formGroups)) {
        foreach ($formGroups as $formGroup) {
            $selected = ($formGroupID == $formGroup['value']) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($formGroup['value']) . '" ' . $selected . '>' . htmlspecialchars($formGroup['name']) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';

    // Year Group filter
    echo '<div class="ga-filter-item" style="display:flex;flex-direction:column;gap:0.5rem;min-width:0;margin:0;">';
    echo '<label for="yearGroup">' . __('Year Group') . '</label>';
    echo '<select name="yearGroup" id="yearGroup" style="display:block;width:100%;min-width:0;height:46px;padding:0.65rem 0.85rem;border:1px solid #d8e0ef;border-radius:10px;background:#fff;color:#1f2937;">';
    echo '<option value="">' . __('All Year Groups') . '</option>';
    if (!empty($yearGroups)) {
        foreach ($yearGroups as $group) {
            $selected = ($yearGroup == $group['value']) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($group['value']) . '" ' . $selected . '>' . htmlspecialchars($group['name']) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';

    // Assessment Type filter
    echo '<div class="ga-filter-item" style="display:flex;flex-direction:column;gap:0.5rem;min-width:0;margin:0;">';
    echo '<label for="assessmentType">' . __('Assessment Type') . '</label>';
    echo '<select name="assessmentType" id="assessmentType" style="display:block;width:100%;min-width:0;height:46px;padding:0.65rem 0.85rem;border:1px solid #d8e0ef;border-radius:10px;background:#fff;color:#1f2937;">';
    echo '<option value="">' . __('All Types') . '</option>';
    if (!empty($assessmentTypes)) {
        foreach ($assessmentTypes as $type) {
            $selected = ($assessmentType == $type) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($type) . '" ' . $selected . '>' . htmlspecialchars($type) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';

    // Assessment filter
    echo '<div class="ga-filter-item" style="display:flex;flex-direction:column;gap:0.5rem;min-width:0;margin:0;">';
    echo '<label for="assessmentName">' . __('Assessment') . '</label>';
    echo '<select name="assessmentName" id="assessmentName" style="display:block;width:100%;min-width:0;height:46px;padding:0.65rem 0.85rem;border:1px solid #d8e0ef;border-radius:10px;background:#fff;color:#1f2937;">';
    echo '<option value="">' . __('All Assessments') . '</option>';
    if (!empty($assessmentColumns)) {
        foreach ($assessmentColumns as $assessmentColumn) {
            if (!isset($assessmentColumn['value'], $assessmentColumn['name'])) {
                continue;
            }
            $selected = ($assessmentName == $assessmentColumn['value']) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($assessmentColumn['value']) . '" ' . $selected . '>' . htmlspecialchars($assessmentColumn['name']) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';

    // Teacher filter
    echo '<div class="ga-filter-item" style="display:flex;flex-direction:column;gap:0.5rem;min-width:0;margin:0;">';
    echo '<label for="teacherID">' . __('Teacher') . '</label>';
    echo '<select name="teacherID" id="teacherID" style="display:block;width:100%;min-width:0;height:46px;padding:0.65rem 0.85rem;border:1px solid #d8e0ef;border-radius:10px;background:#fff;color:#1f2937;">';
    echo '<option value="">' . __('All Teachers') . '</option>';
    if (!empty($teachers)) {
        foreach ($teachers as $teacher) {
            $selected = ($teacherID == $teacher['value']) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($teacher['value']) . '" ' . $selected . '>' . htmlspecialchars($teacher['name']) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';

    // Apply Filters button
    echo '<div class="ga-filter-actions" style="grid-column:1 / -1;display:flex;justify-content:flex-end;align-items:center;margin-top:0.5rem;padding-top:0.25rem;">';
    echo '<button type="submit" class="ga-btn-apply" style="display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;min-width:170px;padding:0.75rem 1.4rem;color:#fff;background:linear-gradient(90deg,#4e73df,#375ad3);border:none;border-radius:10px;font-weight:600;box-shadow:0 10px 20px rgba(78,115,223,0.2);cursor:pointer;">';
    echo '<i class="fas fa-filter" style="margin-right: 0.5rem;"></i>' . __('Apply Filters');
    echo '</button>';
    echo '</div>';

    echo '</div>'; // End ga-filter-grid
    echo '</div>'; // End ga-filter-card-body
    echo '</form>';
    echo '</div>'; // End ga-filter-card


    // Get grade distribution data with all filters
    $gradeData = getGradeDistribution($connection2, $courseID, $formGroupID, $teacherID, $yearGroup, $assessmentType, $classID, $assessmentName);
    
    // Debug log the grade data
    error_log('Grade Data received in dashboard: ' . json_encode($gradeData));

    // Prepare data for charts
    $labels = array();
    $data = array();
    
    // Ensure we have data for all grades in correct order
    $gradeOrder = ['A', 'B', 'C', 'D', 'E'];
    $gradeDataMap = array();
    
    // Create a map of grade => count from the query results
    if (!empty($gradeData)) {
        foreach ($gradeData as $grade) {
            $gradeDataMap[$grade['grade']] = intval($grade['count']);
        }
    }
    
    // Build final arrays ensuring all grades are represented
    foreach ($gradeOrder as $grade) {
        $labels[] = $grade;
        $data[] = isset($gradeDataMap[$grade]) ? $gradeDataMap[$grade] : 0;
    }
    
    error_log('Prepared chart data - Labels: ' . json_encode($labels));
    error_log('Prepared chart data - Data: ' . json_encode($data));

    // Chart colors with consistent colors for each grade
    $dataColors = array();
    $dataBorders = array();
    
    foreach ($labels as $grade) {
        switch ($grade) {
            case 'A':
                $dataColors[] = 'rgba(0, 200, 81, 0.5)';    // Green
                $dataBorders[] = 'rgba(0, 200, 81, 1)';
                break;
            case 'B':
                $dataColors[] = 'rgba(54, 162, 235, 0.5)';  // Blue
                $dataBorders[] = 'rgba(54, 162, 235, 1)';
                break;
            case 'C':
                $dataColors[] = 'rgba(255, 206, 86, 0.5)';  // Yellow
                $dataBorders[] = 'rgba(255, 206, 86, 1)';
                break;
            case 'D':
                $dataColors[] = 'rgba(75, 192, 192, 0.5)';  // Teal
                $dataBorders[] = 'rgba(75, 192, 192, 1)';
                break;
            case 'E':
                $dataColors[] = 'rgba(255, 99, 132, 0.5)';  // Red
                $dataBorders[] = 'rgba(255, 99, 132, 1)';
                break;
        }
    }

    // Clickable grade scale cards
    $gradeScale = [
        ['grade' => 'A', 'range' => '85-100', 'color' => 'rgba(0, 200, 81, 0.85)', 'textColor' => 'white'],
        ['grade' => 'B', 'range' => '70-84', 'color' => 'rgba(54, 162, 235, 0.85)', 'textColor' => 'white'],
        ['grade' => 'C', 'range' => '60-69', 'color' => 'rgba(255, 206, 86, 0.85)', 'textColor' => '#1f2937'],
        ['grade' => 'D', 'range' => '50-59', 'color' => 'rgba(75, 192, 192, 0.85)', 'textColor' => 'white'],
        ['grade' => 'E', 'range' => '0-49', 'color' => 'rgba(255, 99, 132, 0.85)', 'textColor' => 'white'],
    ];

    echo '<div class="card shadow-sm mb-4" style="border-left:4px solid #4e73df;">';
    echo '<div class="card-header py-3" style="background-color:#f8f9fc;border-bottom:1px solid #e3e6f0;">';
    echo '<h6 class="m-0 font-weight-bold text-primary">' . __('Grading Scale') . '</h6>';
    echo '</div>';
    echo '<div class="card-body" style="padding:1.25rem;">';
    echo '<div class="grade-scale-grid" style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:18px;align-items:stretch;">';

    foreach ($gradeScale as $scale) {
        $studentCount = $gradeDataMap[$scale['grade']] ?? 0;
        $countLabel = $studentCount === 1 ? __('1 student') : sprintf(__('%1$s students'), $studentCount);
        echo '<div class="grade-scale-item" onclick="showStudentsByGrade(\'' . $scale['grade'] . '\')" style="background-color:' . $scale['color'] . ';color:' . $scale['textColor'] . ';min-height:140px;padding:24px 16px;border-radius:16px;box-shadow:0 12px 24px rgba(15,23,42,0.12);cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;">';
        echo '<div class="grade-letter" style="font-size:2.25rem;font-weight:800;line-height:1;margin-bottom:10px;">' . $scale['grade'] . '</div>';
        echo '<div class="grade-range" style="font-size:1.1rem;font-weight:700;line-height:1.2;margin-bottom:10px;">' . $scale['range'] . '%</div>';
        echo '<div class="grade-count" style="font-size:0.95rem;font-weight:700;line-height:1.2;margin-bottom:8px;opacity:0.96;">' . htmlspecialchars($countLabel) . '</div>';
        echo '<div class="grade-hint" style="font-size:0.9rem;line-height:1.3;opacity:0.95;">' . __('Click to view students') . '</div>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Chart containers section
    echo '<div class="charts-container">';
    
    // Bar Chart Container
    echo '<div class="card shadow mb-4">';
    echo '<div class="card-header py-3">';
    echo '<h6 class="m-0 font-weight-bold text-primary">' . __('Grade Distribution (Bar Chart)') . '</h6>';
    echo '</div>';
    echo '<div class="card-body" style="padding:1.5rem 2rem; min-height:460px;">';
    echo '<div class="chart-wrapper" style="min-height:380px; height:380px;">';
    echo '<canvas id="gradeDistributionBarChart"></canvas>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Pie Chart Container
    echo '<div class="card shadow mb-4">';
    echo '<div class="card-header py-3">';
    echo '<h6 class="m-0 font-weight-bold text-primary">' . __('Grade Distribution (Pie Chart)') . '</h6>';
    echo '</div>';
    echo '<div class="card-body" style="padding:1.5rem 2rem; min-height:460px;">';
    echo '<div class="chart-wrapper" style="min-height:380px; height:380px;">';
    echo '<canvas id="gradeDistributionPieChart"></canvas>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // End charts-container


    // Add Chart.js from CDN
    echo "<script src='https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js'></script>";

    // Add JavaScript for charts
    // Note: DOMContentLoaded has already fired at this point (mid-page), so run directly.
    echo '<script>';
    echo '(function initCharts() {';
    echo '    const labels = ' . json_encode($labels) . ';';
    echo '    const data = ' . json_encode($data) . ';';

    echo '    function niceScale(values, minTicks = 6, maxTicks = 12) {';
    echo '        const max = Math.max(0, ...values.map(Number).filter(Number.isFinite));';
    echo '        if (max === 0) return { axisMax: 10, stepSize: 2, tickCount: 5 };';
    echo '        const paddedMax = max * 1.08;';
    echo '        const rough = paddedMax / Math.max(maxTicks - 1, 1);';
    echo '        const mag = Math.pow(10, Math.floor(Math.log10(rough)));';
    echo '        const candidates = [];';
    echo '        [0.1, 0.2, 0.25, 0.5, 1, 2, 2.5, 5, 10, 20].forEach(function(multiplier) {';
    echo '            const step = multiplier * mag;';
    echo '            if (step > 0) candidates.push(step);';
    echo '        });';
    echo '        candidates.sort(function(a, b) { return a - b; });';
    echo '        let step = candidates[candidates.length - 1];';
    echo '        let axisMax = Math.ceil(paddedMax / step) * step;';
    echo '        let tickCount = Math.round(axisMax / step) + 1;';
    echo '        for (const candidate of candidates) {';
    echo '            const candidateAxisMax = Math.ceil(paddedMax / candidate) * candidate;';
    echo '            const candidateTickCount = Math.round(candidateAxisMax / candidate) + 1;';
    echo '            if (candidateTickCount <= maxTicks && candidateTickCount >= minTicks) {';
    echo '                step = candidate;';
    echo '                axisMax = candidateAxisMax;';
    echo '                tickCount = candidateTickCount;';
    echo '                break;';
    echo '            }';
    echo '        }';
    echo '        return { axisMax, stepSize: step, tickCount: tickCount };';
    echo '    }';
    echo '    const yScale = niceScale(data);';

    echo '    const commonDataset = {';
    echo '        labels: labels,';
    echo '        datasets: [{ label: "Number of Students", data: data,';
    echo '            backgroundColor: ' . json_encode($dataColors) . ',';
    echo '            borderColor: ' . json_encode($dataBorders) . ', borderWidth: 1 }]';
    echo '    };';

    echo '    const commonOptions = {';
    echo '        responsive: true, maintainAspectRatio: false,';
    echo '        plugins: {';
    echo '            legend: { display: true, position: "top" },';
    echo '            tooltip: { callbacks: { footer: function() { return "Click to view student details"; } } }';
    echo '        },';
    echo '        onClick: function(event, elements) {';
    echo '            if (elements.length > 0) showStudentsByGrade(labels[elements[0].index]);';
    echo '        },';
    echo '        onHover: function(event, elements) {';
    echo '            event.native.target.style.cursor = elements.length > 0 ? "pointer" : "default";';
    echo '        }';
    echo '    };';

    echo '    try {';
    echo '        const barCtx = document.getElementById("gradeDistributionBarChart");';
    echo '        if (barCtx) {';
    echo '            new Chart(barCtx, { type: "bar", data: commonDataset, options: {';
    echo '                ...commonOptions,';
    echo '                scales: { y: {';
    echo '                    beginAtZero: true,';
    echo '                    max: yScale.axisMax,';
    echo '                    ticks: { stepSize: yScale.stepSize, maxTicksLimit: yScale.tickCount, autoSkip: false, precision: 0, padding: 10 },';
    echo '                    grid: { drawBorder: false }';
    echo '                }}';
    echo '            }});';
    echo '        }';

    echo '        const pieCtx = document.getElementById("gradeDistributionPieChart");';
    echo '        if (pieCtx) {';
    echo '            new Chart(pieCtx, { type: "pie", data: commonDataset, options: {';
    echo '                ...commonOptions,';
    echo '                plugins: { legend: { position: "right" } }';
    echo '            }});';
    echo '        }';
    echo '    } catch (error) { console.error("Error creating charts:", error); }';
    echo '})();';
    echo '</script>';


    echo '</div>'; // End col-12
    echo '</div>'; // End row
    echo '</div>'; // End container-fluid

    // Student Details Modal
    echo '<div id="studentModal" class="ga-modal-overlay">';
    echo '<div class="ga-modal-content">';

    // Modal Header
    echo '<div class="ga-modal-header">';
    echo '<h5 id="modalTitle" class="ga-modal-title">Students</h5>';
    echo '<button onclick="closeStudentModal()" class="ga-modal-close">&times;</button>';
    echo '</div>';

    // Modal Body
    echo '<div id="modalBody" class="ga-modal-body">';
    echo '<div id="studentList"></div>';
    echo '</div>';

    // Modal Footer
    echo '<div class="ga-modal-footer">';
    echo '<button onclick="closeStudentModal()" class="ga-btn-secondary">Close</button>';
    echo '</div>';

    echo '</div>'; // End modal content
    echo '</div>'; // End modal
} 
