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


<?php

// Include module-specific functions
require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Bulk Report Download/bulk_download_edit.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Get the log ID from the POST request
$logID = $_POST['logID'] ?? null;

// Validate the log ID
if (empty($logID)) {
    $page->addError(__('No log entry selected for editing.'));
    return;
}

try {
    // Fetch the log entry from the database
    $query = "SELECT logID, userID, downloadDate, criteria FROM bulk_download_logs WHERE logID = :logID";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['logID' => $logID]);
    $logEntry = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validate that the log entry exists
    if (!$logEntry) {
        $page->addError(__('The selected log entry does not exist.'));
        return;
    }
} catch (Exception $e) {
    $page->addError(__('An error occurred while fetching the log entry.'));
    error_log("Error fetching log entry: " . $e->getMessage());
    return;
}

// Build the form
use Gibbon\Forms\Form;
use Gibbon\Forms\Layout\GridFormLayout;

$absoluteURL = $gibbon->session->get('absoluteURL');
$moduleName = $gibbon->session->get('module');
$actionURL = "{$absoluteURL}/index.php?q=/modules/{$moduleName}/bulk_download_editProcess.php";

$form = Form::create('editLog', $actionURL, 'post');
$form->setTitle(__('Edit Log Entry'));
$form->setLayout(new GridFormLayout());

// Include CSRF token for security
$form->addHiddenValue('address', $gibbon->session->get('address'));

// Hidden field for log ID
$form->addHiddenValue('logID', $logEntry['logID']);

// Editable fields
$form->addRow()
    ->addLabel('userID', __('User ID'))
    ->addTextField('userID')
    ->setValue($logEntry['userID'])
    ->required();

$form->addRow()
    ->addLabel('downloadDate', __('Download Date'))
    ->addDateTimeField('downloadDate')
    ->setValue($logEntry['downloadDate'])
    ->required();

$form->addRow()
    ->addLabel('criteria', __('Criteria'))
    ->addTextArea('criteria')
    ->setValue($logEntry['criteria'])
    ->required();

// Submit button
$form->addRow()
    ->addSubmitButton('save', __('Save Changes'));

echo $form->getOutput();

?>
