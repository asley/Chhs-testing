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

/**
 * Generate a unique session ID for chat conversations
 *
 * @return string UUID v4 session ID
 */
function generateSessionID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Get or create a chat session for a student
 *
 * @param \Gibbon\Database\Connection $pdo Database connection
 * @param int $gibbonPersonID Student ID
 * @param int $gibbonSchoolYearID School year ID
 * @param int|null $gibbonCourseID Optional course ID
 * @return string Session ID
 */
function getOrCreateChatSession($pdo, $gibbonPersonID, $gibbonSchoolYearID, $gibbonCourseID = null) {
    try {
        // Check for existing active session (within last 30 minutes)
        $sql = "SELECT sessionID FROM aiTeacherChatSessions
                WHERE gibbonPersonID = :personID
                AND lastActivity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ORDER BY lastActivity DESC LIMIT 1";

        $result = $pdo->executeQuery(['personID' => $gibbonPersonID], $sql);

        if ($result && $result->rowCount() > 0) {
            $row = $result->fetch();
            return $row['sessionID'];
        }

        // Create new session
        $sessionID = generateSessionID();
        $sql = "INSERT INTO aiTeacherChatSessions
                (sessionID, gibbonPersonID, startTime, lastActivity, messageCount)
                VALUES (:sessionID, :personID, NOW(), NOW(), 0)";

        $pdo->executeQuery([
            'sessionID' => $sessionID,
            'personID' => $gibbonPersonID
        ], $sql);

        return $sessionID;

    } catch (Exception $e) {
        error_log("Error in getOrCreateChatSession: " . $e->getMessage());
        return generateSessionID(); // Fallback to new session
    }
}

/**
 * Save a student message to the database
 *
 * @param \Gibbon\Database\Connection $pdo Database connection
 * @param int $gibbonPersonID Student ID
 * @param int $gibbonSchoolYearID School year ID
 * @param string $sessionID Session ID
 * @param string $message Student's message
 * @param int|null $gibbonCourseID Optional course ID
 * @return bool Success
 */
function saveStudentMessage($pdo, $gibbonPersonID, $gibbonSchoolYearID, $sessionID, $message, $gibbonCourseID = null) {
    try {
        $sql = "INSERT INTO aiTeacherStudentConversations
                (gibbonPersonID, gibbonSchoolYearID, sessionID, message, sender, gibbonCourseID)
                VALUES (:personID, :schoolYearID, :sessionID, :message, 'student', :courseID)";

        $pdo->executeQuery([
            'personID' => $gibbonPersonID,
            'schoolYearID' => $gibbonSchoolYearID,
            'sessionID' => $sessionID,
            'message' => $message,
            'courseID' => $gibbonCourseID
        ], $sql);

        // Update session activity
        $sql = "UPDATE aiTeacherChatSessions
                SET lastActivity = NOW(), messageCount = messageCount + 1
                WHERE sessionID = :sessionID";
        $pdo->executeQuery(['sessionID' => $sessionID], $sql);

        return true;
    } catch (Exception $e) {
        error_log("Error in saveStudentMessage: " . $e->getMessage());
        return false;
    }
}

/**
 * Save an AI response to the database
 *
 * @param \Gibbon\Database\Connection $pdo Database connection
 * @param int $gibbonPersonID Student ID
 * @param int $gibbonSchoolYearID School year ID
 * @param string $sessionID Session ID
 * @param string $message AI's response
 * @param string|null $context Conversation context (JSON)
 * @param int|null $gibbonCourseID Optional course ID
 * @return bool Success
 */
