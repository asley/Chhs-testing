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

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('AI Teacher Assistance'), 'index.php');
$page->breadcrumbs->add(__('Curriculum Support'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/curriculum_support.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get module settings
    $settings = getAITeacherSettings($pdo);
    
    // Check if API key is configured
    if (empty($settings['deepseek_api_key'])) {
        $page->addError(__('DeepSeek API key is not configured. Please contact your administrator.'));
    } else {
        // Add CSS for button visibility
        echo <<<CSS
<style>
    /* Style for form buttons to ensure visibility */
    .button, 
    input[type="submit"] { /* Target Gibbon's common button class and generic submit inputs */
        padding: 8px 15px;
        background-color: #007bff; /* A visible background color (e.g., blue) */
        color: white;              /* Text color that contrasts with the background */
        border: 1px solid #007bff;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;     /* If it's an <a> styled as a button */
        display: inline-block;     /* Ensure proper rendering */
        font-size: 0.9em;
    }
    .button:hover,
    input[type="submit"]:hover {
        background-color: #0056b3; /* Darker shade on hover */
        border-color: #0056b3;
    }
    /* Specific styling for the form layout if needed */
    .formLayout {
        margin-bottom: 20px;
    }
    .formLayout .right {
        text-align: right; /* Ensure button aligns to the right if container is full width */
        padding-top: 10px;
    }
</style>
CSS;

        // Handle form submission
        if (isset($_POST['submit'])) {
            $subject = $_POST['subject'] ?? '';
            $topic = $_POST['topic'] ?? '';
            $gradeLevel = $_POST['grade_level'] ?? '';
            $objectives = $_POST['objectives'] ?? ''; // Added objectives
            
            if (empty($subject) || empty($topic) || empty($gradeLevel) || empty($objectives)) { // Added objectives check
                $page->addError(__('Please fill in all required fields, including objectives.'));
            } else {
                try {
                    $lessonPlan = generateLessonPlan($pdo, $subject, $topic, $gradeLevel, $objectives); // Pass objectives
                    
                    // Log the action
                    logAITeacherAction($pdo, $gibbon->session->get('gibbonPersonID'), 'Generate Lesson Plan', $subject, 
                        "Topic: {$topic}, Grade Level: {$gradeLevel}, Objectives: {$objectives}", $lessonPlan); // Added objectives to log
                    
                    echo '<div class="success">';
                    echo '<h3>' . __('Generated Lesson Plan') . '</h3>';
                    echo '<div class="message">' . nl2br(htmlspecialchars($lessonPlan ?? '')) . '</div>'; // Corrected line
                    echo '</div>';
                } catch (Exception $e) {
                    $page->addError(__('Failed to generate lesson plan. Please try again.'));
                }
            }
        }
        
        // Display form
        echo '<form method="post" action="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/curriculum_support.php">';
        
        echo '<table class="fullWidth">';
        
        // Subject
        echo '<tr>';
        echo '<td>';
        echo '<b>' . __('Subject') . ' *</b><br/>';
        echo __('Select the CSEC subject.') . '<br/>';
        echo '</td>';
        echo '<td class="right">';
        echo '<select name="subject" style="width: 300px">';
        echo '<option value="">' . __('Please select...') . '</option>';
        '<option value="Mathematics">' . __('Mathematics') . '</option>';
        echo '<option value="English">' . __('English') . '</option>';
        echo '<option value="Biology">' . __('Biology') . '</option>';
        echo '<option value="Chemistry">' . __('Chemistry') . '</option>';
        echo '<option value="Physics">' . __('Physics') . '</option>';
        echo '<option value="History">' . __('History') . '</option>';
        echo '<option value="Geography">' . __('Geography') . '</option>';
        echo '<option value="EDPM">' . __('EDPM') . '</option>';
        echo '<option value="Electricity">' . __('Electricity') . '</option>';
         echo '<option value="Data Operations">' . __('Data Ops') . '</option>';
        echo '<option value="Technical Drawing">' . __('Technical Drawing') . '</option>';
        echo '<option value="Social Studies">' . __('Social Studies') . '</option>';
        echo '<option value="POB">' . __('POB') . '</option>';
        echo '<option value="Information Technology">' . __('Information Technology') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        // Topic
        echo '<tr>';
        echo '<td>';
        echo '<b>' . __('Topic') . ' *</b><br/>';
        echo __('Enter the specific topic for the lesson plan.') . '<br/>';
        echo '</td>';
        echo '<td class="right">';
        echo '<input type="text" name="topic" value="' . htmlspecialchars($_POST['topic'] ?? '') . '" style="width: 300px">';
        echo '</td>';
        echo '</tr>';

        // Objectives
        echo '<tr>';
        echo '<td>';
        echo '<b>' . __('Objectives') . ' *</b><br/>';
        echo __('Enter the learning objectives for the lesson plan (e.g., "Students will be able to identify input devices.").') . '<br/>';
        echo '</td>';
        echo '<td class="right">';
        echo '<textarea name="objectives" style="width: 300px; height: 80px;">' . htmlspecialchars($_POST['objectives'] ?? '') . '</textarea>';
        echo '</td>';
        echo '</tr>';
        
        // Grade Level
        echo '<tr>';
        echo '<td>';
        echo '<b>' . __('Grade Level') . ' *</b><br/>';
        echo __('Select the grade level.') . '<br/>';
        echo '</td>';
        echo '<td class="right">';
        echo '<select name="grade_level" style="width: 300px">';
        echo '<option value="">' . __('Please select...') . '</option>';
        echo '<option value="Form 4">' . __('Form 4') . '</option>';
        echo '<option value="Form 5">' . __('Form 5') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<div class="right">'; // This div is used for alignment
        echo '<input type="submit" name="submit" value="' . __('Generate Lesson Plan') . '" class="button">'; // Added class="button"
        echo '</div>';
        
        echo '</form>';
    }
}