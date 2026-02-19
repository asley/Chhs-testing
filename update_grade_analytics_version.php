<?php
// One-time script to update GradeAnalytics module version
require_once __DIR__ . '/gibbon.php';

$pdo = $container->get('db');

try {
    // Update the module version in the database
    $updated = $pdo->update(
        "UPDATE gibbonModule SET version = '1.0.1' WHERE name = 'GradeAnalytics'"
    );

    echo "<h2>Module Version Updated</h2>";
    echo "<p>GradeAnalytics module version has been updated to 1.0.1 in the database.</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Go to <strong>Admin → Extend & Update → Update</strong></li>";
    echo "<li>You should now see GradeAnalytics with an update available</li>";
    echo "<li>Click the update button to run the database changes</li>";
    echo "</ol>";
    echo "<p><a href='index.php?q=/modules/System Admin/update.php'>Go to Update page</a></p>";

} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
