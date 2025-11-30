<?php
include '../../gibbon.php';

if (!isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/prizeGivingReport.php')) {
    die('Access denied');
}

echo "<h2>Testing SQL Operators</h2>";
echo "<pre>";

$grades = ['74%', '75%', '76%', '80%', '99%'];
$threshold = 75;
$operators = ['>', '>=', '<', '<=', '='];

foreach ($operators as $op) {
    echo "\nOperator: {$op} {$threshold}\n";
    echo str_repeat('=', 50) . "\n";

    foreach ($grades as $grade) {
        $numGrade = floatval(str_replace('%', '', $grade));

        $result = false;
        switch ($op) {
            case '>': $result = $numGrade > $threshold; break;
            case '>=': $result = $numGrade >= $threshold; break;
            case '<': $result = $numGrade < $threshold; break;
            case '<=': $result = $numGrade <= $threshold; break;
            case '=': $result = $numGrade == $threshold; break;
        }

        $status = $result ? 'INCLUDE' : 'EXCLUDE';
        printf("%-10s %s %s %-3s -> %s\n", $grade, $numGrade, $op, $threshold, $status);
    }
}

echo "\n\nNow testing actual SQL query with <= operator:\n";
echo str_repeat('=', 50) . "\n";

$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

$sql = "SELECT
    me.attainmentValue as grade,
    CASE
        WHEN me.attainmentValue REGEXP '^[0-9]+(\\.[0-9]+)?%?\$' THEN
            CAST(REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '') AS DECIMAL(10,2))
        ELSE NULL
    END as numericGrade
FROM gibbonInternalAssessmentEntry me
JOIN gibbonInternalAssessmentColumn iac ON me.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonCourse c ON cc.gibbonCourseID = cc.gibbonCourseID
WHERE c.gibbonSchoolYearID = ?
AND c.name = 'Year 7 Art'
AND me.attainmentValue IS NOT NULL
AND TRIM(me.attainmentValue) != ''
AND (
    CASE
        WHEN me.attainmentValue REGEXP '^[0-9]+(\\.[0-9]+)?%?\$' THEN
            CAST(REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '') AS DECIMAL(10,2))
        ELSE NULL
    END <= 75
)
LIMIT 10";

$stmt = $connection2->prepare($sql);
$stmt->execute([$gibbonSchoolYearID]);

echo "Results with <= 75:\n";
while ($row = $stmt->fetch()) {
    printf("Grade: %-10s Numeric: %s\n", $row['grade'], $row['numericGrade']);
}

echo "</pre>";
?>
