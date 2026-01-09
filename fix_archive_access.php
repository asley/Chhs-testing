<?php
require_once './gibbon.php';
$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
</style>";

echo "<h1>Fix Archive Access</h1>";

// Check current archive settings
$sql = "SELECT * FROM gibbonReportArchive WHERE gibbonReportArchiveID = '00001'";
$result = $connection2->query($sql);
$archive = $result->fetch(PDO::FETCH_ASSOC);

if ($archive) {
    echo "<div class='info'>";
    echo "<h3>Current Archive Settings:</h3>";
    echo "<p><strong>Name:</strong> " . htmlspecialchars($archive['name']) . "</p>";
    echo "<p><strong>Path:</strong> " . htmlspecialchars($archive['path']) . "</p>";
    echo "<p><strong>Readonly:</strong> " . htmlspecialchars($archive['readonly']) . "</p>";
    echo "<p><strong>School Year:</strong> " . htmlspecialchars($archive['gibbonSchoolYearID'] ?: 'NULL') . "</p>";
    echo "</div>";

    // Get current school year
    $sqlYear = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear WHERE status = 'Current' LIMIT 1";
    $resultYear = $connection2->query($sqlYear);
    $currentYear = $resultYear->fetch(PDO::FETCH_ASSOC);

    if ($currentYear) {
        echo "<div class='info'>";
        echo "<h3>Current School Year:</h3>";
        echo "<p>" . htmlspecialchars($currentYear['name']) . " (ID: " . htmlspecialchars($currentYear['gibbonSchoolYearID']) . ")</p>";
        echo "</div>";

        // Fix the archive
        $updateSQL = "UPDATE gibbonReportArchive
                      SET readonly = 'N',
                          gibbonSchoolYearID = :schoolYearID
                      WHERE gibbonReportArchiveID = '00001'";

        $stmt = $connection2->prepare($updateSQL);
        $stmt->execute(['schoolYearID' => $currentYear['gibbonSchoolYearID']]);

        echo "<div class='success'>";
        echo "<h3>✅ Archive Fixed!</h3>";
        echo "<p>Set Default Archive to:</p>";
        echo "<ul>";
        echo "<li>Readonly: N (editable)</li>";
        echo "<li>School Year: " . htmlspecialchars($currentYear['name']) . "</li>";
        echo "</ul>";
        echo "<p><strong>Now try editing your T1-Exam-2025 report - the archive should appear in the dropdown!</strong></p>";
        echo "</div>";
    }
} else {
    echo "<div class='warning'>";
    echo "<p>Archive not found.</p>";
    echo "</div>";
}
?>
