<?php
include '../../gibbon.php';

if (!isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/prizeGivingReport.php')) {
    die('Access denied');
}

echo "<h2>Testing SQL Query</h2>";
echo "<pre>";

$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

// Simple test - just get all grades for Year 7 Art, 07.1
$sql = "SELECT
    s.surname,
    s.preferredName,
    me.attainmentValue as grade,
    CAST(REPLACE(REPLACE(TRIM(me.attainmentValue), '%', ''), ' ', '') AS DECIMAL(10,2)) as numericGrade
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
AND se.gibbonSchoolYearID = ?
AND c.gibbonSchoolYearID = ?
AND c.name = 'Year 7 Art'
AND fg.name = '07.1'
ORDER BY s.surname, s.preferredName";

$stmt = $connection2->prepare($sql);
$stmt->execute([$gibbonSchoolYearID, $gibbonSchoolYearID]);

echo "All students in Year 7 Art, Form 07.1:\n";
echo str_repeat('=', 80) . "\n";
printf("%-20s %-20s %-15s\n", "Student", "Grade", "Numeric Value");
echo str_repeat('=', 80) . "\n";

$students = [];
while ($row = $stmt->fetch()) {
    printf("%-20s %-20s %-15s\n",
        $row['surname'] . ', ' . $row['preferredName'],
        $row['grade'] ?? '(empty)',
        $row['numericGrade'] ?? 'NULL'
    );
    $students[] = $row;
}

echo "\n\n";
echo "Now filtering for > 75:\n";
echo str_repeat('=', 80) . "\n";

foreach ($students as $row) {
    if ($row['grade'] === null || trim($row['grade']) === '') {
        echo "SKIP: " . $row['surname'] . " - empty grade\n";
        continue;
    }

    if ($row['numericGrade'] !== null && $row['numericGrade'] > 75) {
        printf("INCLUDE: %-20s grade=%s numeric=%s\n",
            $row['surname'] . ', ' . $row['preferredName'],
            $row['grade'],
            $row['numericGrade']
        );
    } else {
        printf("EXCLUDE: %-20s grade=%s numeric=%s (not > 75)\n",
            $row['surname'] . ', ' . $row['preferredName'],
            $row['grade'],
            $row['numericGrade'] ?? 'NULL'
        );
    }
}

echo "</pre>";
?>
