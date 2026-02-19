<?php
/**
 * Database Migration: Add gibbonReportingCycleID to gibbonInternalAssessmentColumn
 * This allows internal assessments to be linked to specific reporting cycles
 */

require_once './gibbon.php';

$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #4e73df; padding-bottom: 10px; }
pre { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; overflow-x: auto; font-family: monospace; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
.step { background: white; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
</style>";

echo "<h1>Fix Reporting Cycles - Database Migration</h1>";

try {
    // Get Gibbon version
    $sql = "SELECT version FROM gibbonSetting WHERE scope='System' AND name='version'";
    $result = $connection2->query($sql);
    $versionData = $result->fetch(PDO::FETCH_ASSOC);
    echo "<div class='info'><strong>Gibbon Version:</strong> " . $versionData['version'] . "</div>";

    // Check if column already exists
    $sql = "SHOW COLUMNS FROM gibbonInternalAssessmentColumn LIKE 'gibbonReportingCycleID'";
    $result = $connection2->query($sql);
    $columnExists = $result->rowCount() > 0;

    if ($columnExists) {
        echo "<div class='info'>✅ Column <code>gibbonReportingCycleID</code> already exists. Skipping migration.</div>";
    } else {
        echo "<div class='step'>";
        echo "<h3>Step 1: Adding gibbonReportingCycleID Column</h3>";

        $sql = "ALTER TABLE gibbonInternalAssessmentColumn
                ADD COLUMN gibbonReportingCycleID INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER gibbonCourseClassID,
                ADD KEY gibbonReportingCycleID (gibbonReportingCycleID)";

        $connection2->exec($sql);

        echo "<div class='success'>✅ Successfully added <code>gibbonReportingCycleID</code> column to <code>gibbonInternalAssessmentColumn</code> table.</div>";
        echo "</div>";
    }

    // Get current year reporting cycles
    $sqlYear = "SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current'";
    $resultYear = $connection2->query($sqlYear);
    $yearData = $resultYear->fetch(PDO::FETCH_ASSOC);
    $currentYearID = $yearData['gibbonSchoolYearID'];

    $sqlCycles = "SELECT gibbonReportingCycleID, name, sequenceNumber
                  FROM gibbonReportingCycle
                  WHERE gibbonSchoolYearID = :yearID
                  ORDER BY sequenceNumber";
    $stmtCycles = $connection2->prepare($sqlCycles);
    $stmtCycles->execute(['yearID' => $currentYearID]);
    $cycles = $stmtCycles->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='step'>";
    echo "<h3>Step 2: Available Reporting Cycles</h3>";
    echo "<ul>";
    foreach ($cycles as $cycle) {
        echo "<li><strong>" . htmlspecialchars($cycle['name']) . "</strong> (ID: " . $cycle['gibbonReportingCycleID'] . ", Sequence: " . $cycle['sequenceNumber'] . ")</li>";
    }
    echo "</ul>";
    echo "</div>";

    // Now assign assessments to cycles based on their names
    echo "<div class='step'>";
    echo "<h3>Step 3: Assigning Assessments to Reporting Cycles</h3>";

    $updated = 0;
    $skipped = 0;

    foreach ($cycles as $cycle) {
        $cycleName = $cycle['name'];
        $cycleID = $cycle['gibbonReportingCycleID'];

        // Update assessments that match this cycle name
        $sqlUpdate = "UPDATE gibbonInternalAssessmentColumn iac
                     JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
                     JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
                     SET iac.gibbonReportingCycleID = :cycleID
                     WHERE c.gibbonSchoolYearID = :yearID
                     AND iac.name LIKE :cycleName
                     AND iac.gibbonReportingCycleID IS NULL";

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

    echo "</div>";

    // Show results
    echo "<div class='step'>";
    echo "<h3>Step 4: Verification</h3>";

    $sqlCheck = "SELECT
        iac.gibbonInternalAssessmentColumnID,
        iac.name as assessmentName,
        iac.gibbonReportingCycleID,
        rc.name as cycleName,
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
    ORDER BY c.nameShort, iac.name";

    $stmtCheck = $connection2->prepare($sqlCheck);
    $stmtCheck->execute(['yearID' => $currentYearID]);
    $assessments = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

    $stillUnassigned = 0;
    echo "<table style='width: 100%; border-collapse: collapse; background: white;'>";
    echo "<tr style='background: #4e73df; color: white;'>";
    echo "<th style='padding: 10px; text-align: left;'>Assessment</th>";
    echo "<th style='padding: 10px; text-align: left;'>Course.Class</th>";
    echo "<th style='padding: 10px; text-align: left;'>Reporting Cycle</th>";
    echo "<th style='padding: 10px; text-align: left;'>Students</th>";
    echo "</tr>";

    foreach ($assessments as $assess) {
        $rowStyle = '';
        if (empty($assess['gibbonReportingCycleID'])) {
            $stillUnassigned++;
            $rowStyle = ' style="background: #ffdddd;"';
        }

        echo "<tr$rowStyle>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($assess['assessmentName']) . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($assess['course']) . "." . htmlspecialchars($assess['class']) . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>";
        if (empty($assess['gibbonReportingCycleID'])) {
            echo "<span style='color: red; font-weight: bold;'>❌ NOT ASSIGNED</span>";
        } else {
            echo "✅ " . htmlspecialchars($assess['cycleName']);
        }
        echo "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $assess['studentCount'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Final summary
    echo "<hr style='margin: 30px 0;'>";
    echo "<h2>Summary</h2>";

    if ($stillUnassigned > 0) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Manual Assignment Needed</h3>";
        echo "<p><strong>$stillUnassigned</strong> assessment columns still need to be assigned to reporting cycles.</p>";
        echo "<p>These assessments couldn't be automatically matched because their names don't contain the cycle names.</p>";
        echo "<p><strong>To fix manually:</strong></p>";
        echo "<ol>";
        echo "<li>Go to <strong>Assess → Internal Assessment</strong></li>";
        echo "<li>For each unassigned assessment (red rows above), click <strong>Edit</strong></li>";
        echo "<li>Select the appropriate <strong>Reporting Cycle</strong></li>";
        echo "<li>Click <strong>Submit</strong></li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "<h3>✅ Migration Complete!</h3>";
        echo "<p>All internal assessment columns have been successfully assigned to reporting cycles.</p>";
        echo "<p><strong>Total assessments updated:</strong> $updated</p>";
        echo "<p>Your reports should now display the correct data for each reporting cycle.</p>";
        echo "</div>";
    }

    echo "<div class='info'>";
    echo "<h3>🧪 Test Your Reports</h3>";
    echo "<ol>";
    echo "<li>Go to <strong>Reports → Manage Reporting Cycles</strong></li>";
    echo "<li>Click <strong>Go to Reports</strong> icon for <strong>Marksheet 1</strong></li>";
    echo "<li>Generate a report and verify it shows ONLY Marksheet 1 data</li>";
    echo "<li>Do the same for <strong>T1-Exam-2025</strong></li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>
