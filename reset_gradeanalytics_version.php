<?php
// Reset GradeAnalytics version to trigger update system
require_once __DIR__ . '/gibbon.php';

$pdo = $container->get('db');

try {
    // Reset module version to 1.0.0 in database
    $sql = "UPDATE gibbonModule SET version = '1.0.0' WHERE name = 'GradeAnalytics'";
    $pdo->statement($sql);

    echo "<h2>✅ Module Version Reset Complete</h2>";
    echo "<p>GradeAnalytics module version has been reset to <strong>1.0.0</strong> in the database.</p>";
    echo "<p>The manifest.php file is at version <strong>1.0.1</strong></p>";

    echo "<hr>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Go to <strong>Admin → Extend & Update → Update</strong></li>";
    echo "<li>You should now see <strong>GradeAnalytics</strong> with an update from <strong>1.0.0 → 1.0.1</strong></li>";
    echo "<li>Click the <strong>Update</strong> button</li>";
    echo "<li>This will run the CHANGEDB.php script which adds the 'View Grade Analytics' permissions</li>";
    echo "</ol>";

    echo "<p><a href='index.php?q=/modules/System Admin/update.php' style='display:inline-block;background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Update Page →</a></p>";

} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
