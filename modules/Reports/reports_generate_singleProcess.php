<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\Reports\ReportBuilder;
use Gibbon\Module\Reports\Domain\ReportGateway;
use Gibbon\Module\Reports\Domain\ReportArchiveGateway;
use Gibbon\Module\Reports\Domain\ReportArchiveEntryGateway;
use Gibbon\Module\Reports\Renderer\MpdfRenderer;
use Gibbon\Module\Reports\Renderer\TcpdfRenderer;
use Gibbon\Data\Validator;
use Gibbon\Services\GoogleDriveService;

require_once '../../gibbon.php';

// Sanitize incoming data
$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonReportID = $_POST['gibbonReportID'] ?? '';
$contextData    = $_POST['contextData'] ?? '';
$identifiers    = $_POST['identifier'] ?? [];  // multiple student enrolments
$status         = $_POST['status'] ?? 'Draft';
$action         = $_POST['action'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Reports/reports_generate_single.php'
    .'&gibbonReportID='.$gibbonReportID.'&contextData='.$contextData;

// Check permissions
if (isActionAccessible($guid, $connection2, '/modules/Reports/reports_generate_batch.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    $partialFail = false;

    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('memory_limit', '512M');  // Increase memory for mPDF report generation
    set_time_limit(300); // Allow up to 5 minutes for report generation

    $reportGateway             = $container->get(ReportGateway::class);
    $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);
    $studentGateway            = $container->get(StudentGateway::class);
    $userGateway               = $container->get(UserGateway::class);
    $driveService              = $container->get(GoogleDriveService::class);

    $report = $reportGateway->getByID($gibbonReportID);

    // Validate the database relationships exist
    if (empty($gibbonReportID) || empty($report) || empty($identifiers)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    if ($action == 'Generate') {
        $clearDriveIdOnGenerate = $driveService->isEnabled() && $status === 'Final';

        // Set reports to cache in a separate location
        $cachePath = $session->has('cachePath') ? $session->get('cachePath').'/reports' : '/uploads/cache';
        $container->get('twig')->setCache($session->get('absolutePath').$cachePath);

        $reportBuilder = $container->get(ReportBuilder::class);
        $archive       = $container->get(ReportArchiveGateway::class)->getByID($report['gibbonReportArchiveID']);
        $template = $reportBuilder->buildTemplate($report['gibbonReportTemplateID'], $status == 'Draft');
        $renderer = $container->get($template->getData('flags') == 1 ? MpdfRenderer::class : TcpdfRenderer::class);

        foreach ($identifiers as $identifier) {
            $ids = [
                'gibbonStudentEnrolmentID' => $identifier,
                'gibbonReportingCycleID'   => $report['gibbonReportingCycleID']
            ];

            $reports = $reportBuilder->buildReportSingle($template, $report, $ids);

            // Get student enrolment data (contains IDs but not name)
            $studentEnrolment = $studentGateway->getByID($identifier);
            if (empty($studentEnrolment)) {
                $partialFail = true;
                continue;
            }

            // Get the person record to retrieve the actual student name
            $person = $userGateway->getByID($studentEnrolment['gibbonPersonID']);

            // Build a friendly file name using the student's actual name from gibbonPerson table
            $studentName = '';
            if (!empty($person)) {
                $firstName = !empty($person['preferredName']) ? $person['preferredName'] : ($person['firstName'] ?? '');
                $lastName  = $person['surname'] ?? '';
                $studentName = trim($lastName . '_' . $firstName);
            }
            if (empty($studentName)) {
                $studentName = 'Student_' . $identifier;
            }
            // Replace spaces and remove unwanted characters
            $studentNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $studentName));
            // Create a safe report name for the filename
            $reportNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $report['name']));
            // Use the student's actual name and report name as the PDF file name
            $path = $studentNameSafe . '_' . $reportNameSafe . '.pdf';
            $fullPath = $session->get('absolutePath').$archive['path'].'/'.$path;

            // Render the report to the friendly file path
            $renderer->render($template, $reports, $fullPath);

            // Insert or update the archive entry with the friendly file name.
            // For regenerated Final reports, clear Drive ID so only this entry is re-queued for sync.
            $insertData = [
                'reportIdentifier'      => $report['name'],
                'gibbonReportID'        => $gibbonReportID,
                'gibbonReportArchiveID' => $report['gibbonReportArchiveID'],
                'gibbonSchoolYearID'    => $studentEnrolment['gibbonSchoolYearID'],
                'gibbonYearGroupID'     => $studentEnrolment['gibbonYearGroupID'],
                'gibbonFormGroupID'     => $studentEnrolment['gibbonFormGroupID'],
                'gibbonPersonID'        => $studentEnrolment['gibbonPersonID'],
                'type'                  => 'Single',
                'status'                => $status,
                'filePath'              => $path,
            ];
            $updateData = [
                'status'            => $status,
                'timestampModified' => date('Y-m-d H:i:s'),
                'filePath'          => $path,
            ];
            if ($clearDriveIdOnGenerate) {
                $insertData['googleDriveFileID'] = null;
                $updateData['googleDriveFileID'] = null;
            }

            $reportArchiveEntryGateway->insertAndUpdate($insertData, $updateData);
        }

    // --------------------------------------
    // 2. Delete Reports
    // --------------------------------------
    } else if ($action == 'Delete') {
        $archive = $container->get(ReportArchiveGateway::class)->getByID($report['gibbonReportArchiveID']);
        $deleteDriveFileOnArchiveDelete = $driveService->isEnabled();

        foreach ($identifiers as $identifier) {
            $studentEnrolment = $studentGateway->getByID($identifier);
            if (empty($studentEnrolment)) {
                $partialFail = true;
                continue;
            }

            $entry = $reportArchiveEntryGateway->selectBy([
                'gibbonReportID'        => $gibbonReportID,
                'gibbonReportArchiveID' => $report['gibbonReportArchiveID'],
                'gibbonSchoolYearID'    => $studentEnrolment['gibbonSchoolYearID'],
                'gibbonYearGroupID'     => $studentEnrolment['gibbonYearGroupID'],
                'gibbonFormGroupID'     => $studentEnrolment['gibbonFormGroupID'],
                'gibbonPersonID'        => $studentEnrolment['gibbonPersonID'],
                'type'                  => 'Single',
            ])->fetch();

            if (!empty($entry)) {
                $path = $session->get('absolutePath').$archive['path'].'/'.$entry['filePath'];
                if (!empty($archive) && file_exists($path)) {
                    unlink($path);
                }

                $driveFileId = trim((string)($entry['googleDriveFileID'] ?? ''));
                if ($deleteDriveFileOnArchiveDelete && !empty($driveFileId) && strpos($driveFileId, 'missing_local:') !== 0) {
                    $driveService->deleteFile($driveFileId);
                }

                $deleted = $reportArchiveEntryGateway->delete($entry['gibbonReportArchiveEntryID']);
                $partialFail &= !$deleted;
            }
        }

    // --------------------------------------
    // 3. Bulk Download (NEW)
    // --------------------------------------
    } else if ($action == 'BulkDownload') {
        $reportArchiveGateway = $container->get(ReportArchiveGateway::class);
        $archive = $reportArchiveGateway->getByID($report['gibbonReportArchiveID']);

        if (empty($archive)) {
            // No archive set up for this report
            $partialFail = true;
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Create a temporary ZIP file
        $zipFile = tempnam(sys_get_temp_dir(), 'reports_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $partialFail = true;
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Bulk download: Loop over each selected student and add their PDF(s) to the ZIP
        foreach ($identifiers as $identifier) {
            // Get student enrolment data (contains IDs but not name)
            $studentEnrolment = $studentGateway->getByID($identifier);
            if (empty($studentEnrolment)) {
                $partialFail = true;
                continue;
            }

            // Get the person record to retrieve the actual student name
            $person = $userGateway->getByID($studentEnrolment['gibbonPersonID']);

            // Query the archive entry from the database using the specific gibbonReportID
            // This ensures we get the correct file for the selected reporting cycle
            $entry = $reportArchiveEntryGateway->selectBy([
                'gibbonReportID'        => $gibbonReportID,
                'gibbonReportArchiveID' => $report['gibbonReportArchiveID'],
                'gibbonSchoolYearID'    => $studentEnrolment['gibbonSchoolYearID'],
                'gibbonYearGroupID'     => $studentEnrolment['gibbonYearGroupID'],
                'gibbonFormGroupID'     => $studentEnrolment['gibbonFormGroupID'],
                'gibbonPersonID'        => $studentEnrolment['gibbonPersonID'],
                'type'                  => 'Single',
            ])->fetch();

            if (empty($entry) || empty($entry['filePath'])) {
                $partialFail = true;
                continue;
            }

            // Use the file path stored in the database (already contains correct report/cycle)
            $storedFilePath = $entry['filePath'];
            $fullFilePath = $session->get('absolutePath').$archive['path'].'/'.$storedFilePath;

            if (!file_exists($fullFilePath)) {
                $partialFail = true;
                continue;
            }

            // Build a friendly file name using student name from gibbonPerson table
            $studentName = '';
            if (!empty($person)) {
                $firstName = !empty($person['preferredName']) ? $person['preferredName'] : ($person['firstName'] ?? '');
                $lastName  = $person['surname'] ?? '';
                $studentName = trim($lastName . ', ' . $firstName);
            }
            if (empty($studentName)) {
                $studentName = 'Student_' . $identifier;
            }
            $studentNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace([' ', ','], ['_', ''], $studentName));

            // Include report name in the zip file entry name for clarity
            $reportNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $report['name']));
            $zipEntryName = $studentNameSafe . '_' . $reportNameSafe . '.pdf';

            // Add the file to the ZIP using the friendly name
            $zip->addFile($fullFilePath, $zipEntryName);
        }

        $zip->close();
        
        // Check if ZIP is valid
        if (!file_exists($zipFile) || filesize($zipFile) == 0) {
            $partialFail = true;
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }
        
        // Output the ZIP file for download
        $zipDownloadName = 'BulkDownloadReports_' . date('Y-m-d_H-i-s') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.$zipDownloadName.'"');
        header('Content-Length: ' . filesize($zipFile));
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        readfile($zipFile);
        unlink($zipFile); // Remove temp file
        exit;
        
    // --------------------------------------
    // 4. Unknown Action
    // --------------------------------------
    } else {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
    
    $URL .= $partialFail
        ? '&return=error3'
        : '&return=success0';
    
    header("Location: {$URL}");
    exit;
}
