<?php
require_once './gibbon.php';
$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
</style>";

echo "<h1>Check if Cycle 2 Data Exists</h1>";

// Count students with Cycle 2 grades
$sql = "SELECT COUNT(DISTINCT iae.gibbonPersonIDStudent) as studentCount
         FROM gibbonInternalAssessmentEntry iae
         JOIN gibbonInternalAssessmentColumn iac ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
         WHERE iac.gibbonReportingCycleID = (SELECT gibbonReportingCycleID FROM gibbonReportingCycle WHERE name = 'T1-Exam-2025' LIMIT 1)
         AND iac.complete = 'Y'
         AND (iae.attainmentValue IS NOT NULL OR iae.effortValue IS NOT NULL)";

$result = $connection2->query($sql)->fetch(PDO::FETCH_ASSOC);

echo "<div class='info'>";
echo "<h3>Students with T1-Exam-2025 (Cycle 2) grades:</h3>";
echo "<p><strong>" . $result['studentCount'] . "</strong> students have at least one Cycle 2 grade entered</p>";
echo "</div>";

// Count total assessments with grades
$sql2 = "SELECT COUNT(*) as count
          FROM gibbonInternalAssessmentEntry iae
          JOIN gibbonInternalAssessmentColumn iac ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
          WHERE iac.gibbonReportingCycleID = (SELECT gibbonReportingCycleID FROM gibbonReportingCycle WHERE name = 'T1-Exam-2025' LIMIT 1)
          AND iac.complete = 'Y'
          AND (iae.attainmentValue IS NOT NULL OR iae.effortValue IS NOT NULL)";

$result2 = $connection2->query($sql2)->fetch(PDO::FETCH_ASSOC);

echo "<div class='info'>";
echo "<h3>Total T1-Exam-2025 grade entries:</h3>";
echo "<p><strong>" . $result2['count'] . "</strong> individual grade entries for Cycle 2</p>";
echo "</div>";

if ($result2['count'] == 0) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ NO CYCLE 2 GRADES ENTERED!</h3>";
    echo "<p>This explains why the T1-Exam-2025 report only shows Cycle 1 data.</p>";
    echo "<p>You need to enter grades for T1-Exam-2025 assessments before they can appear in reports.</p>";
    echo "</div>";
}
?>
