<?php
// Debug query tester
include '../../gibbon.php';

if (!isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/prizeGivingReport.php')) {
    die('Access denied');
}

echo "<pre>";
echo "=== Testing Grade Filter Query ===\n\n";

// Test query with direct SQL
$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
$gradeThreshold = 75;
$operator = '>';

$sql = "SELECT
    s.preferredName,
    s.surname,
    fg.name as formGroup,
    c.name as courseName,
    iac.name as assessmentName,
    me.attainmentValue as grade,
    CASE
        WHEN me.attainmentValue REGEXP '^[0-9]+(\\\\.[0-9]+)?%?\$' THEN
            CAST(REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '') AS DECIMAL(10,2))
        ELSE NULL
    END as numericGrade
FROM gibbonPerson s
JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = s.gibbonPersonID
JOIN gibbonFormGroup fg ON fg.gibbonFormGroupID = se.gibbonFormGroupID
JOIN gibbonCourseClassPerson ccp ON ccp.gibbonPersonID = s.gibbonPersonID
JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
JOIN gibbonInternalAssessmentColumn iac ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonInternalAssessmentEntry me ON me.gibbonPersonIDStudent = s.gibbonPersonID
    AND me.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
WHERE s.status = 'Full'
AND ccp.role = 'Student'
AND se.gibbonSchoolYearID = :gibbonSchoolYearID
AND c.gibbonSchoolYearID = :gibbonSchoolYearID
AND c.name = 'Year 7 Art'
AND fg.name = '07.1'
AND iac.type = 'Exam'
ORDER BY s.surname, me.attainmentValue
LIMIT 10";

$result = $connection2->prepare($sql);
$result->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);

echo "All grades (before filtering):\n";
echo str_repeat('-', 80) . "\n";
printf("%-20s %-15s %-20s %s\n", "Student", "Assessment", "Raw Grade", "Numeric Grade");
echo str_repeat('-', 80) . "\n";

$rows = $result->fetchAll();
foreach ($rows as $row) {
    printf("%-20s %-15s %-20s %s\n",
        $row['surname'] . ', ' . $row['preferredName'],
        $row['assessmentName'],
        $row['grade'],
        $row['numericGrade'] ?? 'N/A'
    );
}

echo "\n\nNow applying filter: > $gradeThreshold\n";
echo str_repeat('-', 80) . "\n";

foreach ($rows as $row) {
    $shouldInclude = false;
    $reason = '';

    if ($row['grade'] === null || $row['grade'] === '') {
        $reason = 'SKIP: Empty grade';
    } elseif ($row['numericGrade'] !== null) {
        $numGrade = floatval($row['numericGrade']);
        $shouldInclude = $numGrade > $gradeThreshold;
        $reason = $shouldInclude ? "INCLUDE: $numGrade > $gradeThreshold" : "EXCLUDE: $numGrade <= $gradeThreshold";
    } else {
        $reason = 'Letter grade (needs mapping)';
    }

    if ($shouldInclude) {
        printf("%-20s %-15s %-20s %s\n",
            $row['surname'] . ', ' . $row['preferredName'],
            $row['assessmentName'],
            $row['grade'],
            $reason
        );
    }
}

echo "\n</pre>";
