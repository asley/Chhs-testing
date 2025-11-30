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
use Gibbon\Module\Reports\ArchiveFile;
use Gibbon\Module\Reports\ReportBuilder;
use Gibbon\Module\Reports\Domain\ReportGateway;
use Gibbon\Module\Reports\Domain\ReportArchiveGateway;
use Gibbon\Module\Reports\Domain\ReportArchiveEntryGateway;
use Gibbon\Module\Reports\Renderer\MpdfRenderer;
use Gibbon\Module\Reports\Renderer\TcpdfRenderer;
use Gibbon\Data\Validator;

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
    
    $reportGateway             = $container->get(ReportGateway::class);
    $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);
    $studentGateway            = $container->get(StudentGateway::class);

    $report = $reportGateway->getByID($gibbonReportID);

    // Validate the database relationships exist
    if (empty($gibbonReportID) || empty($report) || empty($identifiers)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    if ($action == 'Generate') {
        // Set reports to cache in a separate location
        $cachePath = $session->has('cachePath') ? $session->get('cachePath').'/reports' : '/uploads/cache';
        $container->get('twig')->setCache($session->get('absolutePath').$cachePath);

        $reportBuilder = $container->get(ReportBuilder::class);
        $archive       = $container->get(ReportArchiveGateway::class)->getByID($report['gibbonReportArchiveID']);
        // $archiveFile = $container->get(ArchiveFile::class); // No longer used for file naming
        
        $template = $reportBuilder->buildTemplate($report['gibbonReportTemplateID'], $status == 'Draft');
        $renderer = $container->get($template->getData('flags') == 1 ? MpdfRenderer::class : TcpdfRenderer::class);

        foreach ($identifiers as $identifier) {
            $ids = [
                'gibbonStudentEnrolmentID' => $identifier,
                'gibbonReportingCycleID'   => $report['gibbonReportingCycleID']
            ];

            $reports = $reportBuilder->buildReportSingle($template, $report, $ids);

            if ($student = $studentGateway->getByID($identifier)) {
                // Build a friendly file name using the student's actual name from the database.
                // First try the 'name' field; if not available, fallback to preferredName (or firstName) and surname.
                if (!empty($student['name'])) {
                    $studentName = $student['name'];
                } else {
                    $firstName = !empty($student['preferredName']) ? $student['preferredName'] : ($student['firstName'] ?? '');
                    $lastName  = $student['surname'] ?? ($student['lastName'] ?? '');
                    $studentName = trim($firstName . ' ' . $lastName);
                }
                if (empty($studentName)) {
                    $studentName = 'Student_' . $identifier;
                }
                // Replace spaces with underscores and remove unwanted characters
                $studentNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $studentName));
                // Use the student's actual name as the PDF file name
                $path = $studentNameSafe . '.pdf';
                $fullPath = $session->get('absolutePath').$archive['path'].'/'.$path;
                
                // Render the report to the friendly file path
                $renderer->render($template, $reports, $fullPath);

                // Insert or update the archive entry with the friendly file name
                $reportArchiveEntryGateway->insertAndUpdate([
                    'reportIdentifier'      => $report['name'],
                    'gibbonReportID'        => $gibbonReportID,
                    'gibbonReportArchiveID' => $report['gibbonReportArchiveID'],
                    'gibbonSchoolYearID'    => $student['gibbonSchoolYearID'],
                    'gibbonYearGroupID'     => $student['gibbonYearGroupID'],
                    'gibbonFormGroupID'     => $student['gibbonFormGroupID'],
                    'gibbonPersonID'        => $student['gibbonPersonID'],
                    'type'                  => 'Single',
                    'status'                => $status,
                    'filePath'              => $path,
                ], [
                    'status'            => $status,
                    'timestampModified' => date('Y-m-d H:i:s'),
                    'filePath'          => $path
                ]);

            } else {
                $partialFail = true;
            }
        }

    // --------------------------------------
    // 2. Delete Reports
    // --------------------------------------
    } else if ($action == 'Delete') {
        $archive = $container->get(ReportArchiveGateway::class)->getByID($report['gibbonReportArchiveID']);

        foreach ($identifiers as $identifier) {
            if ($student = $studentGateway->getByID($identifier)) {
                $entry = $reportArchiveEntryGateway->selectBy([
                    'gibbonReportID'        => $gibbonReportID,
                    'gibbonReportArchiveID' => $report['gibbonReportArchiveID'],
                    'gibbonSchoolYearID'    => $student['gibbonSchoolYearID'],
                    'gibbonYearGroupID'     => $student['gibbonYearGroupID'],
                    'gibbonFormGroupID'     => $student['gibbonFormGroupID'],
                    'gibbonPersonID'        => $student['gibbonPersonID'],
                    'type'                  => 'Single',
                ])->fetch();

                if (!empty($entry)) {
                    $path = $session->get('absolutePath').$archive['path'].'/'.$entry['filePath'];
                    if (!empty($archive) && file_exists($path)) {
                        unlink($path);
                    }
                    
                    $deleted = $reportArchiveEntryGateway->delete($entry['gibbonReportArchiveEntryID']);
                    $partialFail &= !$deleted;
                }
            } else {
                $partialFail = true;
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

        // Get ArchiveFile instance for building file paths if needed
        $archiveFile = $container->get(ArchiveFile::class);

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
            if ($student = $studentGateway->getByID($identifier)) {
                // Build the student's friendly file name as in the Generate branch
                if (!empty($student['name'])) {
                    $studentName = $student['name'];
                } else {
                    $firstName = !empty($student['preferredName']) ? $student['preferredName'] : ($student['firstName'] ?? '');
                    $lastName  = $student['surname'] ?? ($student['lastName'] ?? '');
                    $studentName = trim($firstName . ' ' . $lastName);
                }
                if (empty($studentName)) {
                    $studentName = 'Student_' . $identifier;
                }
                $studentNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $studentName));

                // Build the full file path using ArchiveFile's method for a single file
                $path = $archiveFile->getSingleFilePath($gibbonReportID, $student['gibbonYearGroupID'], $identifier);
                // Override the file name with the friendly name
                $friendlyPath = $studentNameSafe . '.pdf';
                $filePath = $session->get('absolutePath').$archive['path'].'/'.$friendlyPath;
                
                // If the file doesn't already exist with the friendly name, try to rename it if possible.
                // Alternatively, if the file exists with the original name, you could copy it.
                if (!file_exists($filePath)) {
                    $originalPath = $session->get('absolutePath').$archive['path'].'/'.$path;
                    if (file_exists($originalPath)) {
                        // Rename or copy the file to the friendly name
                        copy($originalPath, $filePath);
                    } else {
                        $partialFail = true;
                        continue;
                    }
                }
                
                // Add the file to the ZIP using the friendly name
                $zip->addFile($filePath, $studentNameSafe . '.pdf');
            } else {
                $partialFail = true;
            }
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