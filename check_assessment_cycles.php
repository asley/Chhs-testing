<?php
require_once './gibbon.php';
$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th { background: #4e73df; color: white; padding: 10px; text-align: left; }
td { padding: 8px; border: 1px solid #ddd; }
.cycle1 { background: #d4edda; }
.cycle2 { background: #fff3cd; }
.highlight { background: #ffcccc; font-weight: bold; }
</style>";

echo "<h1>Internal Assessment Cycle Assignments</h1>";

// Get cycles
$sqlCycles = "SELECT gibbonReportingCycleID, name, cycleNumber
              FROM gibbonReportingCycle
              WHERE gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current')
              ORDER BY cycleNumber";
$cycles = $connection2->query($sqlCycles)->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Reporting Cycles:</h3><ul>";
foreach ($cycles as $c) {
    echo "<li><strong>" . htmlspecialchars($c['name']) . "</strong> - ID: {$c['gibbonReportingCycleID']}, cycleNumber: {$c['cycleNumber']}</li>";
}
echo "</ul>";

// Get all internal assessments
$sql = "SELECT
    iac.gibbonInternalAssessmentColumnID,
    iac.name as assessmentName,
    iac.gibbonReportingCycleID,
    rc.name as cycleName,
    rc.cycleNumber,
    c.nameShort as course,
    cc.nameShort as class,
    COUNT(DISTINCT iae.gibbonPersonIDStudent) as studentCount
FROM gibbonInternalAssessmentColumn iac
LEFT JOIN gibbonReportingCycle rc ON iac.gibbonReportingCycleID = rc.gibbonReportingCycleID
JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
LEFT JOIN gibbonInternalAssessmentEntry iae ON iac.gibbonInternalAssessmentColumnID = iae.gibbonInternalAssessmentColumnID
WHERE c.gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current')
AND iac.complete = 'Y'
GROUP BY iac.gibbonInternalAssessmentColumnID
ORDER BY c.nameShort, iac.name";

$assessments = $connection2->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>All Internal Assessments:</h3>";
echo "<table>";
echo "<tr><th>Assessment Name</th><th>Course.Class</th><th>Assigned Cycle</th><th>cycleNumber</th><th>Students</th></tr>";

foreach ($assessments as $a) {
    $rowClass = '';
    if ($a['cycleNumber'] == 1) $rowClass = 'cycle1';
    elseif ($a['cycleNumber'] == 2) $rowClass = 'cycle2';

    // Highlight if assessment name doesn't match cycle name
    if ($a['cycleName'] && stripos($a['assessmentName'], $a['cycleName']) === false) {
        $rowClass = 'highlight';
    }

    echo "<tr class='$rowClass'>";
    echo "<td><strong>" . htmlspecialchars($a['assessmentName']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($a['course']) . "." . htmlspecialchars($a['class']) . "</td>";
    echo "<td>" . htmlspecialchars($a['cycleName'] ?: 'NOT ASSIGNED') . "</td>";
    echo "<td>" . ($a['cycleNumber'] ?: 'NULL') . "</td>";
    echo "<td>" . $a['studentCount'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Legend:</strong></p>";
echo "<ul>";
echo "<li class='cycle1' style='display:inline-block; padding:5px; margin:5px;'>Cycle 1 (Marksheet 1)</li>";
echo "<li class='cycle2' style='display:inline-block; padding:5px; margin:5px;'>Cycle 2 (T1-Exam-2025)</li>";
echo "<li class='highlight' style='display:inline-block; padding:5px; margin:5px;'>Mismatch: Assessment name doesn't contain cycle name</li>";
echo "</ul>";
?>
