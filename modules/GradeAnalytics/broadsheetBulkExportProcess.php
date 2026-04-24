<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;
use Gibbon\Data\Validator;

include '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/GradeAnalytics/broadsheetBulkExport.php';

if (isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/broadsheetBulkExport.php') == false) {
    header('Location: ' . $URL . '&return=error0');
    exit;
}

$formGroupIDs    = $_POST['formGroupIDs']    ?? [];
$assessmentNames = $_POST['assessmentNames'] ?? [];
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

if (empty($formGroupIDs) || empty($assessmentNames)) {
    header('Location: ' . $URL . '&return=error2');
    exit;
}

if (!class_exists('ZipArchive')) {
    die('ZipArchive extension is required. Please enable zip in PHP.');
}

$gateway = $container->get(GradeAnalyticsGateway::class);

// Fetch form group names for file naming
$allFormGroups = $gateway->selectFormGroups($gibbonSchoolYearID)->fetchAll(\PDO::FETCH_KEY_PAIR);

// Build temp ZIP
$tmpFile = tempnam(sys_get_temp_dir(), 'broadsheet_') . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
    die('Could not create ZIP file.');
}

foreach ($formGroupIDs as $formGroupID) {
    $formGroupName = $allFormGroups[$formGroupID] ?? 'FormGroup_' . $formGroupID;
    $safeFG = preg_replace('/[^A-Za-z0-9_\-]/', '_', $formGroupName);

    foreach ($assessmentNames as $assessmentName) {
        $safeAS = preg_replace('/[^A-Za-z0-9_\-]/', '_', $assessmentName);
        $filename = "{$safeFG}-{$safeAS}.csv";

        $filters = [
            'formGroupID'    => $formGroupID,
            'assessmentName' => $assessmentName,
        ];

        $broadsheetData = $gateway->selectBroadsheetData($gibbonSchoolYearID, $filters);

        if (empty($broadsheetData)) {
            // Still add an empty file so the user knows it was attempted
            $zip->addFromString($filename, "No data found for {$formGroupName} - {$assessmentName}\n");
            continue;
        }

        // Collect courses
        $courses = [];
        foreach ($broadsheetData as $student) {
            foreach (array_keys($student['courses']) as $course) {
                if (!in_array($course, $courses)) {
                    $courses[] = $course;
                }
            }
        }
        sort($courses);

        // Build CSV
        $rows   = [];
        $header = ['Rank', 'Student Name', 'Form Group', 'Year Group'];
        foreach ($courses as $course) {
            $header[] = $course;
        }
        $header[] = 'Average';
        $rows[]   = $header;

        $rank = 1;
        foreach ($broadsheetData as $student) {
            $row = [
                $rank,
                trim($student['preferredName'] . ' ' . $student['surname']),
                $student['formGroup'],
                $student['yearGroup'],
            ];
            foreach ($courses as $course) {
                $grade = $student['courses'][$course] ?? '';
                $row[] = is_numeric($grade) ? number_format($grade, 1) . '%' : $grade;
            }
            $row[] = number_format($student['average'], 2) . '%';
            $rows[] = $row;
            $rank++;
        }

        $csv = '';
        foreach ($rows as $r) {
            $csv .= implode(',', array_map(function($cell) {
                return '"' . str_replace('"', '""', $cell) . '"';
            }, $r)) . "\r\n";
        }

        $zip->addFromString($filename, $csv);
    }
}

$zip->close();

// Stream ZIP to browser
$downloadName = 'broadsheet-bulk-' . date('Y-m-d') . '.zip';
if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Pragma: no-cache');
header('Expires: 0');
readfile($tmpFile);
unlink($tmpFile);
exit;
