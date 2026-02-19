<?php
require_once './gibbon.php';
$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th { background: #4e73df; color: white; padding: 10px; text-align: left; }
td { padding: 8px; border: 1px solid #ddd; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
pre { background: #f8f9fa; border: 1px solid #ddd; padding: 10px; overflow-x: auto; }
</style>";

echo "<h1>Report Template Settings Check</h1>";

// Get Marksheet 1 report details
$sqlReport = "SELECT r.*, rc.name as cycleName, rc.cycleNumber
              FROM gibbonReport r
              JOIN gibbonReportingCycle rc ON r.gibbonReportingCycleID = rc.gibbonReportingCycleID
              WHERE rc.name = 'Marksheet 1'
              AND r.gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current')";
$report = $connection2->query($sqlReport)->fetch(PDO::FETCH_ASSOC);

echo "<h2>Marksheet 1 Report Configuration</h2>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>Report Name</td><td><strong>" . htmlspecialchars($report['name']) . "</strong></td></tr>";
echo "<tr><td>Report ID</td><td>" . $report['gibbonReportID'] . "</td></tr>";
echo "<tr><td>Cycle Name</td><td>" . htmlspecialchars($report['cycleName']) . "</td></tr>";
echo "<tr><td>Cycle Number</td><td>" . $report['cycleNumber'] . "</td></tr>";
echo "</table>";

// Get the template for this report
$sqlTemplate = "SELECT * FROM gibbonReportTemplate
                WHERE gibbonReportID = :reportID
                AND type = 'Body'
                ORDER BY sequenceNumber";
$stmtTemplate = $connection2->prepare($sqlTemplate);
$stmtTemplate->execute(['reportID' => $report['gibbonReportID']]);
$templates = $stmtTemplate->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Report Templates</h2>";
echo "<p>Found " . count($templates) . " body template(s)</p>";

foreach ($templates as $template) {
    echo "<h3>Template: " . htmlspecialchars($template['name']) . "</h3>";

    // Parse the template header to get config
    $templateContent = $template['body'];

    // Extract the config section from the template
    if (preg_match('/config:\s*\n(.*?)-->/s', $templateContent, $matches)) {
        echo "<div class='info'>";
        echo "<h4>Template Configuration:</h4>";
        echo "<pre>" . htmlspecialchars($matches[1]) . "</pre>";
        echo "</div>";

        // Check for limitByReportingCycle setting
        if (preg_match('/limitByReportingCycle:.*?default:\s*([YN])/s', $matches[1], $configMatch)) {
            $limitByReportingCycle = $configMatch[1];
            if ($limitByReportingCycle == 'Y') {
                echo "<div class='info'>";
                echo "<h4>✅ Template is configured to filter by reporting cycle</h4>";
                echo "<p><code>limitByReportingCycle: default: Y</code></p>";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "<h4>⚠️ Template is NOT filtering by reporting cycle!</h4>";
                echo "<p><code>limitByReportingCycle: default: N</code></p>";
                echo "<p>This means the template will show ALL cycles by default.</p>";
                echo "</div>";
            }
        }
    }
}

// Check for archived reports
echo "<h2>Generated Reports Archive</h2>";
$archivePath = __DIR__ . '/uploads/reports';
if (is_dir($archivePath)) {
    $files = scandir($archivePath);
    $reportFiles = array_filter($files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'pdf';
    });

    echo "<p>Found " . count($reportFiles) . " PDF files in archive:</p>";
    echo "<ul>";
    foreach (array_slice($reportFiles, 0, 20) as $file) {
        $filePath = $archivePath . '/' . $file;
        $modTime = filemtime($filePath);
        $size = filesize($filePath);
        echo "<li>";
        echo "<strong>" . htmlspecialchars($file) . "</strong> - ";
        echo "Modified: " . date('Y-m-d H:i:s', $modTime) . " - ";
        echo "Size: " . number_format($size / 1024, 1) . " KB";
        echo "</li>";
    }
    echo "</ul>";

    if (count($reportFiles) > 20) {
        echo "<p><em>... and " . (count($reportFiles) - 20) . " more files</em></p>";
    }

    echo "<div class='warning'>";
    echo "<h4>💡 Important Note About Report Archives</h4>";
    echo "<p>If you're viewing a <strong>previously generated PDF</strong> from the archive, it will show the data from when it was created, not current data.</p>";
    echo "<p>To see the current filtered data:</p>";
    echo "<ol>";
    echo "<li>Go to <strong>Reports → Manage Reporting Cycles</strong></li>";
    echo "<li>Click <strong>Generate Reports</strong> for Marksheet 1</li>";
    echo "<li>Generate a <strong>NEW</strong> report (don't open an old one)</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<p style='color: orange;'>Archive directory not found at: $archivePath</p>";
}

// Check the report prototype settings
$sqlPrototype = "SELECT * FROM gibbonReportPrototypeSection
                 WHERE gibbonReportPrototypeID IN (
                     SELECT gibbonReportPrototypeID FROM gibbonReportPrototype
                     WHERE gibbonSchoolYearID = :yearID
                 )
                 AND templateFile LIKE '%InternalAssessment%'";
$stmtPrototype = $connection2->prepare($sqlPrototype);
$stmtPrototype->execute(['yearID' => $report['gibbonSchoolYearID']]);
$prototypes = $stmtPrototype->fetchAll(PDO::FETCH_ASSOC);

if (!empty($prototypes)) {
    echo "<h2>Report Prototype Sections (using InternalAssessment)</h2>";
    echo "<table>";
    echo "<tr><th>Section Name</th><th>Template File</th><th>Config</th></tr>";
    foreach ($prototypes as $proto) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($proto['name']) . "</td>";
        echo "<td>" . htmlspecialchars($proto['templateFile']) . "</td>";
        echo "<td><pre>" . htmlspecialchars($proto['config']) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
