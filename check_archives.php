<?php
require_once './gibbon.php';
$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
table { border-collapse: collapse; width: 100%; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #4e73df; color: white; }
</style>";

echo "<h1>Report Archive Diagnostics</h1>";

// Check if archives exist
$sql = "SELECT * FROM gibbonReportArchive ORDER BY name";
$result = $connection2->query($sql);
$archives = $result->fetchAll(PDO::FETCH_ASSOC);

if (empty($archives)) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ NO ARCHIVES FOUND!</h3>";
    echo "<p>No archives exist in the database. You need to create an archive first.</p>";
    echo "<p><strong>To create an archive:</strong></p>";
    echo "<ol>";
    echo "<li>Go to Reports > Archives</li>";
    echo "<li>Click 'Add' to create a new archive</li>";
    echo "<li>Give it a name (e.g., 'Reports 2024-2025')</li>";
    echo "<li>Set the path (e.g., '/uploads/reports/2024-2025')</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<h3>✅ Found " . count($archives) . " Archive(s)</h3>";
    echo "</div>";

    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Path</th><th>School Year</th><th>Readonly</th></tr>";
    foreach ($archives as $archive) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($archive['gibbonReportArchiveID']) . "</td>";
        echo "<td>" . htmlspecialchars($archive['name']) . "</td>";
        echo "<td>" . htmlspecialchars($archive['path']) . "</td>";
        echo "<td>" . htmlspecialchars($archive['gibbonSchoolYearID']) . "</td>";
        echo "<td>" . htmlspecialchars($archive['readonly']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check if reports are linked to archives
echo "<h2>Reports with Archives</h2>";
$sql2 = "SELECT gibbonReportID, name, gibbonReportArchiveID FROM gibbonReport WHERE active = 'Y' ORDER BY name";
$result2 = $connection2->query($sql2);
$reports = $result2->fetchAll(PDO::FETCH_ASSOC);

if (empty($reports)) {
    echo "<div class='warning'><p>No active reports found.</p></div>";
} else {
    echo "<table>";
    echo "<tr><th>Report ID</th><th>Report Name</th><th>Archive ID</th><th>Status</th></tr>";
    foreach ($reports as $report) {
        $status = empty($report['gibbonReportArchiveID']) ? "❌ No Archive" : "✅ Has Archive";
        $class = empty($report['gibbonReportArchiveID']) ? "style='background: #fff3cd;'" : "";
        echo "<tr $class>";
        echo "<td>" . htmlspecialchars($report['gibbonReportID']) . "</td>";
        echo "<td>" . htmlspecialchars($report['name']) . "</td>";
        echo "<td>" . htmlspecialchars($report['gibbonReportArchiveID'] ?: 'NULL') . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
