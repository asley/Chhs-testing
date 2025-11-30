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

use Gibbon\Module\aiTeacher\DeepSeekAPI;

// Gibbon includes
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('AI Teacher Assistance'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/index.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get module settings
    $settings = getAITeacherSettings($pdo);
    
    // Check if API key is configured
    if (empty($settings['deepseek_api_key'])) {
        $page->addError(__('DeepSeek API key is not configured. Please contact your administrator.'));
    } else {
        // Display dashboard
        echo '<h2>' . __('AI Teacher Dashboard') . '</h2>';
        
        // Quick actions
        echo '<div class="linkTop">';
        echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/curriculum_support.php">' . __('Curriculum Support') . '</a> | ';
        echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/assessment_analysis.php">' . __('Assessment Analysis') . '</a> | ';
        echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/resource_generator.php">' . __('Resource Generator') . '</a>';
        echo '</div>';
        
        // Recent activity
        echo '<h3>' . __('Recent Activity') . '</h3>';
        
        $sql = "SELECT l.*, p.preferredName, p.surname 
                FROM aiTeacherLogs l 
                JOIN gibbonPerson p ON l.gibbonPersonID = p.gibbonPersonID 
                ORDER BY l.timestamp DESC 
                LIMIT 5";
        $result = $pdo->executeQuery(array(), $sql);
        
        if ($result->rowCount() > 0) {
            echo '<table class="fullWidth colorOddEven">';
            echo '<tr>';
            echo '<th>' . __('Date') . '</th>';
            echo '<th>' . __('User') . '</th>';
            echo '<th>' . __('Action') . '</th>';
            echo '<th>' . __('Subject') . '</th>';
            echo '</tr>';
            
            while ($row = $result->fetch()) {
                echo '<tr>';
                echo '<td>' . date('Y-m-d H:i', strtotime($row['timestamp'])) . '</td>';
                echo '<td>' . $row['preferredName'] . ' ' . $row['surname'] . '</td>';
                echo '<td>' . __($row['action']) . '</td>';
                echo '<td>' . $row['subject'] . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        } else {
            echo '<p>' . __('No recent activity found.') . '</p>';
        }
        
        // Quick tools
        echo '<h3>' . __('Quick Tools') . '</h3>';
        
        echo '<div class="linkTop">';
        echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/generate_lesson_plan.php">' . __('Generate Lesson Plan') . '</a> | ';
        echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/generate_assessment.php">' . __('Generate Assessment') . '</a> | ';
        echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/upload_resource.php">' . __('Upload Resource') . '</a>';
        echo '</div>';
        
        // System status
        echo '<h3>' . __('System Status') . '</h3>';
        
            try {
                $api = new DeepSeekAPI($settings['deepseek_api_key']);
                // Construct the data payload for the send() method
                // $data = [ // Old data structure for send()
                //     'model' => 'deepseek-chat', 
                //     'messages' => [
                //         ['role' => 'user', 'content' => 'Test connection']
                //     ]
                // ];
                // $testResponse = $api->send($data); // Old problematic call
                
                $testContent = $api->generateResponse('Test connection'); // Corrected: Use public method
                
                // Check if the API call was successful and the response structure
                // if (isset($testResponse['success']) && $testResponse['success'] && isset($testResponse['response']['choices'])) { // Old check
                if ($testContent !== null) { // Corrected: generateResponse returns content string or null
                    echo '<p class="success">' . __('AI system is operational.') . '</p>';
                } else {
                    // $errorMessage = __('AI system is not responding correctly.'); // Old error message
                    // if (isset($testResponse['error'])) {
                    //     $errorMessage .= ' Error: ' . htmlspecialchars($testResponse['error']);
                    // } else if (isset($testResponse['response']['error']['message'])) {
                    //     $errorMessage .= ' API Error: ' . htmlspecialchars($testResponse['response']['error']['message']);
                    // }
                    // echo '<p class="error">' . $errorMessage . '</p>';
                    $errorMessage = __('AI system is not responding correctly. Please check server logs for details.'); // Corrected error message
                    echo '<p class="error">' . $errorMessage . '</p>';
                }
            } catch (Exception $e) {
            echo '<p class="error">' . __('AI system connection error: ') . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}