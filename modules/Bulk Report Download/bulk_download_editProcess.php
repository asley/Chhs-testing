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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/



// Include Gibbon core and module functions
include '../../gibbon.php';
include './moduleFunctions.php';

// Build the redirect URL
$URL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module') . '/bulk_download_logs_view.php';

// Check if the user has access to this action
if (!isActionAccessible($guid, $connection2, '/modules/Bulk Report Download/bulk_download_edit.php')) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get the submitted form data
$logID = $_POST['logID'] ?? null;
$userID = $_POST['userID'] ?? null;
$downloadDate = $_POST['downloadDate'] ?? null;
$criteria = $_POST['criteria'] ?? null;

// Validate the input
if (empty($logID) || empty($userID) || empty($downloadDate) || empty($criteria)) {
    $URL .= '&return=error3'; // Missing required variables
    header("Location: {$URL}");
    exit;
}

// Proceed to update the log entry
try {
    $query = "UPDATE bulk_download_logs
              SET userID = :userID, downloadDate = :downloadDate, criteria = :criteria
              WHERE logID = :logID";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'userID' => $userID,
        'downloadDate' => $downloadDate,
        'criteria' => $criteria,
        'logID' => $logID,
    ]);

    if ($stmt->rowCount() > 0) {
        // Success
        $URL .= '&return=success0';
    } else {
        // No changes were made (e.g., same values submitted)
        $URL .= '&return=error4';
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error updating log entry: " . $e->getMessage());
    $URL .= '&return=error5';
}

// Redirect back to the log management page
header("Location: {$URL}");
exit;