function saveAIMessage($pdo, $gibbonPersonID, $gibbonSchoolYearID, $sessionID, $message, $context = null, $gibbonCourseID = null) {
    try {
        $sql = "INSERT INTO aiTeacherStudentConversations
                (gibbonPersonID, gibbonSchoolYearID, sessionID, message, sender, context, gibbonCourseID)
                VALUES (:personID, :schoolYearID, :sessionID, :message, 'ai', :context, :courseID)";

        $pdo->executeQuery([
            'personID' => $gibbonPersonID,
            'schoolYearID' => $gibbonSchoolYearID,
            'sessionID' => $sessionID,
            'message' => $message,
            'context' => $context,
            'courseID' => $gibbonCourseID
        ], $sql);

        // Update session activity
        $sql = "UPDATE aiTeacherChatSessions
                SET lastActivity = NOW(), messageCount = messageCount + 1
                WHERE sessionID = :sessionID";
        $pdo->executeQuery(['sessionID' => $sessionID], $sql);

        return true;
    } catch (Exception $e) {
        error_log("Error in saveAIMessage: " . $e->getMessage());
        return false;
    }
}

/**
 * Get conversation context (last N messages)
 *
 * @param \Gibbon\Database\Connection $pdo Database connection
 * @param string $sessionID Session ID
 * @param int $limit Number of messages to retrieve
 * @return array Array of messages
 */
