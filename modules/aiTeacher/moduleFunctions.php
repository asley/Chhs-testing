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
use Gibbon\Module\aiTeacher\AITeacherService;
use Gibbon\Module\aiTeacher\OpenAIAPI; // Add this line

// Module Functions
function getAITeacherSettings($pdo, $scope = 'aiTeacher') {
    try {
        // First check if the table exists
        $checkTable = "SHOW TABLES LIKE 'aiTeacherSettings'";
        $tableExists = $pdo->executeQuery(array(), $checkTable)->rowCount() > 0;
        
        if (!$tableExists) {
            return array();
        }
        
        $sql = "SELECT name, value FROM aiTeacherSettings WHERE scope = :scope";
        $result = $pdo->executeQuery(array('scope' => $scope), $sql);
        
        $settings = array();
        while ($row = $result->fetch()) {
            $settings[$row['name']] = $row['value'];
        }
        return $settings;
    } catch (Exception $e) {
        // Log error and return empty settings
        error_log("Error in getAITeacherSettings: " . $e->getMessage());
        return array();
    }
}

function logAITeacherAction($pdo, $gibbonPersonID, $action, $subject, $details, $response) {
    try {
        // First check if the table exists
        $checkTable = "SHOW TABLES LIKE 'aiTeacherLogs'";
        $tableExists = $pdo->executeQuery(array(), $checkTable)->rowCount() > 0;
        
        if (!$tableExists) {
            error_log("aiTeacherLogs table does not exist");
            return;
        }

        $sql = "INSERT INTO aiTeacherLogs (gibbonPersonID, action, subject, details, response) 
                VALUES (:gibbonPersonID, :action, :subject, :details, :response)";
        
        if (empty($sql)) {
            error_log("SQL query is empty in logAITeacherAction");
            return;
        }

        $pdo->executeQuery(array(
            'gibbonPersonID' => $gibbonPersonID,
            'action' => $action,
            'subject' => $subject,
            'details' => $details,
            'response' => $response
        ), $sql);
    } catch (Exception $e) {
        error_log("Error in logAITeacherAction: " . $e->getMessage());
    }
}

function uploadAITeacherResource($pdo, $gibbonPersonID, $file, $subject, $description) {
    $settings = getAITeacherSettings($pdo);
    $uploadPath = $settings['upload_path'] ?? 'uploads/aiTeacher';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    $filename = basename($file['name']);
    $filepath = $uploadPath . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $sql = "INSERT INTO aiTeacherUploads (gibbonPersonID, filename, filepath, filetype, filesize, subject, description) 
                VALUES (:gibbonPersonID, :filename, :filepath, :filetype, :filesize, :subject, :description)";
        $pdo->executeQuery(array(
            'gibbonPersonID' => $gibbonPersonID,
            'filename' => $filename,
            'filepath' => $filepath,
            'filetype' => $file['type'],
            'filesize' => $file['size'],
            'subject' => $subject,
            'description' => $description
        ));
        return true;
    }
    return false;
}

// Potentially update getAITeacherSettings if it's hardcoded to look for 'deepseek_api_key'
// For example, it might look like this:
/*
function getAITeacherSettings($pdo) {
    $settings = [];
    $sql = "SELECT name, value FROM aiTeacherSettings WHERE scope = 'aiTeacher'";
    $result = $pdo->executeQuery(array(), $sql);
    if ($result->rowCount() > 0) {
        while ($row = $result->fetch()) {
            $settings[$row['name']] = $row['value'];
        }
    }
    return $settings;
}
*/

