<?php
require_once './gibbon.php';

$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #4e73df; padding-bottom: 10px; }
pre { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; overflow-x: auto; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
th { background: #4e73df; color: white; padding: 12px; text-align: left; }
td { padding: 10px; border: 1px solid #ddd; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
</style>";

echo "<h1>Database Structure Analysis</h1>";

// Check Gibbon version
$sql = "SELECT version FROM gibbonSetting WHERE scope='System' AND name='version'";
$result = $connection2->query($sql);
$versionData = $result->fetch(PDO::FETCH_ASSOC);

echo "<div class='info'><strong>Gibbon Version:</strong> " . $versionData['version'] . "</div>";

// Check gibbonInternalAssessmentColumn table structure
echo "<h2>gibbonInternalAssessmentColumn Table Structure</h2>";
$sql = "DESCRIBE gibbonInternalAssessmentColumn";
$result = $connection2->query($sql);
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
$hasReportingCycleID = false;
foreach ($columns as $col) {
    if ($col['Field'] == 'gibbonReportingCycleID') {
        $hasReportingCycleID = true;
    }
    echo "<tr>";
    echo "<td><strong>" . $col['Field'] . "</strong></td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . $col['Key'] . "</td>";
    echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
    echo "<td>" . $col['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

if (!$hasReportingCycleID) {
    echo "<div class='warning'>";
    echo "<h3>❌ MISSING COLUMN FOUND!</h3>";
    echo "<p>The <code>gibbonInternalAssessmentColumn</code> table is <strong>missing</strong> the <code>gibbonReportingCycleID</code> column.</p>";
    echo "<p>This column is required to link internal assessments to reporting cycles.</p>";
    echo "<p><strong>This needs to be added to your database.</strong></p>";
    echo "</div>";
} else {
    echo "<div class='info'>✅ Column exists - checking data...</div>";
}

// Check if there are any internal assessment columns
echo "<h2>Sample Internal Assessment Columns</h2>";
$sql = "SELECT
    iac.gibbonInternalAssessmentColumnID,
    iac.name,
    c.nameShort as course,
    cc.nameShort as class
FROM gibbonInternalAssessmentColumn iac
JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
JOIN gibbonSchoolYear sy ON c.gibbonSchoolYearID = sy.gibbonSchoolYearID
WHERE sy.status = 'Current'
LIMIT 10";

$result = $connection2->query($sql);
$assessments = $result->fetchAll(PDO::FETCH_ASSOC);

if (empty($assessments)) {
    echo "<div class='warning'>No internal assessment columns found for current year.</div>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Assessment Name</th><th>Course</th><th>Class</th></tr>";
    foreach ($assessments as $assess) {
        echo "<tr>";
        echo "<td>" . $assess['gibbonInternalAssessmentColumnID'] . "</td>";
        echo "<td>" . htmlspecialchars($assess['name']) . "</td>";
        echo "<td>" . htmlspecialchars($assess['course']) . "</td>";
        echo "<td>" . htmlspecialchars($assess['class']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check how reporting works in this version
echo "<h2>Reporting System Configuration</h2>";
$sql = "SELECT
    r.name as reportName,
    rc.name as cycleName,
    COUNT(DISTINCT rt.gibbonReportTemplateID) as templates
FROM gibbonReport r
JOIN gibbonReportingCycle rc ON r.gibbonReportingCycleID = rc.gibbonReportingCycleID
LEFT JOIN gibbonReportTemplate rt ON r.gibbonReportID = rt.gibbonReportID
WHERE r.gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current')
GROUP BY r.gibbonReportID";

$result = $connection2->query($sql);
$reports = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>Report Name</th><th>Cycle</th><th>Templates</th></tr>";
foreach ($reports as $report) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($report['reportName']) . "</td>";
    echo "<td>" . htmlspecialchars($report['cycleName']) . "</td>";
    echo "<td>" . $report['templates'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr style='margin: 30px 0;'>";
echo "<h2>💡 Next Steps</h2>";

if (!$hasReportingCycleID) {
    echo "<div class='warning'>";
    echo "<h3>Database Schema Update Required</h3>";
    echo "<p>Your database needs to be updated to add the <code>gibbonReportingCycleID</code> column to the <code>gibbonInternalAssessmentColumn</code> table.</p>";
    echo "<p><strong>I can create a migration script to add this column for you.</strong></p>";
    echo "</div>";
} else {
    echo "<div class='info'>";
    echo "<p>Database structure looks correct. The issue may be in how reports filter data.</p>";
    echo "</div>";
}
?>
