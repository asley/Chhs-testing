<?php
require_once './gibbon.php';
$connection2 = $pdo->getConnection();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 12px; }
th { background: #4e73df; color: white; padding: 10px; text-align: left; position: sticky; top: 0; }
td { padding: 8px; border: 1px solid #ddd; }
.cycle1 { background: #d4edda; }
.cycle2 { background: #fff3cd; }
.info { background: #e7f3ff; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
h2 { color: #333; border-bottom: 2px solid #4e73df; padding-bottom: 10px; margin-top: 30px; }
</style>";

echo "<h1>Test Both Reports After Reverting Changes</h1>";

// Get both reports
$sqlReports = "SELECT r.gibbonReportID, r.name as reportName, rc.gibbonReportingCycleID, rc.name as cycleName, rc.cycleNumber
              FROM gibbonReport r
              JOIN gibbonReportingCycle rc ON r.gibbonReportingCycleID = rc.gibbonReportingCycleID
              WHERE rc.name IN ('Marksheet 1', 'T1-Exam-2025')
              AND r.gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current')
              ORDER BY rc.cycleNumber";
$reports = $connection2->query($sqlReports)->fetchAll(PDO::FETCH_ASSOC);

// Get a sample student
$sqlStudent = "SELECT gibbonStudentEnrolmentID, gibbonPerson.gibbonPersonID,
                      CONCAT(gibbonPerson.preferredName, ' ', gibbonPerson.surname) as studentName
               FROM gibbonStudentEnrolment
               JOIN gibbonPerson ON gibbonStudentEnrolment.gibbonPersonID = gibbonPerson.gibbonPersonID
               WHERE gibbonStudentEnrolment.gibbonSchoolYearID = (SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status = 'Current')
               AND (gibbonPerson.surname = 'Missick' OR 1=1)
               LIMIT 1";
$student = $connection2->query($sqlStudent)->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "<p style='color: red;'>No students found!</p>";
    exit;
}

echo "<div class='info'>";
echo "<h3>Testing with Student: " . htmlspecialchars($student['studentName']) . "</h3>";
echo "<p>Student Enrolment ID: " . $student['gibbonStudentEnrolmentID'] . "</p>";
echo "</div>";

// Test each report
foreach ($reports as $report) {
    echo "<h2>Report: " . htmlspecialchars($report['reportName']) . " (Cycle " . $report['cycleNumber'] . ")</h2>";

    // Run the REVERTED query (no cycle filtering)
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
        (SELECT name FROM gibbonReportingCycle WHERE gibbonReportingCycleID = gibbonInternalAssessmentColumn.gibbonReportingCycleID) as assessmentCycleName,
        (SELECT cycleNumber FROM gibbonReportingCycle WHERE gibbonReportingCycleID = gibbonInternalAssessmentColumn.gibbonReportingCycleID) as assessmentCycleNumber

    FROM gibbonReport
    JOIN gibbonStudentEnrolment
        ON gibbonStudentEnrolment.gibbonSchoolYearID = gibbonReport.gibbonSchoolYearID
    JOIN gibbonInternalAssessmentEntry
        ON gibbonInternalAssessmentEntry.gibbonPersonIDStudent = gibbonStudentEnrolment.gibbonPersonID
    JOIN gibbonInternalAssessmentColumn
        ON gibbonInternalAssessmentEntry.gibbonInternalAssessmentColumnID = gibbonInternalAssessmentColumn.gibbonInternalAssessmentColumnID
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

    GROUP BY gibbonInternalAssessmentColumn.gibbonInternalAssessmentColumnID,
             gibbonCourse.gibbonCourseID,
             gibbonCourseClass.gibbonCourseClassID
    ORDER BY gibbonInternalAssessmentColumn.completeDate DESC, gibbonCourse.nameShort, gibbonCourseClass.nameShort";

    $stmt = $connection2->prepare($sql);
    $stmt->execute($data);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Query returned " . count($results) . " assessments</strong></p>";

    if (!empty($results)) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Assessment Name</th>";
        echo "<th>Course</th>";
        echo "<th>Assessment Cycle</th>";
        echo "<th>Assessment<br>cycleNumber</th>";
        echo "<th>Grade</th>";
        echo "</tr>";

        $cycle1Count = 0;
        $cycle2Count = 0;

        foreach ($results as $r) {
            $rowClass = '';
            if ($r['assessmentCycleNumber'] == 1) {
                $cycle1Count++;
                $rowClass = 'cycle1';
            } elseif ($r['assessmentCycleNumber'] == 2) {
                $cycle2Count++;
                $rowClass = 'cycle2';
            }

            echo "<tr class='$rowClass'>";
            echo "<td><strong>" . htmlspecialchars($r['name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($r['courseNameShort']) . "</td>";
            echo "<td>" . htmlspecialchars($r['assessmentCycleName']) . "</td>";
            echo "<td>" . ($r['assessmentCycleNumber'] ?: 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($r['attainmentValue'] ?: 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<div class='info'>";
        echo "<h4>Assessment Breakdown:</h4>";
        echo "<ul>";
        echo "<li><strong style='color: green;'>Cycle 1 (Marksheet 1):</strong> $cycle1Count assessments</li>";
        echo "<li><strong style='color: orange;'>Cycle 2 (T1-Exam-2025):</strong> $cycle2Count assessments</li>";
        echo "<li><strong>Total:</strong> " . ($cycle1Count + $cycle2Count) . " assessments</li>";
        echo "</ul>";
        echo "</div>";

        // Analysis
        if ($report['cycleName'] == 'Marksheet 1') {
            if ($cycle2Count > 0) {
                echo "<div class='warning'>";
                echo "<h4>❌ PROBLEM: Marksheet 1 is showing Cycle 2 assessments!</h4>";
                echo "<p>The query returns ALL assessments regardless of cycle. The template is responsible for filtering.</p>";
                echo "</div>";
            } else {
                echo "<div class='success'>";
                echo "<h4>✅ Correct: Only Cycle 1 assessments</h4>";
                echo "</div>";
            }
        } elseif ($report['cycleName'] == 'T1-Exam-2025') {
            if ($cycle1Count > 0 && $cycle2Count > 0) {
                echo "<div class='success'>";
                echo "<h4>✅ Correct: T1-Exam-2025 shows both cycles (cumulative)</h4>";
                echo "</div>";
            } elseif ($cycle2Count == 0) {
                echo "<div class='warning'>";
                echo "<h4>⚠️ T1-Exam-2025 is missing Cycle 2 assessments</h4>";
                echo "</div>";
            }
        }
    }
}

echo "<div class='info'>";
echo "<h3>📋 Summary</h3>";
echo "<p>After reverting the changes:</p>";
echo "<ul>";
echo "<li>The <strong>InternalAssessmentByCourse</strong> data source now returns <strong>ALL completed assessments</strong> regardless of cycle</li>";
echo "<li>The <strong>template</strong> (Twig file) is responsible for filtering which assessments to display</li>";
echo "<li>The template uses <code>limitByReportingCycle</code> config and matches assessments by NAME</li>";
echo "</ul>";
echo "<p><strong>Root cause:</strong> The template's name-matching logic may not be working correctly, or the config is set to show all cycles.</p>";
echo "</div>";
?>