function generateLessonPlan($pdo, $subject, $topic, $gradeLevel, $objectives) { // Added $objectives parameter
    $settings = getAITeacherSettings($pdo);
    
    if (empty($settings['openai_api_key'])) {
        error_log("AI Teacher - generateLessonPlan: OpenAI API key is not set.");
        return "Error: OpenAI API key is not configured. Please check module settings.";
    }

    try {
        // Ensure the OpenAIAPI class exists before trying to use it
        if (!class_exists('Gibbon\Module\aiTeacher\OpenAIAPI')) {
            error_log("AI Teacher - generateLessonPlan: OpenAIAPI class not found. Ensure it's correctly namespaced and included.");
            return "Error: AI Service component (OpenAIAPI) is missing. Please contact administrator.";
        }

        $api = new \Gibbon\Module\aiTeacher\OpenAIAPI($settings['openai_api_key']);
        
        $prompt = "Generate a detailed CSEC lesson plan for {$subject} on the topic of {$topic} for grade level {$gradeLevel}. " .
                   "The learning objectives are: {$objectives}. " .
                   "Include learning objectives (elaborate on the provided ones if necessary), activities, assessment criteria, and resources needed.";
        
        error_log("AI Teacher - generateLessonPlan: Sending prompt to OpenAI: " . $prompt); // Log the prompt

        $response = $api->generateResponse($prompt);
        
        if ($response === null) {
            error_log("AI Teacher - generateLessonPlan: OpenAIAPI->generateResponse returned null. Check OpenAIAPI class and API communication.");
            return "Error: The AI service returned an empty response. Please try again or check logs.";
        } elseif (empty(trim($response))) {
            error_log("AI Teacher - generateLessonPlan: OpenAIAPI->generateResponse returned an empty string. Prompt: " . $prompt);
            return "Error: The AI service returned an empty lesson plan. This might be due to the prompt or an API issue.";
        }
        
        error_log("AI Teacher - generateLessonPlan: Received response from OpenAI (first 100 chars): " . substr(is_string($response) ? $response : '', 0, 100));
        return $response;

    } catch (\Exception $e) {
        error_log("AI Teacher - generateLessonPlan: Exception caught: " . $e->getMessage());
        error_log("AI Teacher - generateLessonPlan: Stack Trace: " . $e->getTraceAsString());
        return "Error: An unexpected error occurred while generating the lesson plan. Details: " . htmlspecialchars($e->getMessage());
    }
}

function generateInterventionStrategy($pdo, $subject, $score) {
    $settings = getAITeacherSettings($pdo);
    if (empty($settings['openai_api_key'])) {
        error_log("OpenAI API key is not set.");
        return "Error: OpenAI API key is not configured.";
    }
    $api = new OpenAIAPI($settings['openai_api_key']); // Changed
    
    $prompt = "Generate specific intervention strategies for a student scoring {$score}% in {$subject}. 
               Focus on practical, actionable steps for improvement.";
    
    $response = $api->generateResponse($prompt);
    return $response;
}

// Ensure the use statement is present if this file is namespaced,
// or use the fully qualified class name if this file is in the global namespace.
// If moduleFunctions.php itself is namespaced (e.g., namespace Gibbon\Module\aiTeacher;), then:
// use Gibbon\Module\aiTeacher\DeepSeekAPI; 
// (This use statement would typically be at the top of moduleFunctions.php)

function generateAssessment($pdo, $subject, $topic, $assessmentType, $customInstructions = '') {
    // Get settings (assuming getAITeacherSettings is available)
    $settings = getAITeacherSettings($pdo);
    $apiKey = $settings['deepseek_api_key'] ?? null;

    if (empty($apiKey)) {
        throw new \Exception("DeepSeek API key is not configured.");
    }

    // Construct the prompt
    $prompt = "Generate a '{$assessmentType}' assessment for the CSEC subject '{$subject}' on the topic '{$topic}'.";
    if (!empty($customInstructions)) {
        $prompt .= " Additional instructions: {$customInstructions}";
    }

    // Instantiate DeepSeekAPI using its fully qualified namespace if no 'use' statement is active
    // If 'use Gibbon\Module\aiTeacher\DeepSeekAPI;' is at the top of moduleFunctions.php,
    // you can just use: $api = new DeepSeekAPI($apiKey);
    $api = new \Gibbon\Module\aiTeacher\DeepSeekAPI($apiKey); 
    
    error_log("[moduleFunctions - generateAssessment] Prompt sent to DeepSeekAPI: " . $prompt);
    $generatedContent = $api->generateResponse($prompt);
    error_log("[moduleFunctions - generateAssessment] Raw response from DeepSeekAPI: " . print_r($generatedContent, true));

    if ($generatedContent === null) {
        // The generateResponse method in DeepSeekAPI now returns null on failure
        // and logs errors internally. You might want to throw an exception here
        // or return a specific error indicator.
        throw new \Exception("Failed to get a valid response from the AI service. Check API logs.");
    }

    error_log("[moduleFunctions - generateAssessment] Content returned by generateAssessment: " . print_r($generatedContent, true));
    return $generatedContent;
}

