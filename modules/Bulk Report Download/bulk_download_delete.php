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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/


// Use the Gibbon DeleteForm
use Gibbon\Forms\Prefab\DeleteForm;

// Include module-specific functions
require_once __DIR__ . '/moduleFunctions.php';

// Check if the action is accessible
if (!isActionAccessible($guid, $connection2, "/modules/Bulk Report Download/bulk_download_delete.php")) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get the ID (e.g., logID) from the POST request
    $logID = $_POST['logID'] ?? null;

    // Validate the logID
    if (empty($logID)) {
        $page->addError(__('No log entry selected for deletion.'));
    } else {
        // Validate that the log entry exists
        $query = "SELECT COUNT(*) FROM bulk_download_logs WHERE logID = :logID";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['logID' => $logID]);

        if ($stmt->fetchColumn() == 0) {
            $page->addError(__('The selected log entry does not exist.'));
        } else {
            // Generate the delete form
            $absoluteURL = $gibbon->session->get('absoluteURL');
            $moduleName = $gibbon->session->get('module');

            $form = DeleteForm::createForm(
                "{$absoluteURL}/index.php?q=/modules/{$moduleName}/bulk_download_deleteProcess.php&logID={$logID}"
            );

            // Output the form
            echo $form->getOutput();
        }
    }
}

