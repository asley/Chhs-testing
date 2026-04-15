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

use Gibbon\Services\GoogleDriveService;
use Gibbon\Module\Reports\Domain\ReportArchiveEntryGateway;

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Reports/archive_manage_googledrive.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

if (isActionAccessible($guid, $connection2, '/modules/Reports/archive_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$driveService = $container->get(GoogleDriveService::class);

if (!$driveService->isEnabled()) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}


$reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);
$absolutePath = $session->get('absolutePath');
$batchLimit = max(1, min(500, (int)($_POST['batchLimit'] ?? 300)));
$scanLimit = max($batchLimit * 5, $batchLimit);
$scanLimit = min($scanLimit, 5000);
$forceResync = ($_POST['forceResync'] ?? 'N') === 'Y';
$missingMarkerPrefix = 'missing_local:';
$requeued = 0;

if ($forceResync) {
    $requeueSql = "UPDATE gibbonReportArchiveEntry
                   SET googleDriveFileID = NULL
                   WHERE status = 'Final'
                   AND googleDriveFileID IS NOT NULL
                   AND googleDriveFileID <> ''
                   AND googleDriveFileID NOT LIKE 'missing_local:%'";
    $requeued = (int)$pdo->affectingStatement($requeueSql);
}

// Fetch a scan window of unsynced FINAL entries. Missing files are skipped, and we
// continue scanning so one run can still upload up to batchLimit actual files.
$sql = "SELECT e.gibbonReportArchiveEntryID, e.filePath, a.path AS archivePath
        FROM gibbonReportArchiveEntry e
        JOIN gibbonReportArchive a ON (a.gibbonReportArchiveID = e.gibbonReportArchiveID)
        WHERE e.status = 'Final'
        AND (e.googleDriveFileID IS NULL OR e.googleDriveFileID = '')
        ORDER BY e.timestampCreated ASC
        LIMIT {$scanLimit}";

$result = $pdo->select($sql);
$entries = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];

$synced      = 0;
$partialFail = false;
$missingSkipped = 0;
$scanned = 0;
$uploadAttempts = 0;

// Increase execution time for large archives
set_time_limit(600);

$errors = [];

foreach ($entries as $entry) {
    $scanned++;
    $localPath = $absolutePath . rtrim($entry['archivePath'], '/') . '/' . ltrim($entry['filePath'], '/');

    if (!is_file($localPath)) {
        // Persist a marker so missing files are not retried every batch forever.
        $reportArchiveEntryGateway->update($entry['gibbonReportArchiveEntryID'], [
            'googleDriveFileID' => $missingMarkerPrefix . $entry['gibbonReportArchiveEntryID'],
        ]);
        $missingSkipped++;
        continue;
    }

    $uploadAttempts++;
    $driveFileId = $driveService->uploadFile($localPath, basename($entry['filePath']));

    if ($driveFileId) {
        $reportArchiveEntryGateway->update($entry['gibbonReportArchiveEntryID'], [
            'googleDriveFileID' => $driveFileId,
        ]);
        $synced++;
    } else {
        $uploadError = trim((string)$driveService->getLastError());
        $uploadError = preg_replace('/\s+/', ' ', $uploadError);
        if (strlen($uploadError) > 180) {
            $uploadError = substr($uploadError, 0, 180) . '...';
        }

        $errors[] = empty($uploadError)
            ? 'Drive upload failed for: ' . $entry['filePath']
            : 'Drive upload failed for: ' . $entry['filePath'] . ' (' . $uploadError . ')';
        $partialFail = true;
    }

    if ($uploadAttempts >= $batchLimit) {
        break;
    }
}

// Log first error to PHP error log for debugging
if (!empty($errors)) {
    error_log('GoogleDriveSync errors: ' . implode(' | ', array_slice($errors, 0, 3)));
    // Store first error in session for display
    $session->set('googleDriveSyncError', $errors[0]);
} else {
    $session->forget('googleDriveSyncError');
}

$returnCode = 'success1';
if ($partialFail) {
    $returnCode = 'warning1';
} elseif ($missingSkipped > 0) {
    $returnCode = 'warning2';
}

$URL .= '&return=' . $returnCode;
$URL .= '&synced=' . $synced;
$URL .= '&missing=' . $missingSkipped;
$URL .= '&processed=' . $scanned;
$URL .= '&attempted=' . $uploadAttempts;
$URL .= '&batch=' . $batchLimit;
$URL .= '&requeued=' . $requeued;

header("Location: {$URL}");