/**
 * Analyzes the provided text content using the AI model.
 *
 * @param \Gibbon\Database\Connection $pdo
 * @param string $fileContent The text content of the file.
 * @param string $userPrompt An optional prompt from the user about what to analyze.
 * @return string|null The AI's analysis or null on error.
 */
function analyzeFileContent($pdo, $fileContent, $userPrompt = '') {
    $settings = getAITeacherSettings($pdo);
    if (empty($settings['openai_api_key'])) {
        error_log("OpenAI API key is not set for file analysis.");
        return "Error: OpenAI API key is not configured.";
    }
    $api = new OpenAIAPI($settings['openai_api_key']);

    $systemMessage = "You are an AI assistant. Analyze the following text content.";
    if (!empty($userPrompt)) {
        $systemMessage .= " The user's specific request for this file is: " . $userPrompt;
    }
    
    // Constructing a prompt that includes the file content.
    // Be mindful of token limits for very large files.
    // For now, we'll send the whole content.
    $fullPrompt = $systemMessage . "\n\n--- File Content Start ---\n" . $fileContent . "\n--- File Content End ---";

    // You might want to add instructions to the prompt, e.g.:
    // $fullPrompt .= "\n\nPlease provide a summary and identify key points.";

    $response = $api->generateResponse($fullPrompt);
    return $response;
}

function analyzeStudentPerformance($pdo, $studentID, $subject) {
    $settings = getAITeacherSettings($pdo);
    $threshold = $settings['score_threshold'] ?? 60;
    
    // Get student's assessment data from Gibbon
    // Using CamelCase table names as per Gibbon conventions and manifest
    $sql = "SELECT 
                    iae.gibbonInternalAssessmentEntryID,
                    iac.gibbonInternalAssessmentColumnID,
                    iac.name AS columnName,
                    iae.attainmentValue AS score,
                    c.nameShort AS courseName,
                    iae.comment
                FROM gibbonInternalAssessmentEntry iae
                JOIN gibbonInternalAssessmentColumn iac ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
                JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
                JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
                WHERE iae.gibbonPersonIDStudent = :studentID
                AND c.nameShort = :subject
                AND iac.name = 'Overall'
                ORDER BY iae.gibbonInternalAssessmentEntryID DESC";
    
    // Ensure $sql is not empty before proceeding (defensive coding)
    if (empty($sql)) {
        error_log("AI Teacher Module: In analyzeStudentPerformance, the SQL query string was unexpectedly empty for Student ID: {$studentID}, Subject: {$subject}. Review query generation logic.");
        return []; 
    }
    
    $result = $pdo->executeQuery(array(
        'studentID' => $studentID,
        'subject' => $subject
    ), $sql); 
    
    // Check if $result is false (indicating an error) before proceeding
    if ($result === false) {
        error_log("AI Teacher Module: SQL query execution failed in analyzeStudentPerformance for Student ID: {$studentID}, Subject: {$subject}. PDO Error: " . print_r($pdo->getErrorInfo(), true));
        return []; // Return empty or handle error appropriately
    }
    
    $assessments = $result->fetchAll(\PDO::FETCH_ASSOC);
    $analysis = array();
    
    foreach ($assessments as $assessment) {
        // Ensure 'score' key exists and is numeric
        if (isset($assessment['score']) && is_numeric($assessment['score'])) {
            $scoreValue = floatval($assessment['score']); // Convert score to float
            if ($scoreValue < $threshold) {
                $analysis[] = array(
                    'date' => $assessment['date'],
                    'score' => $scoreValue, // Use the numeric score
                    'feedback' => generateInterventionStrategy($pdo, $subject, $scoreValue)
                );
            }
        } else {
            // Log if score is missing or not numeric, to help debug data issues
            error_log("AI Teacher Module: Missing or non-numeric score for assessment ID " . ($assessment['gibbonInternalAssessmentID'] ?? 'N/A') . " for student {$studentID}, subject {$subject}. Score data: " . print_r($assessment, true));
        }
    }
    
    return $analysis;
}

