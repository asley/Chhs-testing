<?php
/**
 * Add gibbonReportingCycleID column to gibbonInternalAssessmentColumn table
 */

require_once './gibbon.php';

$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #4e73df; padding-bottom: 10px; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
pre { background: #f8f9fa; border: 1px solid #ddd; padding: 10px; overflow-x: auto; }
</style>";

echo "<h1>Add Reporting Cycle Column</h1>";

try {
    // Check if column already exists
    $sql = "SHOW COLUMNS FROM gibbonInternalAssessmentColumn LIKE 'gibbonReportingCycleID'";
    $result = $connection2->query($sql);
    $columnExists = $result->rowCount() > 0;

    if ($columnExists) {
        echo "<div class='info'>";
        echo "<h3>✅ Column Already Exists</h3>";
        echo "<p>The <code>gibbonReportingCycleID</code> column is already present in the <code>gibbonInternalAssessmentColumn</code> table.</p>";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Column Missing - Adding Now...</h3>";
        echo "</div>";

        // Add the column
        $sqlAlter = "ALTER TABLE gibbonInternalAssessmentColumn
                     ADD COLUMN gibbonReportingCycleID INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL
                     AFTER gibbonCourseClassID,
                     ADD KEY gibbonReportingCycleID (gibbonReportingCycleID)";

        $connection2->exec($sqlAlter);

        echo "<div class='success'>";
        echo "<h3>✅ Column Added Successfully!</h3>";
        echo "<p>Added <code>gibbonReportingCycleID</code> to <code>gibbonInternalAssessmentColumn</code> table.</p>";
        echo "<p><strong>SQL executed:</strong></p>";
        echo "<pre>" . htmlspecialchars($sqlAlter) . "</pre>";
        echo "</div>";
    }

    // Verify the column exists now
    $sql = "DESCRIBE gibbonInternalAssessmentColumn";
    $result = $connection2->query($sql);
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);

    $foundColumn = false;
    echo "<div class='info'>";
    echo "<h3>📋 Current Table Structure</h3>";
    echo "<p>Columns in <code>gibbonInternalAssessmentColumn</code>:</p>";
    echo "<ul>";
    foreach ($columns as $col) {
        if ($col['Field'] == 'gibbonReportingCycleID') {
            $foundColumn = true;
            echo "<li><strong style='color: green;'>✅ " . $col['Field'] . "</strong> (" . $col['Type'] . ")</li>";
        } else {
            echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
        }
    }
    echo "</ul>";
    echo "</div>";

    if ($foundColumn) {
        echo "<div class='success'>";
        echo "<h3>🎉 Migration Complete!</h3>";
        echo "<p><strong>Next Steps:</strong></p>";
        echo "<ol>";
        echo "<li>Run the <a href='assign_assessments_to_cycles.php'>assessment assignment tool</a> to link assessments to cycles</li>";
        echo "<li>Test your reports - Marksheet 1 should show ONLY Marksheet 1 data</li>";
        echo "<li>Deploy to live site with <code>git pull</code></li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ Column Not Found</h3>";
        echo "<p>Something went wrong - the column was not added successfully.</p>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Code:</strong> " . $e->getCode() . "</p>";
    echo "</div>";
}
?>
