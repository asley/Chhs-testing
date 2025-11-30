<?php
// Include Gibbon core and module functions
include '../../gibbon.php';
include './moduleFunctions.php';

// Check if the user has access to view the logs
if (!isActionAccessible($guid, $connection2, '/modules/Bulk Report Download/bulk_download_logs_view.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Fetch logs from the database
$query = "SELECT logID, userID, downloadDate, criteria FROM bulk_download_logs ORDER BY downloadDate DESC";
$stmt = $pdo->query($query);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display logs
$page->addBreadcrumb(__('Bulk Report Download Logs'));
$page->setTitle(__('Bulk Report Download Logs'));

echo '<table class="table table-striped table-hover">';
echo '<thead>';
echo '<tr>';
echo '<th>' . __('Log ID') . '</th>';
echo '<th>' . __('User ID') . '</th>';
echo '<th>' . __('Download Date') . '</th>';
echo '<th>' . __('Criteria') . '</th>';
echo '<th>' . __('Actions') . '</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($logs as $log) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($log['logID']) . '</td>';
    echo '<td>' . htmlspecialchars($log['userID']) . '</td>';
    echo '<td>' . htmlspecialchars($log['downloadDate']) . '</td>';
    echo '<td>' . htmlspecialchars($log['criteria']) . '</td>';
    echo '<td>';
    echo '<form method="post" action="bulk_download_delete.php" style="display:inline;">';
    echo '<input type="hidden" name="logID" value="' . htmlspecialchars($log['logID']) . '">';
    echo '<button type="submit" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to delete this log?\');">' . __('Delete') . '</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

?>
