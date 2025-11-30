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
$page->breadcrumbs->add(__('Upload Resource'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/upload_resource.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get module settings
    $settings = getAITeacherSettings($pdo);
    
    // Handle form submission
    if (isset($_POST['submit'])) {
        $subject = $_POST['subject'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (empty($subject) || empty($_FILES['resource']['name'])) {
            $page->addError(__('Please fill in all required fields and select a file to upload.'));
        } else {
            try {
                if (uploadAITeacherResource($pdo, $gibbon->session->get('gibbonPersonID'), $_FILES['resource'], $subject, $description)) {
                    // Log the action
                    logAITeacherAction($pdo, $gibbon->session->get('gibbonPersonID'), 'Upload Resource', $subject, 
                        "Description: {$description}", $_FILES['resource']['name']);
                    
                    $page->addSuccess(__('Resource uploaded successfully.'));
                } else {
                    $page->addError(__('Failed to upload resource. Please try again.'));
                }
            } catch (Exception $e) {
                $page->addError(__('An error occurred while uploading the resource.'));
            }
        }
    }
    
    // Display form
    echo '<form method="post" action="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/upload_resource.php" enctype="multipart/form-data">';
    
    echo '<table class="fullWidth">';
    
    // Subject
    echo '<tr>';
    echo '<td>';
    echo '<b>' . __('Subject') . ' *</b><br/>';
    echo __('Select the subject for this resource.') . '<br/>';
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
        echo '<option value="Technical Drawing">' . __('Technical Drawing') . '</option>';
        echo '<option value="Social Studies">' . __('Social Studies') . '</option>';
        echo '<option value="POB">' . __('POB') . '</option>';
    echo '<option value="Information Technology">' . __('Information Technology') . '</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    
    // File Upload
    echo '<tr>';
    echo '<td>';
    echo '<b>' . __('Resource File') . ' *</b><br/>';
    echo __('Select a file to upload (PDF, DOC, DOCX, TXT).') . '<br/>';
    echo '</td>';
    echo '<td class="right">';
    echo '<input type="file" name="resource" style="width: 300px">';
    echo '</td>';
    echo '</tr>';
    
    // Description
    echo '<tr>';
    echo '<td>';
    echo '<b>' . __('Description') . '</b><br/>';
    echo __('Enter a description of the resource.') . '<br/>';
    echo '</td>';
    echo '<td class="right">';
    echo '<textarea name="description" rows="4" style="width: 300px">' . htmlspecialchars($_POST['description'] ?? '') . '</textarea>';
    echo '</td>';
    echo '</tr>';
    
    echo '</table>';
    
    echo '<div class="right">';
    echo '<input type="submit" name="submit" value="' . __('Upload Resource') . '">';
    echo '</div>';
    
    echo '</form>';
    
    // Display recent uploads
    echo '<h3>' . __('Recent Uploads') . '</h3>';
    
    $sql = "SELECT u.*, p.preferredName, p.surname 
            FROM aiTeacherUploads u 
            JOIN gibbonPerson p ON u.gibbonPersonID = p.gibbonPersonID 
            ORDER BY u.timestamp DESC 
            LIMIT 5";
    $result = $pdo->executeQuery(array(), $sql);
    
    if ($result->rowCount() > 0) {
        echo '<table class="fullWidth colorOddEven">';
        echo '<tr>';
        echo '<th>' . __('Date') . '</th>';
        echo '<th>' . __('User') . '</th>';
        echo '<th>' . __('Subject') . '</th>';
        echo '<th>' . __('File') . '</th>';
        echo '<th>' . __('Description') . '</th>';
        echo '</tr>';
        
        while ($row = $result->fetch()) {
            echo '<tr>';
            echo '<td>' . date('Y-m-d H:i', strtotime($row['timestamp'])) . '</td>';
            echo '<td>' . formatName('', $row['preferredName'], $row['surname'], 'Staff') . '</td>';
            echo '<td>' . $row['subject'] . '</td>';
            echo '<td><a href="' . $gibbon->session->get('absoluteURL') . '/' . $row['filepath'] . '" target="_blank">' . $row['filename'] . '</a></td>';
            echo '<td>' . $row['description'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    } else {
        echo '<p>' . __('No resources have been uploaded yet.') . '</p>';
    }
} 