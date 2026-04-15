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

use Gibbon\Forms\Form;
use Gibbon\Services\GoogleDriveService;

if (isActionAccessible($guid, $connection2, '/modules/Reports/archive_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Manage Archives'), 'archive_manage.php')
        ->add(__('Google Drive Sync'));

    $page->return->addReturns([
        'success1' => __('Sync complete. {count} report(s) uploaded to Google Drive.', ['count' => '<b>'.($_GET['synced'] ?? '0').'</b>']),
        'warning1' => __('Sync complete with some errors. {count} report(s) uploaded. Some files could not be synced.', ['count' => '<b>'.($_GET['synced'] ?? '0').'</b>']),
        'warning2' => __('Sync complete. {count} report(s) uploaded and <b>{missing}</b> missing local file(s) were skipped.', [
            'count' => '<b>'.($_GET['synced'] ?? '0').'</b>',
            'missing' => (int)($_GET['missing'] ?? 0),
        ]),
        'error3'   => __('Google Drive sync is not enabled or not configured. Please check Google Drive Settings.'),
    ]);

    $driveService = $container->get(GoogleDriveService::class);

    if (!$driveService->isEnabled()) {
        $page->addAlert(
            __('Google Drive sync is not enabled. Configure your service account credentials first.')
            .' <a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Reports/googledrive_settings.php">'.__('Google Drive Settings').'</a>',
            'warning'
        );

        return;
    }

    // Count unsynced FINAL reports only (drafts are intentionally excluded from sync).
    $unsyncedCount = $pdo->selectOne(
        "SELECT COUNT(*) FROM gibbonReportArchiveEntry WHERE status = 'Final' AND (googleDriveFileID IS NULL OR googleDriveFileID = '')"
    );

    // Show last sync error if present
    if ($session->has('googleDriveSyncError')) {
        $page->addAlert(__('Last sync error: ').'<code>'.htmlspecialchars($session->get('googleDriveSyncError')).'</code>', 'error');
        $session->forget('googleDriveSyncError');
    }

    // Debug table — only populated when batchLimit ≤ 10
    if ($session->has('googleDriveSyncDebug')) {
        $debugLog = $session->get('googleDriveSyncDebug');
        $session->forget('googleDriveSyncDebug');

        $rows = '';
        foreach ($debugLog as $t) {
            $statusColor = $t['status'] === 'OK' ? 'text-green-700' : ($t['status'] === 'FAILED' ? 'text-red-700 font-bold' : 'text-yellow-700');
            $rows .= '<tr class="border-t border-gray-200 text-xs">'
                . '<td class="px-2 py-1 font-mono">' . htmlspecialchars($t['id'])         . '</td>'
                . '<td class="px-2 py-1">'           . htmlspecialchars($t['student'])     . '</td>'
                . '<td class="px-2 py-1 font-mono">' . htmlspecialchars($t['filename'])    . '</td>'
                . '<td class="px-2 py-1">'           . ($t['fileExists'] ? '✓' : '<span class="text-red-600">✗ missing</span>') . '</td>'
                . '<td class="px-2 py-1">'           . htmlspecialchars($t['schoolYear'])  . '</td>'
                . '<td class="px-2 py-1">'           . htmlspecialchars($t['yearGroup'])   . '</td>'
                . '<td class="px-2 py-1">'           . htmlspecialchars($t['formGroup'])   . '</td>'
                . '<td class="px-2 py-1 font-mono text-gray-500">' . htmlspecialchars(substr($t['folderId'], 0, 24)) . '</td>'
                . '<td class="px-2 py-1 font-mono text-gray-500">' . htmlspecialchars(substr($t['driveId'],  0, 24)) . '</td>'
                . '<td class="px-2 py-1 ' . $statusColor . '">' . htmlspecialchars($t['status']) . '</td>'
                . '<td class="px-2 py-1 text-red-600">' . htmlspecialchars($t['error']) . '</td>'
                . '</tr>';
        }

        echo '<div class="mt-6">'
            . '<h4 class="font-semibold text-sm mb-2">Debug — Per-entry results</h4>'
            . '<div class="overflow-x-auto">'
            . '<table class="w-full text-xs border border-gray-300 bg-white">'
            . '<thead class="bg-gray-100 text-left">'
            . '<tr>'
            . '<th class="px-2 py-1">Entry ID</th>'
            . '<th class="px-2 py-1">Student</th>'
            . '<th class="px-2 py-1">Drive Filename</th>'
            . '<th class="px-2 py-1">File?</th>'
            . '<th class="px-2 py-1">School Year</th>'
            . '<th class="px-2 py-1">Year Group</th>'
            . '<th class="px-2 py-1">Form Group</th>'
            . '<th class="px-2 py-1">Folder ID</th>'
            . '<th class="px-2 py-1">Drive ID</th>'
            . '<th class="px-2 py-1">Status</th>'
            . '<th class="px-2 py-1">Error</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div></div>';
    }
    $totalCount = $pdo->selectOne("SELECT COUNT(*) FROM gibbonReportArchiveEntry WHERE status = 'Final'");
    $missingSkippedCount = $pdo->selectOne("SELECT COUNT(*) FROM gibbonReportArchiveEntry WHERE status = 'Final' AND googleDriveFileID LIKE 'missing_local:%'");
    $unsyncedDraftCount = $pdo->selectOne("SELECT COUNT(*) FROM gibbonReportArchiveEntry WHERE status = 'Draft' AND (googleDriveFileID IS NULL OR googleDriveFileID = '')");

    $page->addAlert(
        __('There are <b>{unsynced}</b> unsynced final report(s) out of <b>{total}</b> total final archive entries.', [
            'unsynced' => $unsyncedCount,
            'total'    => $totalCount,
        ]),
        $unsyncedCount > 0 ? 'message' : 'success'
    );

    if ((int)$unsyncedDraftCount > 0) {
        $page->addAlert(
            __('There are <b>{count}</b> unsynced draft report(s). Drafts are excluded from Google Drive sync.', [
                'count' => $unsyncedDraftCount,
            ]),
            'message'
        );
    }

    if ((int)$missingSkippedCount > 0) {
        $page->addAlert(
            __('There are <b>{count}</b> final archive entry/entries marked as missing local files and excluded from sync retries.', [
                'count' => $missingSkippedCount,
            ]),
            'warning'
        );
    }

    $requeued = (int)($_GET['requeued'] ?? 0);
    if ($requeued > 0) {
        $page->addAlert(
            __('Re-queued <b>{count}</b> previously synced final report(s) for upload.', ['count' => $requeued]),
            'message'
        );
    }

    if ($unsyncedCount == 0) {
        $page->addAlert(__('All final reports are already synced to Google Drive.'), 'success');
    }

    $batchLimit = max(1, min(500, (int)($_GET['batch'] ?? 300)));

    if (isset($_GET['processed']) || isset($_GET['batch'])) {
        $processed = (int)($_GET['processed'] ?? 0);
        $attempted = (int)($_GET['attempted'] ?? 0);
        $batch = (int)($_GET['batch'] ?? $batchLimit);
        $page->addAlert(
            __('Scanned <b>{processed}</b> report(s) and attempted <b>{attempted}</b> upload(s) in this batch (batch size: <b>{batch}</b>).', [
                'processed' => $processed,
                'attempted' => $attempted,
                'batch' => $batch,
            ]),
            'message'
        );
    }

    $form = Form::create('googleDriveSync', $session->get('absoluteURL').'/modules/Reports/archive_manage_googledriveSyncProcess.php');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('batchLimit', __('Batch Size'))
            ->description(__('Number of final reports to upload per run. Recommended: 300.'));
        $row->addNumber('batchLimit')->onlyInteger(true)->required()->maxLength(4)->setValue($batchLimit);

    $row = $form->addRow();
        $row->addLabel('forceResync', __('Force Re-sync Final Reports'))
            ->description(__('Set to Yes to re-queue previously synced final reports. Use this after files are manually deleted from Google Drive.'));
        $row->addYesNo('forceResync')->selected('N');

    $row = $form->addRow();
        $row->addContent(
            '<p class="text-sm text-gray-600">'
            .($unsyncedCount > 0
                ? __('Click Sync to upload unsynced <b>final</b> report PDFs to Google Drive. Missing local files are auto-skipped.')
                : __('No unsynced final reports are currently queued. You can still use this page to update settings or run sync again after restoring missing local files.'))
            .'</p>'
        );

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Sync to Google Drive'));

    echo $form->getOutput();

    // Settings shortcut
    echo '<p class="mt-4 text-sm"><a href="'.$session->get('absoluteURL').'/index.php?q=/modules/Reports/googledrive_settings.php" class="underline text-blue-700">'.__('Google Drive Settings').'</a></p>';
}