function getConversationContext($pdo, $sessionID, $limit = 10) {
    try {
        // Cast limit to int to avoid SQL binding issues
        $limit = (int)$limit;

        $sql = "SELECT message, sender, timestamp
                FROM aiTeacherStudentConversations
                WHERE sessionID = :sessionID
                ORDER BY timestamp DESC
                LIMIT $limit";

        $result = $pdo->executeQuery([
            'sessionID' => $sessionID
        ], $sql);

        $messages = [];
        if ($result && $result->rowCount() > 0) {
            while ($row = $result->fetch()) {
                $messages[] = $row;
            }
        }

        // Reverse to get chronological order
        return array_reverse($messages);

    } catch (Exception $e) {
        error_log("Error in getConversationContext: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if message contains inappropriate content
 *
 * @param string $message Message to check
 * @return array ['flagged' => bool, 'reason' => string|null]
 */
function checkInappropriateContent($message) {
    $flagged = false;
    $reason = null;

    // Self-harm keywords (critical priority)
    $selfHarmKeywords = ['kill myself', 'suicide', 'end my life', 'want to die', 'hurt myself', 'self harm'];
    foreach ($selfHarmKeywords as $keyword) {
        if (stripos($message, $keyword) !== false) {
            return ['flagged' => true, 'reason' => 'self_harm', 'severity' => 'critical'];
        }
    }

    // Cheating detection
    $cheatingPatterns = [
        'give me the answer',
        'what is the answer to',
        'do my homework',
        'write my essay',
        'complete my assignment'
    ];
    foreach ($cheatingPatterns as $pattern) {
        if (stripos($message, $pattern) !== false) {
            return ['flagged' => true, 'reason' => 'cheating_attempt', 'severity' => 'medium'];
        }
    }

    // Profanity check (basic list - extend as needed)
    $profanityList = ['fuck', 'shit', 'damn', 'bitch', 'ass'];
    foreach ($profanityList as $word) {
        if (stripos($message, $word) !== false) {
            return ['flagged' => true, 'reason' => 'profanity', 'severity' => 'low'];
        }
    }

    return ['flagged' => false, 'reason' => null, 'severity' => null];
}

/**
 * Get AI tutor response with conversation context
 *
 * @param \Gibbon\Database\Connection $pdo Database connection
 * @param int $gibbonPersonID Student ID
 * @param int $gibbonSchoolYearID School year ID
 * @param string $message Student's message
 * @param string $sessionID Session ID
 * @param int|null $gibbonCourseID Optional course ID
 * @return array ['success' => bool, 'response' => string, 'flagged' => bool, 'flagReason' => string|null]
 */
function getAITutorResponse($pdo, $gibbonPersonID, $gibbonSchoolYearID, $message, $sessionID, $gibbonCourseID = null) {
    try {
        // Check for inappropriate content
        $contentCheck = checkInappropriateContent($message);
        if ($contentCheck['flagged']) {
            // Flag in database
            $sql = "UPDATE aiTeacherStudentConversations
                    SET flagged = 1, flagReason = :reason
                    WHERE sessionID = :sessionID
                    ORDER BY timestamp DESC LIMIT 1";
            $pdo->executeQuery([
                'reason' => $contentCheck['reason'],
                'sessionID' => $sessionID
            ], $sql);

            // Critical content - alert and block
            if ($contentCheck['severity'] === 'critical') {
                return [
                    'success' => false,
                    'response' => 'I notice you might be going through a difficult time. Please speak with a teacher, counselor, or trusted adult immediately. Your wellbeing is important.',
                    'flagged' => true,
                    'flagReason' => $contentCheck['reason']
                ];
            }

            // Medium severity - warn but allow
            if ($contentCheck['severity'] === 'medium') {
                $warningResponse = "I'm here to help you understand concepts, not to do your work for you. Let me guide you to find the answer yourself. " .
                                   "Can you tell me what you've tried so far?";
                return [
                    'success' => true,
                    'response' => $warningResponse,
                    'flagged' => true,
                    'flagReason' => $contentCheck['reason']
                ];
            }
        }

        // Get conversation context
        $context = getConversationContext($pdo, $sessionID, 10);

        // Build context string for AI
        $contextString = "";
        foreach ($context as $msg) {
            $role = $msg['sender'] === 'student' ? 'Student' : 'AI Tutor';
            $contextString .= "{$role}: {$msg['message']}\n";
        }

        // Get AI settings
        $settings = getAITeacherSettings($pdo);
        $apiKey = $settings['deepseek_api_key'] ?? null;

        if (empty($apiKey)) {
            return [
                'success' => false,
                'response' => 'AI service is not configured. Please contact your teacher.',
                'flagged' => false,
                'flagReason' => null
            ];
        }

        // Create AI API instance
        $api = new \Gibbon\Module\aiTeacher\DeepSeekAPI($apiKey);

        // Build AI prompt with personality and guidelines
        $systemPrompt = "You are a patient, encouraging CSEC tutor helping a high school student. Your role is to:\n" .
                       "1. Guide students to understand concepts, not give direct answers\n" .
                       "2. Ask clarifying questions to understand their confusion\n" .
                       "3. Break down complex topics into simple steps\n" .
                       "4. Provide examples and analogies\n" .
                       "5. Encourage effort and celebrate progress\n" .
                       "6. If stuck, suggest they review specific textbook sections or ask their teacher\n\n" .
                       "Guidelines:\n" .
                       "- Never solve homework problems completely - guide them through it\n" .
                       "- Use encouraging language\n" .
                       "- Keep responses concise (2-3 short paragraphs max)\n" .
                       "- Use simple language appropriate for high school level\n\n" .
                       "FORMATTING INSTRUCTIONS (IMPORTANT - USE THESE):\n" .
                       "- Use **bold** for emphasis and key terms\n" .
                       "- Use *italics* for definitions or examples\n" .
                       "- Use numbered lists (1. 2. 3.) for step-by-step explanations\n" .
                       "- Use bullet points (- item) for listing concepts\n" .
                       "- For math expressions: Use LaTeX with $ for inline math like $F = ma$ or $$....$$ for equations on their own line\n" .
                       "- For physics formulas: Use proper symbols like $v = \\frac{d}{t}$, $a = \\frac{\\Delta v}{\\Delta t}$, $E = mc^2$\n" .
                       "- For chemistry: Use subscripts like H$_2$O, CO$_2$, or superscripts for charges like Ca$^{2+}$\n" .
                       "- Example: 'The formula for velocity is $v = \\frac{distance}{time}$ where $v$ is velocity in m/s.'\n\n";

        if (!empty($contextString)) {
            $systemPrompt .= "Previous conversation:\n{$contextString}\n\n";
        }

        $fullPrompt = $systemPrompt . "Student: {$message}\nAI Tutor:";

        // Get AI response
        $aiResponse = $api->generateResponse($fullPrompt);

        if ($aiResponse === null || empty(trim($aiResponse))) {
            return [
                'success' => false,
                'response' => 'Sorry, I encountered a problem. Please try asking your question again.',
                'flagged' => false,
                'flagReason' => null
            ];
        }

        // Save AI response to database
        saveAIMessage($pdo, $gibbonPersonID, $gibbonSchoolYearID, $sessionID, $aiResponse, json_encode($context), $gibbonCourseID);

        return [
            'success' => true,
            'response' => $aiResponse,
            'flagged' => $contentCheck['flagged'],
            'flagReason' => $contentCheck['reason'] ?? null
        ];

    } catch (Exception $e) {
        error_log("Error in getAITutorResponse: " . $e->getMessage());
        return [
            'success' => false,
            'response' => 'An error occurred. Please try again.',
            'flagged' => false,
            'flagReason' => null
        ];
    }
}

/**
 * Render markdown and mathematical expressions for AI tutor messages
 *
 * @param string $text The message text to render
 * @return string HTML-formatted text
 */
function renderMarkdownAndMath($text) {
    if (empty($text)) {
        return '';
    }

    // First, escape HTML to prevent XSS
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Split into lines for processing
    $lines = explode("\n", $text);
    $html = '';
    $inList = false;
    $inNumberedList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Check for numbered list (1. item, 2. item, etc.)
        if (preg_match('/^(\d+)\.\s+(.+)$/', $trimmed, $matches)) {
            if (!$inNumberedList) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<ol class="ai-numbered-list">';
                $inNumberedList = true;
            }
            $html .= '<li>' . formatInlineMarkdown($matches[2]) . '</li>';
            continue;
        }

        // Check for bullet list (- item or * item)
        if (preg_match('/^[-\*]\s+(.+)$/', $trimmed, $matches)) {
            if (!$inList) {
                if ($inNumberedList) {
                    $html .= '</ol>';
                    $inNumberedList = false;
                }
                $html .= '<ul class="ai-bullet-list">';
                $inList = true;
            }
            $html .= '<li>' . formatInlineMarkdown($matches[1]) . '</li>';
            continue;
        }

        // Close any open lists if we're not in a list line
        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }
        if ($inNumberedList) {
            $html .= '</ol>';
            $inNumberedList = false;
        }

        // Process regular paragraph
        if (!empty($trimmed)) {
            $html .= '<p>' . formatInlineMarkdown($line) . '</p>';
        } else {
            $html .= '<br>';
        }
    }

    // Close any remaining open lists
    if ($inList) {
        $html .= '</ul>';
    }
    if ($inNumberedList) {
        $html .= '</ol>';
    }

    return $html;
}

