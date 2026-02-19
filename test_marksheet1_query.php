<?php
require_once './gibbon.php';
$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th { background: #4e73df; color: white; padding: 10px; text-align: left; }
td { padding: 8px; border: 1px solid #ddd; }
.cycle1 { background: #d4edda; }
.cycle2 { background: #ffcccc; font-weight: bold; }
pre { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; overflow-x: auto; }
</style>";

echo "<h1>Test Marksheet 1 Query - What Data Is Actually Returned?</h1>";

// Get Marksheet 1 report ID
$sqlReport = "SELECT r.gibbonReportID, r.name as reportName, rc.gibbonReportingCycleID, rc.name as cycleName, rc.cycleNumber
              FROM gibbonReport r
              JOIN gibbonReportingCycle rc ON r.gibbonReportingCycleID = rc.gibbonReportingCycleID
              WHERE rc.name = 'Marksheet 1'
              AND r.gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current')";
$report = $connection2->query($sqlReport)->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    echo "<p style='color: red;'>Could not find Marksheet 1 report!</p>";
    exit;
}

echo "<h3>Report Details:</h3>";
echo "<ul>";
echo "<li><strong>Report Name:</strong> " . htmlspecialchars($report['reportName']) . "</li>";
echo "<li><strong>Report ID:</strong> " . $report['gibbonReportID'] . "</li>";
echo "<li><strong>Cycle ID:</strong> " . $report['gibbonReportingCycleID'] . "</li>";
echo "<li><strong>Cycle Name:</strong> " . htmlspecialchars($report['cycleName']) . "</li>";
echo "<li><strong>Cycle Number:</strong> " . $report['cycleNumber'] . "</li>";
echo "</ul>";

// Get a sample student - try to find the student from the screenshot (Missick, Maverick)
$sqlStudent = "SELECT gibbonStudentEnrolmentID, gibbonPerson.gibbonPersonID,
                      CONCAT(gibbonPerson.preferredName, ' ', gibbonPerson.surname) as studentName
               FROM gibbonStudentEnrolment
               JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID = gibbonPerson.gibbonPersonID
               WHERE gibbonStudentEnrolment.gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current')
               AND (gibbonPerson.surname = 'Missick' OR 1=1)
               LIMIT 1";
$student = $connection2->query($sqlStudent)->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "<p style='color: red;'>No students found in current year!</p>";
    exit;
}

echo "<h3>Testing with Student: " . htmlspecialchars($student['studentName']) . " (ID: " . $student['gibbonStudentEnrolmentID'] . ")</h3>";

// Run the EXACT query from InternalAssessmentByCourse.php
$data = array(
    'gibbonStudentEnrolmentID' => $student['gibbonStudentEnrolmentID'],
    'gibbonReportID' => $report['gibbonReportID'],
    'today' => date('Y-m-d')
);

$sql = "SELECT
    gibbonCourse.name AS groupBy,
    gibbonInternalAssessmentColumn.name,
    gibbonInternalAssessmentColumn.description,
    gibbonInternalAssessmentColumn.type,
    gibbonInternalAssessmentColumn.attainment AS attainmentActive,
    gibbonInternalAssessmentEntry.attainmentValue,
    gibbonInternalAssessmentEntry.attainmentDescriptor,
    gibbonInternalAssessmentColumn.effort AS effortActive,
    gibbonInternalAssessmentEntry.effortValue,
    gibbonInternalAssessmentEntry.effortDescriptor,
    gibbonInternalAssessmentColumn.comment AS commentActive,
    gibbonInternalAssessmentEntry.comment,
    gibbonCourse.name AS courseName,
    gibbonCourse.nameShort AS courseNameShort,
    gibbonCourseClass.name AS className,
    gibbonCourseClass.nameShort AS classNameShort,
    gibbonInternalAssessmentColumn.completeDate,

    /* Debug: Show cycle info */
    gibbonInternalAssessmentColumn.gibbonReportingCycleID as assessmentCycleID,
    assessmentCycle.name as assessmentCycleName,
    assessmentCycle.cycleNumber as assessmentCycleNumber,
    reportCycle.cycleNumber as reportCycleNumber,

    /* ✅ Fetching Teacher Name (Ensuring Only Teachers Are Included) */
    (SELECT GROUP_CONCAT(DISTINCT CONCAT(gibbonPerson.title, ' ', gibbonPerson.preferredName, ' ', gibbonPerson.surname)
        ORDER BY gibbonPerson.surname SEPARATOR ', ')
    FROM gibbonCourseClassPerson
    JOIN gibbonPerson ON gibbonCourseClassPerson.gibbonPersonID = gibbonPerson.gibbonPersonID
    WHERE gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
      AND gibbonCourseClassPerson.role = 'Teacher' /* 🔹 Ensuring only teachers are included */
    ) AS teacherName

FROM gibbonReport
JOIN gibbonReportingCycle AS reportCycle
    ON gibbonReport.gibbonReportingCycleID = reportCycle.gibbonReportingCycleID
JOIN gibbonStudentEnrolment
    ON gibbonStudentEnrolment.gibbonSchoolYearID = gibbonReport.gibbonSchoolYearID
JOIN gibbonInternalAssessmentEntry
    ON gibbonInternalAssessmentEntry.gibbonPersonIDStudent = gibbonStudentEnrolment.gibbonPersonID
JOIN gibbonInternalAssessmentColumn
    ON gibbonInternalAssessmentEntry.gibbonInternalAssessmentColumnID = gibbonInternalAssessmentColumn.gibbonInternalAssessmentColumnID
LEFT JOIN gibbonReportingCycle AS assessmentCycle
    ON gibbonInternalAssessmentColumn.gibbonReportingCycleID = assessmentCycle.gibbonReportingCycleID
JOIN gibbonCourseClassPerson
    ON gibbonInternalAssessmentColumn.gibbonCourseClassID = gibbonCourseClassPerson.gibbonCourseClassID
JOIN gibbonCourseClass
    ON gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
JOIN gibbonCourse
    ON gibbonCourseClass.gibbonCourseID = gibbonCourse.gibbonCourseID

WHERE gibbonReport.gibbonReportID = :gibbonReportID
AND gibbonStudentEnrolment.gibbonStudentEnrolmentID = :gibbonStudentEnrolmentID
AND FIND_IN_SET(gibbonStudentEnrolment.gibbonYearGroupID, gibbonReport.gibbonYearGroupIDList)
AND gibbonCourse.gibbonSchoolYearID = gibbonStudentEnrolment.gibbonSchoolYearID
AND gibbonInternalAssessmentColumn.complete = 'Y'
AND gibbonInternalAssessmentColumn.completeDate <= :today
AND (
    gibbonInternalAssessmentColumn.gibbonReportingCycleID = gibbonReport.gibbonReportingCycleID
    OR (assessmentCycle.cycleNumber IS NOT NULL AND assessmentCycle.cycleNumber < reportCycle.cycleNumber)
    OR gibbonInternalAssessmentColumn.gibbonReportingCycleID IS NULL
)

GROUP BY gibbonInternalAssessmentColumn.gibbonInternalAssessmentColumnID,
         gibbonCourse.gibbonCourseID,
         gibbonCourseClass.gibbonCourseClassID
ORDER BY gibbonInternalAssessmentColumn.completeDate DESC, gibbonCourse.nameShort, gibbonCourseClass.nameShort";

echo "<h3>Running Query...</h3>";
echo "<details><summary>Click to see full SQL query</summary><pre>" . htmlspecialchars($sql) . "</pre></details>";

$stmt = $connection2->prepare($sql);
$stmt->execute($data);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Query Results: " . count($results) . " assessments found</h3>";

if (empty($results)) {
    echo "<p style='color: orange;'>No assessments found for this student. They may not have any grades entered yet.</p>";
} else {
    echo "<table>";
    echo "<tr>";
    echo "<th>Assessment Name</th>";
    echo "<th>Course</th>";
    echo "<th>Assessment Cycle</th>";
    echo "<th>Assessment<br>cycleNumber</th>";
    echo "<th>Report<br>cycleNumber</th>";
    echo "<th>Grade</th>";
    echo "<th>Should Be Included?</th>";
    echo "</tr>";

    $cycle1Count = 0;
    $cycle2Count = 0;

    foreach ($results as $r) {
        $rowClass = '';
        $shouldInclude = 'YES';

        if ($r['assessmentCycleNumber'] == 1) {
            $cycle1Count++;
            $rowClass = 'cycle1';
        } elseif ($r['assessmentCycleNumber'] == 2) {
            $cycle2Count++;
            $rowClass = 'cycle2';  // THIS SHOULD NOT HAPPEN for Marksheet 1!
            $shouldInclude = 'NO - WRONG CYCLE!';
        }

        echo "<tr class='$rowClass'>";
        echo "<td><strong>" . htmlspecialchars($r['name']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($r['courseNameShort']) . "</td>";
        echo "<td>" . htmlspecialchars($r['assessmentCycleName']) . "</td>";
        echo "<td>" . ($r['assessmentCycleNumber'] ?: 'NULL') . "</td>";
        echo "<td>" . $r['reportCycleNumber'] . "</td>";
        echo "<td>" . htmlspecialchars($r['attainmentValue'] ?: 'N/A') . "</td>";
        echo "<td>" . $shouldInclude . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li><strong style='color: green;'>Cycle 1 (Marksheet 1) assessments:</strong> $cycle1Count</li>";
    echo "<li><strong style='color: " . ($cycle2Count > 0 ? 'red' : 'green') . ";'>Cycle 2 (T1-Exam-2025) assessments:</strong> $cycle2Count</li>";
    echo "</ul>";

    if ($cycle2Count > 0) {
        echo "<div style='background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0;'>";
        echo "<h3 style='color: #721c24;'>❌ PROBLEM DETECTED!</h3>";
        echo "<p>Cycle 2 assessments are being returned for a Marksheet 1 (Cycle 1) report!</p>";
        echo "<p>This means the WHERE clause filter is NOT working correctly.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0;'>";
        echo "<h3 style='color: #155724;'>✅ Query is Working Correctly!</h3>";
        echo "<p>Only Cycle 1 assessments are being returned for Marksheet 1 report.</p>";
        echo "<p>The issue might be in the template or caching.</p>";
        echo "</div>";
    }
}
?>
