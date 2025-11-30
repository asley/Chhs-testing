<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010-2023, Gibbon Team and contributors

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Gibbon includes
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';

// Initialize PDO connection
global $pdo;
if (!isset($pdo)) {
    $pdo = $connection2 ?? new PDO($dsn, $username, $password);
}

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('AI Teacher Assistance'), 'index.php');
$page->breadcrumbs->add(__('My AI Feedback'));

// Get logged-in user's ID and role
$loggedInUserId = $gibbon->session->get('gibbonPersonID');
$loggedInUserRoleId = $gibbon->session->get('gibbonRoleIDCurrent');

// Define or fetch the Student Role ID. This might need adjustment based on your Gibbon setup.
// A more robust way would be to get role by name: $studentRoleID = $gibbon->roles->getRoleIDByName('Student');
$studentRoleID = 5; // Placeholder: Replace with actual Student Role ID from your Gibbon's gibbonRole table

// Replace permission check
if (!isActionAccessible($guid, $connection2, '/modules/aiTeacher/my_ai_feedback.php')) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Get module settings
$settings = getAITeacherSettings($pdo);
$aiEnabled = !empty($settings['deepseek_api_key']);
$threshold = $settings['score_threshold'] ?? 60; // Use threshold from settings or default

echo '<h3>' . __('Assessments Requiring Attention') . '</h3>';
echo '<p>' . sprintf(__('Below are your assessments where your score is less than %s%%. You can request AI-generated feedback to help you improve.'), $threshold) . '</p>';

// Query student's assessments
$sql = "
    SELECT
        s.gibbonPersonID,
        s.preferredName,
        s.surname,
        c.gibbonCourseID,
        c.name AS courseName,
        iac.name AS assessmentColumnName,
        iae.attainmentValue
    FROM
        gibbonInternalAssessmentEntry iae
        JOIN gibbonInternalAssessmentColumn iac ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
        JOIN gibbonCourseClass ccc ON iac.gibbonCourseClassID = ccc.gibbonCourseClassID
        JOIN gibbonCourse c ON ccc.gibbonCourseID = c.gibbonCourseID
        JOIN gibbonPerson s ON iae.gibbonPersonIDStudent = s.gibbonPersonID
    WHERE
        iae.gibbonPersonIDStudent = :studentID
        AND iae.attainmentValue < :threshold
    ORDER BY
        c.name, iac.name";

$params = ['studentID' => $loggedInUserId, 'threshold' => $threshold];
$result = $pdo->executeQuery($params, $sql);
$assessments = $result->fetchAll();

if (count($assessments) > 0) {
    echo '<table class="fullWidth colorOddEven">';
    echo '<tr>';
    echo '<th>' . __('Course') . '</th>';
    echo '<th>' . __('Assessment') . '</th>';
    echo '<th>' . __('Your Score') . '</th>';
    echo '<th>' . __('AI Feedback') . '</th>';
    echo '</tr>';

    foreach ($assessments as $assessment) {
        $studentId = htmlspecialchars($assessment['gibbonPersonID']); // This will be the logged-in student's ID
        $studentName = htmlspecialchars($assessment['preferredName'] . ' ' . $assessment['surname']);
        $courseName = htmlspecialchars($assessment['courseName']);
        $assessmentName = htmlspecialchars($assessment['assessmentColumnName']);
        $score = htmlspecialchars($assessment['attainmentValue']);
        // Create a unique ID for the result div based on course and assessment name to avoid conflicts if a student has multiple entries for the same course (though less likely here)
        $resultDivId = 'ai-feedback-' . md5($studentId . $courseName . $assessmentName);


        echo '<tr>';
        echo '<td>' . $courseName . '</td>';
        echo '<td>' . $assessmentName . '</td>';
        echo '<td>' . $score . '%</td>';
        echo '<td>';
        if ($aiEnabled) {
            echo '<button class="get-ai-feedback" data-student-id="' . $studentId . '" data-student-name="' . $studentName . '" data-course-name="' . $courseName . '" data-score="' . $score . '" data-result-div-id="' . $resultDivId . '">' . __('Get My AI Feedback') . '</button>';
            echo '<div class="ai-feedback-result" id="' . $resultDivId . '"></div>';
        } else {
            echo __('AI feedback is currently not available.');
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';

    // Define the URL for JavaScript
    $getRecommendationURL = $gibbon->session->get('absoluteURL').'/modules/aiTeacher/get_ai_recommendation.php';
    ?>
    <style>
        .get-ai-feedback {
            padding: 6px 12px;
            background-color: #28a745;
            color: white;
            border: 1px solid #28a745;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
        }
        .get-ai-feedback:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .ai-feedback-result {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
    </style>
    <script>
    const getFeedbackUrl = '<?php echo $getRecommendationURL; ?>';
    document.querySelectorAll('.get-ai-feedback').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var studentId = this.getAttribute('data-student-id');
            var studentName = this.getAttribute('data-student-name');
            var courseName = this.getAttribute('data-course-name');
            var score = this.getAttribute('data-score');
            var resultDivId = this.getAttribute('data-result-div-id');
            var resultDiv = document.getElementById(resultDivId);

            resultDiv.innerHTML = 'Loading feedback...';
            fetch(getFeedbackUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    studentId: studentId, // Important: send studentId
                    studentName: studentName,
                    courseName: courseName,
                    score: score
                })
            })
            .then(response => response.text())
            .then(data => {
                resultDiv.innerHTML = data;
            })
            .catch(err => {
                resultDiv.innerHTML = 'Error fetching feedback.';
                console.error('Feedback fetch error:', err);
            });
        });
    });
    </script>
    <?php
} else {
    echo '<p>' . __('No assessments found requiring attention at this time.') . '</p>';
}
?>