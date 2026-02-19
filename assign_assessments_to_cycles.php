<?php
/**
 * Assign Internal Assessment Columns to Reporting Cycles
 */

require_once './gibbon.php';

$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #4e73df; padding-bottom: 10px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
th { background: #4e73df; color: white; padding: 12px; text-align: left; position: sticky; top: 0; }
td { padding: 10px; border: 1px solid #ddd; }
tr:nth-child(even) { background: #f9f9f9; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
</style>";

echo "<h1>Assign Assessments to Reporting Cycles</h1>";

try {
    // Get current year
    $sqlYear = "SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current'";
    $resultYear = $connection2->query($sqlYear);
    $yearData = $resultYear->fetch(PDO::FETCH_ASSOC);
    $currentYearID = $yearData['gibbonSchoolYearID'];

    // Get reporting cycles
    $sqlCycles = "SELECT gibbonReportingCycleID, name, sequenceNumber
                  FROM gibbonReportingCycle
                  WHERE gibbonSchoolYearID = :yearID
                  ORDER BY sequenceNumber";
    $stmtCycles = $connection2->prepare($sqlCycles);
    $stmtCycles->execute(['yearID' => $currentYearID]);
    $cycles = $stmtCycles->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<h3>Available Reporting Cycles</h3>";
    echo "<ul>";
    foreach ($cycles as $cycle) {
        echo "<li><strong>" . htmlspecialchars($cycle['name']) . "</strong> (ID: " . $cycle['gibbonReportingCycleID'] . ", Sequence: " . $cycle['sequenceNumber'] . ")</li>";
    }
    echo "</ul>";
    echo "</div>";

    // Auto-assign based on assessment names
    $updated = 0;
    foreach ($cycles as $cycle) {
        $cycleName = $cycle['name'];
        $cycleID = $cycle['gibbonReportingCycleID'];

        // Update assessments matching this cycle name
        $sqlUpdate = "UPDATE gibbonInternalAssessmentColumn iac
                     JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
                     JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
                     SET iac.gibbonReportingCycleID = :cycleID
                     WHERE c.gibbonSchoolYearID = :yearID
                     AND iac.name LIKE :cycleName";

        $stmtUpdate = $connection2->prepare($sqlUpdate);
        $stmtUpdate->execute([
            'cycleID' => $cycleID,
            'yearID' => $currentYearID,
            'cycleName' => '%' . $cycleName . '%'
        ]);

        $rowsAffected = $stmtUpdate->rowCount();
        $updated += $rowsAffected;

        if ($rowsAffected > 0) {
            echo "<div class='success'>✅ Assigned <strong>$rowsAffected</strong> assessments to <strong>" . htmlspecialchars($cycleName) . "</strong></div>";
        }
    }

    // Show current status
    echo "<h2>Current Assessment Assignments</h2>";

    $sqlCheck = "SELECT
        iac.gibbonInternalAssessmentColumnID,
        iac.name as assessmentName,
        iac.gibbonReportingCycleID,
        rc.name as cycleName,
        rc.sequenceNumber,
        c.nameShort as course,
        cc.nameShort as class
    FROM gibbonInternalAssessmentColumn iac
    LEFT JOIN gibbonReportingCycle rc ON iac.gibbonReportingCycleID = rc.gibbonReportingCycleID
    JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
    JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
    WHERE c.gibbonSchoolYearID = :yearID
    ORDER BY c.nameShort, iac.name";

    $stmtCheck = $connection2->prepare($sqlCheck);
    $stmtCheck->execute(['yearID' => $currentYearID]);
    $assessments = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

    $stillUnassigned = 0;
    echo "<table>";
    echo "<tr>";
    echo "<th>Assessment Name</th>";
    echo "<th>Course.Class</th>";
    echo "<th>Reporting Cycle</th>";
    echo "<th>Sequence</th>";
    echo "</tr>";

    foreach ($assessments as $assess) {
        $rowStyle = '';
        if (empty($assess['gibbonReportingCycleID'])) {
            $stillUnassigned++;
            $rowStyle = ' style="background: #ffdddd;"';
        }

        echo "<tr$rowStyle>";
        echo "<td><strong>" . htmlspecialchars($assess['assessmentName']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($assess['course']) . "." . htmlspecialchars($assess['class']) . "</td>";
        if (empty($assess['gibbonReportingCycleID'])) {
            echo "<td><span style='color: red;'>❌ NOT ASSIGNED</span></td>";
            echo "<td>-</td>";
        } else {
            echo "<td>✅ " . htmlspecialchars($assess['cycleName']) . "</td>";
            echo "<td>" . $assess['sequenceNumber'] . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";

    // Summary
    echo "<hr style='margin: 30px 0;'>";
    echo "<h2>Summary</h2>";

    if ($stillUnassigned > 0) {
        echo "<div class='warning'>";
        echo "<p><strong>$stillUnassigned</strong> assessments still need cycle assignment.</p>";
        echo "<p>To fix: Go to <strong>Assess → Internal Assessment</strong>, edit each assessment, and select the reporting cycle.</p>";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "<h3>✅ All Assessments Assigned!</h3>";
        echo "<p>Total assignments updated: <strong>$updated</strong></p>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='warning'>";
    echo "<h3>Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
