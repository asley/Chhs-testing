<?php 

require_once dirname(__DIR__, 2) . '/gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

// Validate inputs
$formGroupID = $_POST['formGroup'] ?? null;
$yearGroupID = $_POST['yearGroup'] ?? null;

if (!$formGroupID || !$yearGroupID) {
    exit('Error: Missing form group or year group.');
}
// Validate the directory
$reportsDirectory = __DIR__ . '/reports';
if (!is_dir($reportsDirectory)) {
    mkdir($reportsDirectory, 0755, true);
}
// Generate a ZIP file for the selected form group and year group
$zipFileName = "reports_formGroup_{$formGroupID}_yearGroup_{$yearGroupID}.zip";
$zipFilePath = $reportsDirectory . '/' . $zipFileName;

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    error_log("Error: Failed to create ZIP file at $zipFilePath");
    exit('Error: Unable to create ZIP file.');
} else {
    error_log("Debug: ZIP file created successfully at $zipFilePath");
}

// Add matching reports to the ZIP file
$reportFiles = glob("{$reportsDirectory}/report_formGroup_{$formGroupID}_yearGroup_{$yearGroupID}_*.pdf");
if (empty($reportFiles)) {
    error_log("Error: No matching reports found for Form Group: $formGroupID, Year Group: $yearGroupID.");
    $zip->close(); // Ensure ZIP is closed if no files are added
    exit('Error: No matching reports found.');
}

foreach ($reportFiles as $reportFile) {
    if ($zip->addFile($reportFile, basename($reportFile))) {
        error_log("Debug: Added file to ZIP: $reportFile");
    } else {
        error_log("Error: Failed to add file to ZIP: $reportFile");
    }
}

$zip->close();


// Verify the ZIP file exists
if (!file_exists($zipFilePath)) {
    error_log("Error: ZIP file does not exist at $zipFilePath");
    exit('Error: ZIP file not found.');
}

if (!file_exists($zipFilePath) || filesize($zipFilePath) === 0) {
    error_log("Error: Invalid ZIP file at $zipFilePath");
    exit('Error: ZIP file is invalid.');
}


// Set headers for file download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipFilePath) . '"');
header('Content-Length: ' . filesize($zipFilePath));
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// Output the ZIP file



readfile($zipFilePath);

if (readfile($zipFilePath) === false) {
    error_log("Error: Failed to send ZIP file to browser.");
    exit('Error: Failed to download ZIP file.');

}
exit;

