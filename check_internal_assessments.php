<?php
// Simple check for internal assessment columns
require_once './gibbon.php';

$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #4e73df; padding-bottom: 10px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
th { background: #4e73df; color: white; padding: 12px; text-align: left; position: sticky; top: 0; }
td { padding: 10px; border: 1px solid #ddd; }
tr:nth-child(even) { background: #f9f9f9; }
.no-cycle { background: #ffdddd !important; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
</style>";

echo "<h1>Internal Assessment Columns Check</h1>";

// Get current year
try {
    $sqlYear = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear WHERE status = 'Current'";
    $resultYear = $connection2->query($sqlYear);
    $yearData = $resultYear->fetch(PDO::FETCH_ASSOC);
    $currentYearID = $yearData['gibbonSchoolYearID'];

    echo "<div class='info'><strong>Current School Year:</strong> " . $yearData['name'] . " (ID: $currentYearID)</div>";

    // Get reporting cycles for reference
    $sqlCycles = "SELECT gibbonReportingCycleID, name, sequenceNumber
                  FROM gibbonReportingCycle
                  WHERE gibbonSchoolYearID = :yearID
                  ORDER BY sequenceNumber";
    $stmtCycles = $connection2->prepare($sqlCycles);
    $stmtCycles->execute(['yearID' => $currentYearID]);
    $cycles = $stmtCycles->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Available Reporting Cycles</h2>";
    echo "<table>";
    echo "<tr><th>Cycle ID</th><th>Name</th><th>Sequence</th></tr>";
    foreach ($cycles as $cycle) {
        echo "<tr>";
        echo "<td>" . $cycle['gibbonReportingCycleID'] . "</td>";
        echo "<td><strong>" . $cycle['name'] . "</strong></td>";
        echo "<td>" . $cycle['sequenceNumber'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Get all internal assessment columns
    $sql = "SELECT
        iac.gibbonInternalAssessmentColumnID,
        iac.name as assessmentName,
        iac.gibbonReportingCycleID,
        iac.type as assessmentType,
        rc.name as cycleName,
        rc.sequenceNumber as cycleSeq,
        c.name as courseName,
        c.nameShort as courseCode,
        cc.nameShort as className,
        COUNT(DISTINCT iae.gibbonPersonIDStudent) as studentCount,
        COUNT(DISTINCT iae.gibbonInternalAssessmentEntryID) as entryCount
    FROM gibbonInternalAssessmentColumn iac
    LEFT JOIN gibbonReportingCycle rc ON iac.gibbonReportingCycleID = rc.gibbonReportingCycleID
    JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
    JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
    LEFT JOIN gibbonInternalAssessmentEntry iae ON iac.gibbonInternalAssessmentColumnID = iae.gibbonInternalAssessmentColumnID
    WHERE c.gibbonSchoolYearID = :yearID
    GROUP BY iac.gibbonInternalAssessmentColumnID
    ORDER BY c.nameShort, iac.name";

    $stmt = $connection2->prepare($sql);
    $stmt->execute(['yearID' => $currentYearID]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($assessments)) {
        echo "<div class='warning'>⚠️ <strong>No internal assessment columns found!</strong> You need to create assessments first.</div>";
    } else {
        $unassignedCount = 0;
        $totalCount = count($assessments);

        echo "<h2>Internal Assessment Columns (" . $totalCount . " total)</h2>";
        echo "<div class='info'>📋 These are the columns where teachers enter grades (Marksheet 1, T1-Exam-2025, etc.)</div>";

        echo "<table>";
        echo "<tr>";
        echo "<th>Column ID</th>";
        echo "<th>Assessment Name</th>";
        echo "<th>Type</th>";
        echo "<th>Course</th>";
        echo "<th>Class</th>";
        echo "<th>Reporting Cycle</th>";
        echo "<th>Cycle #</th>";
        echo "<th>Students</th>";
        echo "<th>Entries</th>";
        echo "</tr>";

        foreach ($assessments as $assess) {
            $rowClass = '';
            if (empty($assess['gibbonReportingCycleID'])) {
                $unassignedCount++;
                $rowClass = ' class="no-cycle"';
            }

            echo "<tr$rowClass>";
            echo "<td>" . $assess['gibbonInternalAssessmentColumnID'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($assess['assessmentName']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($assess['assessmentType'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($assess['courseName']) . "</td>";
            echo "<td>" . htmlspecialchars($assess['courseCode']) . "." . htmlspecialchars($assess['className']) . "</td>";

            if (empty($assess['gibbonReportingCycleID'])) {
                echo "<td><strong style='color: red;'>❌ NOT ASSIGNED</strong></td>";
                echo "<td>-</td>";
            } else {
                echo "<td>" . htmlspecialchars($assess['cycleName']) . "</td>";
                echo "<td>" . $assess['cycleSeq'] . "</td>";
            }

            echo "<td>" . $assess['studentCount'] . "</td>";
            echo "<td>" . $assess['entryCount'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Summary
        if ($unassignedCount > 0) {
            echo "<div class='warning'>";
            echo "<h3>⚠️ PROBLEM FOUND!</h3>";
            echo "<p><strong>$unassignedCount out of $totalCount assessment columns are NOT assigned to a reporting cycle!</strong></p>";
            echo "<p><strong>This is why you're seeing the wrong data in reports.</strong></p>";
            echo "<p>📝 <strong>How to fix:</strong></p>";
            echo "<ol>";
            echo "<li>Go to <strong>Assess → Internal Assessment</strong></li>";
            echo "<li>For each class, click the <strong>Pencil icon</strong> to edit the assessment column</li>";
            echo "<li>Look for the <strong>'Reporting Cycle'</strong> dropdown</li>";
            echo "<li>Select the appropriate cycle:";
            echo "<ul>";
            echo "<li><strong>Marksheet 1</strong> assessments → Choose <em>'Marksheet 1'</em> cycle</li>";
            echo "<li><strong>T1-Exam-2025</strong> assessments → Choose <em>'T1-Exam-2025'</em> cycle</li>";
            echo "</ul></li>";
            echo "<li>Click <strong>Submit</strong></li>";
            echo "<li>Repeat for ALL red-highlighted rows above</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "<h3>✅ All Assessment Columns Are Assigned</h3>";
            echo "<p>All internal assessment columns have reporting cycles assigned. If you're still seeing the wrong data, the issue might be in report template configuration.</p>";
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='warning'>";
    echo "<h3>Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
