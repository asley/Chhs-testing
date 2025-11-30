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
$page->breadcrumbs->add(__('Settings'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/settings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get module settings
    $settings = getAITeacherSettings($pdo); // This function might also need a slight tweak if it specifically looks for deepseek_api_key by name
    
    // Ensure settings rows exist
    $settingNames = [
        'openai_api_key' => 'OpenAI API Key for AI integration',
        'deepseek_api_key' => 'DeepSeek API Key for AI integration',
        'upload_path' => 'Path for storing uploaded resources',
        'score_threshold' => 'Threshold for student performance alerts (percentage)'
    ];

    foreach ($settingNames as $name => $desc) {
        $sqlCheck = "SELECT COUNT(*) FROM aiTeacherSettings WHERE scope = 'aiTeacher' AND name = :name";
        $result = $pdo->executeQuery(['name' => $name], $sqlCheck);
        if ($result->fetchColumn() == 0) {
            $insertSql = "INSERT INTO aiTeacherSettings (scope, name, value, description) VALUES ('aiTeacher', :name, '', :desc)";
            $pdo->executeQuery(['name' => $name, 'desc' => $desc], $insertSql);
        }
    }

    // Handle form submission
    if (isset($_POST['submit'])) {
        // Validate input
        $openai_api_key = $_POST['openai_api_key'] ?? ''; // Changed
        $upload_path = $_POST['upload_path'] ?? 'uploads/aiTeacher';
        $score_threshold = $_POST['score_threshold'] ?? 60;
        $deepseek_api_key = $_POST['deepseek_api_key'] ?? '';
        
        // Update settings
        $sql = "UPDATE aiTeacherSettings SET value = :value WHERE scope = 'aiTeacher' AND name = :name";
        
        $pdo->executeQuery(
            array('value' => $openai_api_key, 'name' => 'openai_api_key'), // Changed
            $sql
        );
        
        $pdo->executeQuery(
            array('value' => $upload_path, 'name' => 'upload_path'),
            $sql
        );
        
        $pdo->executeQuery(
            array('value' => $score_threshold, 'name' => 'score_threshold'),
            $sql
        );
        
        $pdo->executeQuery(
            array('value' => $deepseek_api_key, 'name' => 'deepseek_api_key'),
            $sql
        );
        
        $page->addSuccess(__('Settings updated successfully.'));
    }
    
    // Display form
    echo '<form method="post" action="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/settings.php">';
    
    echo '<table class="fullWidth">';
    
    // OpenAI API Key // Changed comment
    echo '<tr>';
    echo '<td>';
    echo '<b>' . __('OpenAI API Key') . '</b><br/>'; // Changed
    echo __('Enter your OpenAI API key for AI integration.') . '<br/>'; // Changed
    echo '</td>';
    echo '<td class="right">';
    echo '<input type="password" name="openai_api_key" value="' . htmlspecialchars($settings['openai_api_key'] ?? '') . '" style="width: 300px">'; // Changed name and settings key
    echo '</td>';
    echo '</tr>';
    
    // DeepSeek API Key
    echo '<tr>';
    echo '<td>';
    echo '<b>' . __('DeepSeek API Key') . '</b><br/>';
    echo __('Enter your DeepSeek API key for AI integration.') . '<br/>';
    echo '</td>';
    echo '<td class="right">';
    echo '<input type="password" name="deepseek_api_key" value="' . htmlspecialchars($settings['deepseek_api_key'] ?? '') . '" style="width: 300px">';
    echo '</td>';
    echo '</tr>';
    
    // Upload Path
    echo '<tr>';
    echo '<td>';
    echo '<b>' . __('Upload Path') . '</b><br/>';
    echo __('Path for storing uploaded resources.') . '<br/>';
    echo '</td>';
    echo '<td class="right">';
    echo '<input type="text" name="upload_path" value="' . htmlspecialchars($settings['upload_path'] ?? 'uploads/aiTeacher') . '" style="width: 300px">';
    echo '</td>';
    echo '</tr>';
    
    // Score Threshold
    echo '<tr>';
    echo '<td>';
    echo '<b>' . __('Score Threshold') . '</b><br/>';
    echo __('Threshold for student performance alerts (percentage).') . '<br/>';
    echo '</td>';
    echo '<td class="right">';
    echo '<input type="number" name="score_threshold" value="' . htmlspecialchars($settings['score_threshold'] ?? '60') . '" min="0" max="100" style="width: 100px">';
    echo '</td>';
    echo '</tr>';
    
    echo '</table>';
    
    echo '<div class="right">';
    echo '<input type="submit" name="submit" value="' . __('Save') . '">';
    echo '</div>';
    
    echo '</form>';
}