/**
 * Format inline markdown (bold, italics, math)
 *
 * @param string $text Text to format
 * @return string Formatted HTML
 */
function formatInlineMarkdown($text) {
    // Process math expressions first (to protect them from other formatting)
    // Block math: $$...$$
    $text = preg_replace_callback('/\$\$(.+?)\$\$/s', function($matches) {
        return '<span class="math-block">\\[' . $matches[1] . '\\]</span>';
    }, $text);

    // Inline math: $...$
    $text = preg_replace_callback('/\$(.+?)\$/s', function($matches) {
        return '<span class="math-inline">\\(' . $matches[1] . '\\)</span>';
    }, $text);

    // Bold: **text**
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

    // Italics: *text* (but not ** which is bold)
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);

    return $text;
}

/**
 * Get chat history for a student
 *
 * @param \Gibbon\Database\Connection $pdo Database connection
 * @param int $gibbonPersonID Student ID
 * @param int $limit Number of recent sessions to retrieve
 * @return array Array of chat sessions
 */
function getChatHistory($pdo, $gibbonPersonID, $limit = 10) {
    try {
        $sql = "SELECT s.sessionID, s.startTime, s.lastActivity, s.topic, s.messageCount, s.resolved
                FROM aiTeacherChatSessions s
                WHERE s.gibbonPersonID = :personID
                ORDER BY s.lastActivity DESC
                LIMIT :limit";

        $result = $pdo->executeQuery([
            'personID' => $gibbonPersonID,
            'limit' => $limit
        ], $sql);

        $sessions = [];
        if ($result && $result->rowCount() > 0) {
            while ($row = $result->fetch()) {
                $sessions[] = $row;
            }
        }

        return $sessions;

    } catch (Exception $e) {
        error_log("Error in getChatHistory: " . $e->getMessage());
        return [];
    }
}

