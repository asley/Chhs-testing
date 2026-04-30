<?php

use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;

include '../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

if (!function_exists('formatReportingCycleAverage')) {
    function formatReportingCycleAverage(array $grades) : string
    {
        $numericGrades = [];

        foreach ($grades as $grade) {
            $grade = trim((string) $grade);
            if ($grade === '') {
                continue;
            }

            $normalized = rtrim($grade, '%');
            if (is_numeric($normalized)) {
                $numericGrades[] = (float) $normalized;
            }
        }

        if (empty($numericGrades)) {
            return '';
        }

        $average = array_sum($numericGrades) / count($numericGrades);

        return rtrim(rtrim(number_format($average, 1, '.', ''), '0'), '.').'%';
    }
}

if (isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/reportingCycleGradeReport.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $gateway = $container->get(GradeAnalyticsGateway::class);

    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $formGroupID = $_GET['formGroupID'] ?? '';
    $yearGroup = $_GET['yearGroup'] ?? '';

    $filters = [
        'formGroupID' => $formGroupID,
        'yearGroup' => $yearGroup,
    ];

    $results = $gateway->selectReportingCycleGradeExport($gibbonSchoolYearID, $filters)->fetchAll(\PDO::FETCH_ASSOC);

    echo '<h2>'.__('Reporting Cycle Grade Report').'</h2>';

    if (empty($results)) {
        echo '<div class="error">'.__('No grade data found for the selected filters.').'</div>';
        return;
    }

    $schoolYears = $gateway->selectSchoolYears()->fetchKeyPair();
    $formGroups = $gateway->selectFormGroups($gibbonSchoolYearID)->fetchKeyPair();
    $yearGroups = $gateway->selectYearGroups($gibbonSchoolYearID)->fetchKeyPair();

    echo '<table class="smallIntBorder" cellspacing="0" style="width: 100%; margin-bottom: 20px;">';
    echo '<tr><td style="width: 20%"><b>'.__('School Year').'</b></td><td>'.htmlspecialchars($schoolYears[$gibbonSchoolYearID] ?? '').'</td></tr>';
    echo '<tr><td><b>'.__('Form Class').'</b></td><td>'.htmlspecialchars($formGroups[$formGroupID] ?? __('All Form Classes')).'</td></tr>';
    echo '<tr><td><b>'.__('Year Group').'</b></td><td>'.htmlspecialchars($yearGroups[$yearGroup] ?? __('All Year Groups')).'</td></tr>';
    echo '</table>';

    $columnMap = [];
    $columnOrder = [];
    $rowMap = [];

    foreach ($results as $entry) {
        $cycleLabel = !empty($entry['reportingCycleShortName']) ? $entry['reportingCycleShortName'] : $entry['reportingCycleName'];
        $columnKey = trim((string) $cycleLabel);

        if ($columnKey === '') {
            $columnKey = trim((string) $entry['assessmentName']);
        }

        if (!isset($columnMap[$columnKey])) {
            $columnMap[$columnKey] = [
                'label' => $columnKey,
                'sequence' => (int) $entry['reportingCycleSequence'],
            ];
            $columnOrder[] = $columnKey;
        }

        $studentRowKey = (string) $entry['gibbonPersonID'];

        if (!isset($rowMap[$studentRowKey])) {
            $rowMap[$studentRowKey] = [
                'studentName' => trim($entry['preferredName'].' '.$entry['surname']),
                'formGroup' => $entry['formGroup'],
                'yearGroup' => $entry['yearGroup'],
                'grades' => [],
            ];
        }

        $rowMap[$studentRowKey]['grades'][$columnKey][] = $entry['grade'];
    }

    usort($columnOrder, function ($a, $b) use ($columnMap) {
        $left = $columnMap[$a];
        $right = $columnMap[$b];

        return [$left['sequence'], $left['label']] <=> [$right['sequence'], $right['label']];
    });

    echo '<table class="fullWidth colorOddEven" cellspacing="0" style="font-size: 11px;">';
    echo '<thead><tr>';
    echo '<th>'.__('Student Name').'</th>';
    echo '<th>'.__('Form Class').'</th>';
    echo '<th>'.__('Year Group').'</th>';
    foreach ($columnOrder as $columnKey) {
        echo '<th>'.htmlspecialchars($columnMap[$columnKey]['label']).'</th>';
    }
    echo '</tr></thead><tbody>';

    foreach (array_values($rowMap) as $rowData) {
        echo '<tr>';
        echo '<td>'.htmlspecialchars($rowData['studentName']).'</td>';
        echo '<td>'.htmlspecialchars($rowData['formGroup']).'</td>';
        echo '<td>'.htmlspecialchars($rowData['yearGroup']).'</td>';
        foreach ($columnOrder as $columnKey) {
            echo '<td style="text-align: center;">'.htmlspecialchars(formatReportingCycleAverage($rowData['grades'][$columnKey] ?? [])).'</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
}
