<?php
// Diagnose reporting cycle issue
require_once './gibbon.php';

$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
h2 { color: #333; border-bottom: 2px solid #4e73df; padding-bottom: 10px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th { background: #4e73df; color: white; padding: 10px; text-align: left; }
td { padding: 8px; border: 1px solid #ddd; }
tr:nth-child(even) { background: #f9f9f9; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 10px; margin: 10px 0; }
</style>";

echo "<h1>Reporting Cycle Diagnostic</h1>";

// Get current year
$sqlYear = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear WHERE status = 'Current'";
$resultYear = $connection2->query($sqlYear);
$yearData = $resultYear->fetch(PDO::FETCH_ASSOC);
$currentYearID = $yearData['gibbonSchoolYearID'];

echo "<div class='info'><strong>Current School Year:</strong> " . $yearData['name'] . " (ID: $currentYearID)</div>";

// Get reporting cycles
echo "<h2>1. Reporting Cycles</h2>";
$sql = "SELECT * FROM gibbonReportingCycle
        WHERE gibbonSchoolYearID = :yearID
        ORDER BY sequenceNumber";
$stmt = $connection2->prepare($sql);
$stmt->execute(['yearID' => $currentYearID]);
$cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>ID</th><th>Name</th><th>Sequence</th><th>Date Range</th><th>Year Groups</th></tr>";
foreach ($cycles as $cycle) {
    echo "<tr>";
    echo "<td>" . $cycle['gibbonReportingCycleID'] . "</td>";
    echo "<td><strong>" . $cycle['name'] . "</strong></td>";
    echo "<td>" . $cycle['sequenceNumber'] . "</td>";
    echo "<td>" . $cycle['dateStart'] . " → " . $cycle['dateEnd'] . "</td>";
    echo "<td>" . $cycle['gibbonYearGroupIDList'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check internal assessment columns
echo "<h2>2. Internal Assessment Columns (Marksheets/Exams)</h2>";
$sql = "SELECT
    iac.gibbonInternalAssessmentColumnID,
    iac.name as assessmentName,
    iac.gibbonReportingCycleID,
    rc.name as cycleName,
    rc.sequenceNumber as cycleSeq,
    c.nameShort as course,
    cc.nameShort as class,
    COUNT(DISTINCT iae.gibbonPersonIDStudent) as studentCount
FROM gibbonInternalAssessmentColumn iac
LEFT JOIN gibbonReportingCycle rc ON iac.gibbonReportingCycleID = rc.gibbonReportingCycleID
JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
LEFT JOIN gibbonInternalAssessmentEntry iae ON iac.gibbonInternalAssessmentColumnID = iae.gibbonInternalAssessmentColumnID
WHERE c.gibbonSchoolYearID = :yearID
GROUP BY iac.gibbonInternalAssessmentColumnID
ORDER BY rc.sequenceNumber, c.nameShort, iac.name";

$stmt = $connection2->prepare($sql);
$stmt->execute(['yearID' => $currentYearID]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($assessments)) {
    echo "<div class='warning'>⚠️ No internal assessment columns found!</div>";
} else {
    $unassignedCount = 0;
    echo "<table>";
    echo "<tr><th>Assessment Name</th><th>Course.Class</th><th>Reporting Cycle</th><th>Cycle Seq</th><th>Students</th></tr>";
    foreach ($assessments as $assess) {
        $rowClass = '';
        if (empty($assess['gibbonReportingCycleID'])) {
            $unassignedCount++;
            $rowClass = ' style="background: #ffdddd;"';
        }

        echo "<tr$rowClass>";
        echo "<td><strong>" . $assess['assessmentName'] . "</strong></td>";
        echo "<td>" . $assess['course'] . "." . $assess['class'] . "</td>";
        echo "<td>" . ($assess['cycleName'] ?: '<span style="color: red;">❌ NOT ASSIGNED</span>') . "</td>";
        echo "<td>" . ($assess['cycleSeq'] ?: '-') . "</td>";
        echo "<td>" . $assess['studentCount'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if ($unassignedCount > 0) {
        echo "<div class='warning'><strong>⚠️ Found $unassignedCount assessment columns NOT assigned to a reporting cycle!</strong></div>";
    }
}

// Check reporting scopes and criteria
echo "<h2>3. Reporting Scopes & Criteria</h2>";
$sql = "SELECT
    rs.gibbonReportingScopeID,
    rs.name as scopeName,
    rs.scopeType,
    rc.name as cycleName,
    rc.sequenceNumber as cycleSeq,
    COUNT(DISTINCT rcrit.gibbonReportingCriteriaID) as criteriaCount
FROM gibbonReportingScope rs
JOIN gibbonReportingCycle rc ON rs.gibbonReportingCycleID = rc.gibbonReportingCycleID
LEFT JOIN gibbonReportingCriteria rcrit ON rs.gibbonReportingScopeID = rcrit.gibbonReportingScopeID
WHERE rc.gibbonSchoolYearID = :yearID
GROUP BY rs.gibbonReportingScopeID
ORDER BY rc.sequenceNumber, rs.sequenceNumber";

$stmt = $connection2->prepare($sql);
$stmt->execute(['yearID' => $currentYearID]);
$scopes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($scopes)) {
    echo "<div class='warning'>⚠️ No reporting scopes found! This is needed for reports to work.</div>";
} else {
    echo "<table>";
    echo "<tr><th>Scope Name</th><th>Type</th><th>Cycle</th><th>Cycle Seq</th><th>Criteria Count</th></tr>";
    foreach ($scopes as $scope) {
        echo "<tr>";
        echo "<td>" . $scope['scopeName'] . "</td>";
        echo "<td>" . $scope['scopeType'] . "</td>";
        echo "<td><strong>" . $scope['cycleName'] . "</strong></td>";
        echo "<td>" . $scope['cycleSeq'] . "</td>";
        echo "<td>" . $scope['criteriaCount'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check reports
echo "<h2>4. Reports Configuration</h2>";
$sql = "SELECT
    r.gibbonReportID,
    r.name as reportName,
    rc.name as cycleName,
    rc.sequenceNumber as cycleSeq,
    COUNT(DISTINCT rt.gibbonReportTemplateID) as templateCount
FROM gibbonReport r
JOIN gibbonReportingCycle rc ON r.gibbonReportingCycleID = rc.gibbonReportingCycleID
LEFT JOIN gibbonReportTemplate rt ON r.gibbonReportID = rt.gibbonReportID
WHERE r.gibbonSchoolYearID = :yearID
GROUP BY r.gibbonReportID
ORDER BY rc.sequenceNumber";

$stmt = $connection2->prepare($sql);
$stmt->execute(['yearID' => $currentYearID]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($reports)) {
    echo "<div class='warning'>⚠️ No reports configured for this school year!</div>";
} else {
    echo "<table>";
    echo "<tr><th>Report ID</th><th>Report Name</th><th>Reporting Cycle</th><th>Cycle Seq</th><th>Templates</th></tr>";
    foreach ($reports as $report) {
        echo "<tr>";
        echo "<td>" . $report['gibbonReportID'] . "</td>";
        echo "<td><strong>" . $report['reportName'] . "</strong></td>";
        echo "<td>" . $report['cycleName'] . "</td>";
        echo "<td>" . $report['cycleSeq'] . "</td>";
        echo "<td>" . $report['templateCount'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr style='margin: 30px 0;'>";
echo "<h2>💡 Common Issues & Solutions</h2>";
echo "<div class='info'>";
echo "<p><strong>If you see Marksheet 1 showing T1-Exam-2025 data, check:</strong></p>";
echo "<ol>";
echo "<li><strong>Internal Assessment Columns:</strong> Make sure each assessment column (Marksheet 1, T1-Exam-2025) is assigned to the correct reporting cycle</li>";
echo "<li><strong>Reporting Scopes:</strong> Each reporting cycle should have its own scopes</li>";
echo "<li><strong>Reports:</strong> Each report should be linked to only ONE reporting cycle</li>";
echo "<li><strong>Template Filters:</strong> Report templates may have data sources that need proper cycle filtering</li>";
echo "</ol>";
echo "</div>";

echo "<div class='warning'>";
echo "<p><strong>⚠️ CRITICAL:</strong> If internal assessment columns are NOT assigned to reporting cycles (showing ❌ NOT ASSIGNED above), this will cause all assessments to appear in all reports!</p>";
echo "<p><strong>Fix:</strong> Go to <em>Assess → Internal Assessment</em> and edit each assessment column to assign it to the correct reporting cycle.</p>";
echo "</div>";
?>