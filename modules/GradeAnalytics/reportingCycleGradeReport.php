<?php

use Gibbon\Forms\Form;
use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;

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
    $page->breadcrumbs
        ->add(__('Grade Analytics'), 'gradeDashboard.php')
        ->add(__('Reporting Cycle Grade Report'));

    echo '<h2>'.__('Reporting Cycle Grade Report').'</h2>';
    echo '<p>'.__('Display the average grade for each student in each marksheet or reporting cycle.').'</p>';

    $gateway = $container->get(GradeAnalyticsGateway::class);

    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $formGroupID = $_GET['formGroupID'] ?? '';
    $yearGroup = $_GET['yearGroup'] ?? '';

    $form = Form::create('filterForm', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder fullWidth');
    $form->addHiddenValue('q', '/modules/GradeAnalytics/reportingCycleGradeReport.php');

    $row = $form->addRow();
    $row->addLabel('gibbonSchoolYearID', __('School Year'));
    $row->addSelect('gibbonSchoolYearID')
        ->fromArray($gateway->selectSchoolYears()->fetchKeyPair())
        ->selected($gibbonSchoolYearID)
        ->required();

    $row = $form->addRow();
    $row->addLabel('formGroupID', __('Form Class'));
    $row->addSelect('formGroupID')
        ->fromArray($gateway->selectFormGroups($gibbonSchoolYearID)->fetchKeyPair())
        ->placeholder(__('All Form Classes'))
        ->selected($formGroupID);

    $row = $form->addRow();
    $row->addLabel('yearGroup', __('Year Group'));
    $row->addSelect('yearGroup')
        ->fromArray($gateway->selectYearGroups($gibbonSchoolYearID)->fetchKeyPair())
        ->placeholder(__('All Year Groups'))
        ->selected($yearGroup);

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Apply Filters'));

    echo $form->getOutput();

    if (!empty($_GET) && isset($_GET['q'])) {
        $filters = [
            'formGroupID' => $formGroupID,
            'yearGroup' => $yearGroup,
        ];

        $results = $gateway->selectReportingCycleGradeExport($gibbonSchoolYearID, $filters)->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($results)) {
            echo \Gibbon\Services\Format::alert(__('No grade data found for the selected filters.'), 'message');
            return;
        }

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
                    'key' => $columnKey,
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

        $tableRows = array_values($rowMap);

        echo '<div class="linkTop">';
        echo '<a target="_blank" href="'.$session->get('absoluteURL').'/report.php?q=/modules/GradeAnalytics/reportingCycleGradeReport_print.php&'.http_build_query($_GET).'">';
        echo __('Print').' <img style="margin-left: 5px" title="'.__('Print').'" src="./themes/'.$session->get('gibbonThemeName').'/img/print.png"/>';
        echo '</a> | ';
        echo '<a href="#" class="button" onclick="downloadReportingCycleCSV(); return false;">'.__('Download CSV').'</a>';
        echo '</div>';

        echo '<div class="overflow-x-auto">';
        echo '<table class="fullWidth colorOddEven" cellspacing="0" id="reportingCycleGradeTable" style="font-size: 0.92em;">';
        echo '<thead><tr>';
        echo '<th>'.__('Student Name').'</th>';
        echo '<th>'.__('Form Class').'</th>';
        echo '<th>'.__('Year Group').'</th>';
        foreach ($columnOrder as $columnKey) {
            echo '<th style="min-width: 120px; text-align: center;">'.htmlspecialchars($columnMap[$columnKey]['label']).'</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($tableRows as $rowData) {
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
        echo '</div>';

        echo '<script>';
        echo 'function downloadReportingCycleCSV(){';
        echo 'var columns='.json_encode(array_values(array_map(function ($key) use ($columnMap) {
            return $columnMap[$key]['label'];
        }, $columnOrder))).';';
        echo 'var rows='.json_encode($tableRows).';';
        echo 'var keys='.json_encode(array_values($columnOrder)).';';
        echo 'var csv=[];';
        echo 'function formatAverage(grades){var numeric=(grades||[]).map(function(grade){return String(grade).trim().replace(/%$/, "");}).filter(function(value){return value !== "" && !isNaN(value);}).map(function(value){return parseFloat(value);}); if(!numeric.length){return "";} var avg=numeric.reduce(function(sum, value){return sum+value;}, 0)/numeric.length; return String(avg % 1 === 0 ? avg.toFixed(0) : avg.toFixed(1)).replace(/\.0$/, "") + "%";}';
        echo 'csv.push(["Student Name","Form Class","Year Group"].concat(columns).map(function(cell){return \'"\'+String(cell).replace(/"/g, \'""\')+\'"\';}).join(","));';
        echo 'rows.forEach(function(row){var values=[row.studentName,row.formGroup,row.yearGroup];keys.forEach(function(key){values.push(formatAverage((row.grades && row.grades[key]) ? row.grades[key] : []));});csv.push(values.map(function(cell){return \'"\'+String(cell).replace(/"/g, \'""\')+\'"\';}).join(","));});';
        echo 'var blob=new Blob([csv.join("\\n")],{type:"text/csv;charset=utf-8;"});';
        echo 'var url=window.URL.createObjectURL(blob);';
        echo 'var link=document.createElement("a");';
        echo 'link.href=url;';
        echo 'link.download="reporting-cycle-grade-report.csv";';
        echo 'document.body.appendChild(link);';
        echo 'link.click();';
        echo 'document.body.removeChild(link);';
        echo 'window.URL.revokeObjectURL(url);';
        echo '}';
        echo '</script>';
    }
}
