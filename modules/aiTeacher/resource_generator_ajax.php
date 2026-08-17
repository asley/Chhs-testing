<?php
if (ob_get_level()) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/php-error.log');
ini_set('memory_limit', '512M');
set_time_limit(180);

function json_response($data) {
    if (ob_get_level()) ob_end_clean();
    $jsonOutput = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($jsonOutput === false) {
        error_log('[JSON ERROR] ' . json_last_error_msg());
        echo json_encode(['success' => false, 'error' => 'Response encoding error']);
    } else {
        echo $jsonOutput;
    }
    exit;
}

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/src/DeepSeekAPI.php';

use Gibbon\Module\aiTeacher\DeepSeekAPI;

set_exception_handler(function($e) {
    error_log("[FATAL] resource_generator_ajax.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $fatalErrorResponse = [
        'success' => false,
        'error' => 'A fatal server error occurred. Please contact your administrator.'
    ];
    error_log("[ResourceGenerator] Final JSON Response (Fatal Error): " . json_encode($fatalErrorResponse));
    json_response($fatalErrorResponse);
});

try {
    if (!isActionAccessible($guid, $connection2, '/modules/aiTeacher/resource_generator.php')) {
        json_response([
            'success' => false,
            'error' => 'You do not have access to this action.',
            'message' => 'Access denied'
        ]);
    }

    // Safe settings fetch
    if (!function_exists('getAITeacherSettings')) {
        json_response([
            'success' => false,
            'error' => 'Gibbon environment not loaded.',
            'message' => 'Configuration error'
        ]);
    }
    error_log("[ResourceGenerator] Incoming POST Data: " . print_r($_POST, true));

    $settings = getAITeacherSettings($pdo);
    if (empty($settings['deepseek_api_key'])) {
        json_response([
            'success' => false,
            'error' => 'DeepSeek API key is not configured. Please contact your administrator.',
            'message' => 'Configuration error'
        ]);
    }

    $subject = $_POST['subject'] ?? '';
    $topic = $_POST['topic'] ?? '';
    $assessmentType = $_POST['assessmentType'] ?? '';
    $customInstructions = trim($_POST['customInstructions'] ?? '');
    $mode = $_POST['mode'] ?? 'assessment';
    $questionCount = $_POST['questionCount'] ?? 10;
    $questionType = $_POST['questionType'] ?? 'multiple_choice_single';
    $gibbonPlannerEntryID = $_POST['gibbonPlannerEntryID'] ?? '';

    if (empty($subject) || empty($topic) || ($mode !== 'tcexam_csv' && empty($assessmentType))) {
        json_response([
            'success' => false,
            'error' => 'Please fill in all required fields.',
            'message' => 'Validation error'
        ]);
    }

    try {
        $lessonContext = null;
        if (!empty($gibbonPlannerEntryID)) {
            $canViewAllPlannerLessons = isActionAccessible(
                $guid,
                $connection2,
                '/modules/Planner/planner_view_full.php',
                'Lesson Planner_viewEditAllClasses'
            );

            $lessonContext = getAITeacherLessonContext(
                $pdo,
                $gibbonPlannerEntryID,
                $session->get('gibbonPersonID'),
                $canViewAllPlannerLessons
            );

            if (empty($lessonContext)) {
                json_response([
                    'success' => false,
                    'error' => 'The selected lesson could not be loaded, or you do not have access to it.',
                    'message' => 'Lesson access error'
                ]);
            }
        }

        if ($mode === 'tcexam_csv') {
            $result = generateTCExamQuestions($pdo, $subject, $topic, $questionCount, $customInstructions, $lessonContext, $questionType);
            $filenameTopic = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($topic));
            $filenameTopic = trim($filenameTopic, '-') ?: 'lesson';

            json_response([
                'success' => true,
                'message' => 'TCExam CSV Generated Successfully!',
                'questions' => $result['questions'],
                'csv' => $result['csv'],
                'filename' => 'tcexam-questions-' . strtolower($filenameTopic) . '.csv',
            ]);
        }

        $assessmentResult = generateAssessment($pdo, $subject, $topic, $assessmentType, $customInstructions, $lessonContext);
        if (is_string($assessmentResult) && strpos($assessmentResult, 'Error from AI Service:') === 0) {
            json_response([
                'success' => false,
                'error' => $assessmentResult
            ]);
        } elseif (!empty($assessmentResult)) {
            error_log("[ResourceGenerator] Assessment Result (before encoding/cleaning): " . $assessmentResult);

            $cleanContent = mb_convert_encoding($assessmentResult, 'UTF-8', 'UTF-8');
            $cleanContent = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F]/', '', $cleanContent);
            $response = [
                'success' => true,
                'message' => 'Assessment Generated Successfully!',
                'formatted_assessment' => $cleanContent
            ];
            error_log("[ResourceGenerator] Final JSON Response: " . json_encode($response));
            json_response($response);
        } else {
            json_response([
                'success' => false,
                'error' => 'The AI service returned an empty or invalid response.'
        ]);
    }
    error_log("[ResourceGenerator] Final JSON Response: " . json_encode(['success' => false, 'error' => 'The AI service returned an empty or invalid response.']));
} catch (\Exception $e) {
        error_log("AI Teacher - resource_generator_ajax.php - Exception: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
        $errorResponse = [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ];
        error_log("[ResourceGenerator] Final JSON Response (Exception): " . json_encode($errorResponse));
        json_response($errorResponse);
    }
}
catch (Throwable $e) {
    error_log("[TOP-LEVEL FATAL] resource_generator_ajax.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $fatalErrorResponse = [
        'success' => false,
        'error' => 'A fatal server error occurred. Please contact your administrator.'
    ];
    error_log("[ResourceGenerator] Final JSON Response (Fatal Error): " . json_encode($fatalErrorResponse));
    json_response($fatalErrorResponse);
    }
