<?php
// Check reporting cycles and internal assessment associations
require_once './gibbon.php';

// Get the database connection
$connection2 = $pdo->getConnection();

echo "<h2>Reporting Cycles</h2>\n";
echo "<pre>\n";

// Get current school year
$sqlYear = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear WHERE status = 'Current'";
$resultYear = $connection2->query($sqlYear);
$yearData = $resultYear->fetch(PDO::FETCH_ASSOC);
$currentYearID = $yearData['gibbonSchoolYearID'];
echo "Current School Year: " . $yearData['name'] . " (ID: $currentYearID)\n\n";

// Get reporting cycles
$sql = "SELECT
    gibbonReportingCycleID,
    name,
    sequenceNumber,
    dateStart,
    dateEnd,
    gibbonYearGroupIDList
FROM gibbonReportingCycle
WHERE gibbonSchoolYearID = :yearID
ORDER BY sequenceNumber";

$stmt = $connection2->prepare($sql);
$stmt->execute(['yearID' => $currentYearID]);
$cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Reporting Cycles:\n";
foreach ($cycles as $cycle) {
    echo "ID: " . $cycle['gibbonReportingCycleID'] . "\n";
    echo "Name: " . $cycle['name'] . "\n";
    echo "Sequence: " . $cycle['sequenceNumber'] . "\n";
    echo "Start: " . $cycle['dateStart'] . " | End: " . $cycle['dateEnd'] . "\n";
    echo "Year Groups: " . $cycle['gibbonYearGroupIDList'] . "\n";
    echo "---\n";
}

// Check internal assessment columns and their reporting cycle associations
echo "\n<h2>Internal Assessment Columns by Reporting Cycle</h2>\n";

$sqlAssess = "SELECT
    iac.gibbonInternalAssessmentColumnID,
    iac.name as assessmentName,
    iac.gibbonReportingCycleID,
    rc.name as cycleName,
    c.nameShort as courseCode,
    cc.nameShort as className,
    COUNT(DISTINCT iae.gibbonInternalAssessmentEntryID) as entryCount
FROM gibbonInternalAssessmentColumn iac
LEFT JOIN gibbonReportingCycle rc ON iac.gibbonReportingCycleID = rc.gibbonReportingCycleID
JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
LEFT JOIN gibbonInternalAssessmentEntry iae ON iac.gibbonInternalAssessmentColumnID = iae.gibbonInternalAssessmentColumnID
WHERE c.gibbonSchoolYearID = :yearID
GROUP BY iac.gibbonInternalAssessmentColumnID
ORDER BY rc.sequenceNumber, c.nameShort, iac.name";

$stmt = $connection2->prepare($sqlAssess);
$stmt->execute(['yearID' => $currentYearID]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Assessment Columns:\n";
foreach ($assessments as $assess) {
    echo "Column ID: " . $assess['gibbonInternalAssessmentColumnID'] . "\n";
    echo "Assessment: " . $assess['assessmentName'] . "\n";
    echo "Course: " . $assess['courseCode'] . "." . $assess['className'] . "\n";
    echo "Reporting Cycle ID: " . ($assess['gibbonReportingCycleID'] ?: 'NULL') . "\n";
    echo "Reporting Cycle Name: " . ($assess['cycleName'] ?: 'NOT ASSIGNED') . "\n";
    echo "Entries: " . $assess['entryCount'] . "\n";
    echo "---\n";
}

echo "</pre>\n";
