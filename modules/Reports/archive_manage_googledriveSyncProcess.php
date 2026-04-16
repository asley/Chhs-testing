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

// Allow static call to buildFilename without a full namespace prefix below


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

$lockPath = sys_get_temp_dir() . '/gibbon-google-drive-sync.lock';
$lockHandle = @fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    if (is_resource($lockHandle)) {
        fclose($lockHandle);
    }
    $URL .= '&return=error4';
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
$remoteChecked = 0;

if ($forceResync) {
    // Safety-first resync: only re-queue entries whose stored Drive file is missing.
    // This avoids wiping IDs for the full archive and accidentally re-uploading everything.
    $requeueSql = "SELECT gibbonReportArchiveEntryID, googleDriveFileID
                   FROM gibbonReportArchiveEntry
                   WHERE status = 'Final'
                   AND type = 'Single'
                   AND googleDriveFileID IS NOT NULL
                   AND googleDriveFileID <> ''
                   AND googleDriveFileID NOT LIKE 'missing_local:%'
                   ORDER BY timestampModified DESC
                   LIMIT {$scanLimit}";
    $requeueResult = $pdo->select($requeueSql);
    $requeueCandidates = $requeueResult ? $requeueResult->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($requeueCandidates as $candidate) {
        $entryID = (int)($candidate['gibbonReportArchiveEntryID'] ?? 0);
        $driveFileID = trim((string)($candidate['googleDriveFileID'] ?? ''));
        if (empty($entryID) || empty($driveFileID)) {
            continue;
        }

        // Guardrail: malformed IDs are treated as unsynced and re-queued immediately.
        if (!preg_match('/^[A-Za-z0-9_-]{20,}$/', $driveFileID)) {
            $reportArchiveEntryGateway->update($entryID, ['googleDriveFileID' => null]);
            $requeued++;
            continue;
        }

        $remoteChecked++;
        if (!$driveService->fileExists($driveFileID)) {
            $reportArchiveEntryGateway->update($entryID, ['googleDriveFileID' => null]);
            $requeued++;
        }
    }
}

// Fetch a scan window of unsynced FINAL Single-type entries with the metadata needed
// to build a human-readable filename and the folder hierarchy in Drive.
// Batch (year-group combined) PDFs are intentionally excluded (type = 'Single' only).
$sql = "SELECT e.gibbonReportArchiveEntryID, e.filePath, e.reportIdentifier,
               a.path AS archivePath,
               p.surname, p.preferredName,
               yg.name  AS yearGroupName,
               fg.nameShort AS formGroupName,
               sy.name  AS schoolYearName
        FROM gibbonReportArchiveEntry e
        JOIN gibbonReportArchive a  ON (a.gibbonReportArchiveID  = e.gibbonReportArchiveID)
        JOIN gibbonPerson p         ON (p.gibbonPersonID         = e.gibbonPersonID)
        LEFT JOIN gibbonYearGroup  yg ON (yg.gibbonYearGroupID  = e.gibbonYearGroupID)
        LEFT JOIN gibbonFormGroup  fg ON (fg.gibbonFormGroupID  = e.gibbonFormGroupID)
        LEFT JOIN gibbonSchoolYear sy ON (sy.gibbonSchoolYearID = e.gibbonSchoolYearID)
        WHERE e.status = 'Final'
        AND e.type = 'Single'
        AND (e.googleDriveFileID IS NULL OR e.googleDriveFileID = '')
        ORDER BY e.timestampCreated ASC
        LIMIT {$scanLimit}";

$result = $pdo->select($sql);
$entries = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];

$synced         = 0;
$partialFail    = false;
$missingSkipped = 0;
$scanned        = 0;
$uploadAttempts = 0;

// Increase execution time for large archives
set_time_limit(600);

$errors    = [];
$debugLog  = [];  // Per-entry trace — stored in session when batchLimit <= 10

foreach ($entries as $entry) {
    $scanned++;
    $entryID   = $entry['gibbonReportArchiveEntryID'];
    $localPath = $absolutePath . rtrim($entry['archivePath'], '/') . '/' . ltrim($entry['filePath'], '/');

    $trace = [
        'id'        => $entryID,
        'student'   => ($entry['preferredName'] ?? '') . ' ' . ($entry['surname'] ?? ''),
        'localPath' => $localPath,
        'fileExists'=> is_file($localPath),
        'schoolYear'=> $entry['schoolYearName'] ?? '(none)',
        'yearGroup' => $entry['yearGroupName']  ?? '(none)',
        'formGroup' => $entry['formGroupName']  ?? '(none)',
        'filename'  => '',
        'folderId'  => '',
        'driveId'   => '',
        'error'     => '',
        'status'    => '',
    ];

    if (!$trace['fileExists']) {
        $reportArchiveEntryGateway->update($entryID, [
            'googleDriveFileID' => $missingMarkerPrefix . $entryID,
        ]);
        $trace['status'] = 'SKIPPED — local file missing';
        $debugLog[] = $trace;
        $missingSkipped++;
        continue;
    }

    // Build human-readable filename
    $driveFilename = GoogleDriveService::buildFilename(
        $entry['surname']          ?? '',
        $entry['preferredName']    ?? '',
        $entry['reportIdentifier'] ?? basename($entry['filePath'], '.pdf')
    );
    $trace['filename'] = $driveFilename;

    // Resolve Drive folder hierarchy
    $parentFolderId = null;
    if (!empty($entry['schoolYearName']) && !empty($entry['yearGroupName']) && !empty($entry['formGroupName'])) {
        $parentFolderId = $driveService->resolveFolderPath(
            $entry['schoolYearName'],
            $entry['yearGroupName'],
            $entry['formGroupName']
        );
        $trace['folderId'] = $parentFolderId ?? '(folder resolution failed — ' . $driveService->getLastError() . ')';
    } else {
        $trace['folderId'] = '(missing year/form metadata — falling back to root folder)';
    }

    $uploadAttempts++;
    $driveFileId = $driveService->uploadFile($localPath, $driveFilename, 'application/pdf', $parentFolderId, [
        'externalKey' => (string)$entryID,
    ]);

    if ($driveFileId) {
        $reportArchiveEntryGateway->update($entryID, [
            'googleDriveFileID' => $driveFileId,
        ]);
        $trace['driveId'] = $driveFileId;
        $trace['status']  = 'OK';
        $synced++;
    } else {
        $uploadError = trim((string)$driveService->getLastError());
        $uploadError = preg_replace('/\s+/', ' ', $uploadError);

        $trace['error']  = $uploadError ?: 'unknown error';
        $trace['status'] = 'FAILED';

        $errors[] = '[' . $entryID . '] ' . $driveFilename . ': ' . ($uploadError ?: 'upload returned null');
        $partialFail = true;
    }

    $debugLog[] = $trace;

    if ($uploadAttempts >= $batchLimit) {
        break;
    }
}

// Always log all errors to PHP error log
if (!empty($errors)) {
    error_log('GoogleDriveSync errors (' . count($errors) . '): ' . implode(' | ', $errors));
}

// Store full debug log in session when running a small test batch (≤ 50)
if ($batchLimit <= 50) {
    $session->set('googleDriveSyncDebug', $debugLog);
} else {
    $session->forget('googleDriveSyncDebug');
}

// Store first error for the banner display (any batch size)
if (!empty($errors)) {
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
$URL .= '&checked=' . $remoteChecked;

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
header("Location: {$URL}");
