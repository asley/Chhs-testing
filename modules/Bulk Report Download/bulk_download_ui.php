<?php

require_once dirname(__DIR__, 2) . '/gibbon.php';

// Debug: Verify Gibbon framework inclusion
if (!isset($gibbon)) {
    throw new Exception('Error: $gibbon is not set.');
}
error_log('Debug: Type of $gibbon: ' . get_class($gibbon));

// Validate $connection2
if (!isset($connection2)) {
    throw new Exception('Error: $connection2 is not set.');
}

if (!$connection2 instanceof PDO) {
    throw new Exception('Error: $connection2 is not a valid PDO connection.');
}
error_log('Debug: $connection2 is a valid PDO connection.');

// Fetch form groups and year groups
require_once __DIR__ . '/moduleFunctions.php';

try {
    $formGroups = getFormGroups($connection2);
    $yearGroups = getCustomYearGroups($connection2);

    echo '<h1>' . __('Bulk Report Download') . '</h1>';
    $formAction = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/Bulk Report Download/bulk_download_process.php';
    
    echo '<form action="' . htmlspecialchars($formAction) . '" method="post">';
    echo '<label for="formGroup">' . __('Form Group') . '</label>';
    echo '<select name="formGroup" id="formGroup" required>';
    echo '<option value="">' . __('--Select Form Group--') . '</option>';
    foreach ($formGroups as $group) {
        echo '<option value="' . htmlspecialchars($group['gibbonFormGroupID']) . '">' . htmlspecialchars($group['formGroupName']) . '</option>';
    }
    echo '</select><br><br>';
    
    echo '<label for="yearGroup">' . __('Year Group') . '</label>';
    echo '<select name="yearGroup" id="yearGroup" required>';
    echo '<option value="">' . __('--Select Year Group--') . '</option>';
    foreach ($yearGroups as $group) {
        echo '<option value="' . htmlspecialchars($group['gibbonYearGroupID']) . '">' . htmlspecialchars($group['yearGroupName']) . '</option>';
    }
    echo '</select><br><br>';
    
    echo '<button type="submit">' . __('Download Reports') . '</button>';
    echo '</form>';
    
} catch (Exception $e) {
    error_log("Error fetching groups: " . $e->getMessage());
}

?>
