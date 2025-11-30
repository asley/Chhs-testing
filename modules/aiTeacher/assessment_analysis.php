<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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
require_once __DIR__ . '/src/DeepSeekAPI.php';

$page->breadcrumbs->add(__('AI Teacher Assistance'), 'index.php');
$page->breadcrumbs->add(__('Assessment Analysis'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/assessment_analysis.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get module settings
    $settings = getAITeacherSettings($pdo);
    $aiEnabled = !empty($settings['deepseek_api_key']);
    $threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 60;
    $selectedCourse = $_GET['course'] ?? '';
    $selectedAssessmentName = $_GET['assessment_name'] ?? ''; // New: Get selected assessment name

    // Get list of courses
    $courses = $pdo->executeQuery([], "SELECT gibbonCourseID, name FROM gibbonCourse ORDER BY name");

    // New: Get list of distinct assessment names
    $assessmentNamesResult = $pdo->executeQuery([], "SELECT DISTINCT name FROM gibbonInternalAssessmentColumn WHERE name IS NOT NULL AND name <> '' ORDER BY name ASC");
    $assessmentNames = $assessmentNamesResult->fetchAll(\PDO::FETCH_COLUMN);


    // Add some CSS for better visibility
    echo <<<CSS
<style>
    .filter-form-container {
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px; /* Adds space between form elements */
    }
    .filter-form-container label {
        display: flex;
        align-items: center;
        gap: 5px; /* Space between label text and input */
    }
    .filter-form-container input[type="number"] {
        width: 80px; /* Increased width for threshold */
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .filter-form-container .button,
    .filter-form-container input[type="submit"] { /* Target Gibbon's default button class and the submit input */
        padding: 6px 12px;
        background-color: #007bff; /* A visible background color */
        color: white;
        border: 1px solid #007bff;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none; /* If it's an <a> styled as a button */
        display: inline-block; /* Ensure proper rendering */
    }
    .filter-form-container .button:hover,
    .filter-form-container input[type="submit"]:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }

    /* Style for the "Get AI Recommendation" button in the table */
    .get-ai-recommendation {
        padding: 6px 12px;
        background-color: #28a745; /* Green background, or choose another distinct color */
        color: white;
        border: 1px solid #28a745;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 0.9em; /* Match other button font size if desired */
    }
    .get-ai-recommendation:hover {
        background-color: #218838; /* Darker green on hover */
        border-color: #1e7e34;
    }
</style>
CSS;

    // Filter form
    echo '<form method="get" action="' . $gibbon->session->get('absoluteURL') . '/index.php" class="filter-form-container">'; // Added a class for styling
    echo '<input type="hidden" name="q" value="/modules/aiTeacher/assessment_analysis.php">';
    echo '<label>' . __('Course') . ': ';
    echo '<select name="course">';
    echo '<option value="">' . __('Please select...') . '</option>';
    while ($course = $courses->fetch()) {
        $selected = ($selectedCourse == $course['gibbonCourseID']) ? 'selected' : '';
        echo '<option value="' . $course['gibbonCourseID'] . '" ' . $selected . '>' . htmlspecialchars($course['name']) . '</option>';
    }
    echo '</select></label>'; // Removed trailing space

    // New: Assessment Name Filter Dropdown
    echo '<label>' . __('Assessment Name') . ': ';
    echo '<select name="assessment_name">';
    echo '<option value="">' . __('All Assessments') . '</option>';
    foreach ($assessmentNames as $assessmentName) {
        $selected = ($selectedAssessmentName == $assessmentName) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($assessmentName) . '" ' . $selected . '>' . htmlspecialchars($assessmentName) . '</option>';
    }
    echo '</select></label>';

    echo '<label>' . __('Failing Threshold') . ': ';
    // Removed inline style, will be handled by CSS block
    echo '<input type="number" name="threshold" value="' . $threshold . '" min="0" max="100">'; 
    echo '</label>'; // Removed trailing space
    echo '<input type="submit" value="' . __('Filter') . '" class="button">';
    echo '</form>';

    if ($selectedCourse) {
        // Query students in the selected course with their latest assessment
        $sql = "
            SELECT
                s.gibbonPersonID,
                s.preferredName,
                s.surname,
                c.gibbonCourseID,
                c.name AS courseName,
                iac.name AS assessmentColumnName, -- Added for clarity, can be used if needed
                iae.attainmentValue
            FROM
                gibbonInternalAssessmentEntry iae
                JOIN gibbonInternalAssessmentColumn iac ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
                JOIN gibbonCourseClass ccc ON iac.gibbonCourseClassID = ccc.gibbonCourseClassID
                JOIN gibbonCourse c ON ccc.gibbonCourseID = c.gibbonCourseID
                JOIN gibbonPerson s ON iae.gibbonPersonIDStudent = s.gibbonPersonID
            WHERE
                c.gibbonCourseID = :courseID";

        $params = ['courseID' => $selectedCourse];

        if (!empty($selectedAssessmentName)) {
            $sql .= " AND iac.name = :assessmentName";
            $params['assessmentName'] = $selectedAssessmentName;
        }

        $sql .= " ORDER BY s.surname, s.preferredName, iac.name"; // Added iac.name to ordering for consistency if multiple assessments per student
        
        $result = $pdo->executeQuery($params, $sql);
        $students = $result->fetchAll();
    
        // Filter only failing students
        $failingStudents = array_filter($students, function($student) use ($threshold) {
            return floatval($student['attainmentValue']) < $threshold;
        });
    
        if (count($failingStudents) > 0) {
            echo '<table class="fullWidth colorOddEven">';
            echo '<tr>';
            echo '<th>' . __('Student') . '</th>';
            echo '<th>' . __('Course') . '</th>';
            echo '<th>' . __('Score') . '</th>';
            echo '<th>' . __('AI Recommendation') . '</th>';
            echo '</tr>';
    
            foreach ($failingStudents as $student) {
                $studentId = htmlspecialchars($student['gibbonPersonID']);
                $studentName = htmlspecialchars($student['preferredName'] . ' ' . $student['surname']);
                $courseName = htmlspecialchars($student['courseName']);
                $score = htmlspecialchars($student['attainmentValue']);
                echo '<tr>';
                echo '<td>' . $studentName . '</td>';
                echo '<td>' . $courseName . '</td>';
                echo '<td>' . $score . '</td>';
                echo '<td>';
                echo '<button class="get-ai-recommendation" data-student-id="' . $studentId . '" data-student-name="' . $studentName . '" data-course-name="' . $courseName . '" data-score="' . $score . '">Get AI Recommendation</button>';
                echo '<div class="ai-recommendation-result" id="ai-recommendation-' . $studentId . '"></div>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Define the URL for JavaScript
            $getRecommendationURL = $gibbon->session->get('absoluteURL').'/modules/aiTeacher/get_ai_recommendation.php';
            ?>
            <script>
            const getRecommendationUrl = '<?php echo $getRecommendationURL; ?>';
            document.querySelectorAll('.get-ai-recommendation').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var studentId = this.getAttribute('data-student-id');
                    var studentName = this.getAttribute('data-student-name');
                    var courseName = this.getAttribute('data-course-name');
                    var score = this.getAttribute('data-score');
                    var resultDiv = document.getElementById('ai-recommendation-' + studentId);
                    resultDiv.innerHTML = 'Loading...';
                    fetch(getRecommendationUrl, { // Use the dynamic URL
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            studentId: studentId, // Add studentId here
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
                        resultDiv.innerHTML = 'Error fetching recommendation.';
                    });
                });
            });
            </script>
            <?php
        } else {
            echo '<p>' . __('No failing students found for this course.') . '</p>';
        }
    } else {
        echo '<p>' . __('Please select a course to view students.') . '</p>';
    }
} // Closing brace for the isActionAccessible check